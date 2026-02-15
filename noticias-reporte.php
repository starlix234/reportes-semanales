<?php
/*
Plugin Name: Noticias Reporte - Exportador semanal de noticias
Description: Muestra categorías y noticias de la última semana. Exporta CSV, Word (.doc) y genera página imprimible para PDF.
Version: 1.0
Author: Tu nombre
Text Domain: noticias-reporte
*/

if (!defined('ABSPATH')) exit;

class NR_Plugin {

    private $slug = 'noticias-reporte';

    public function __construct() {
        add_shortcode('noticias_reporte', [$this, 'shortcode_ui']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_nr_get_posts', [$this, 'ajax_get_posts']);
        add_action('wp_ajax_nopriv_nr_get_posts', [$this, 'ajax_get_posts']);
        add_action('admin_post_nr_export', [$this, 'handle_export']);          // form submit (logged in)
        add_action('admin_post_nopriv_nr_export', [$this, 'handle_export']);   // form submit (public)
    }

    public function enqueue_assets() {
        wp_enqueue_style('nr-style', plugin_dir_url(__FILE__) . 'assets/nr-style.css', [], '1.0');
        wp_enqueue_script('nr-script', plugin_dir_url(__FILE__) . 'assets/nr-script.js', ['jquery'], '1.0', true);
        wp_localize_script('nr-script', 'nr_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('nr_nonce')
        ]);
    }

    public function shortcode_ui($atts) {
        $cats = get_categories(['hide_empty' => true]);
        ob_start(); ?>
<div class="nr-wrap container">
    <header class="nr-header" role="banner">
        <img class="nr-logo" src="<?php echo esc_url( plugin_dir_url(__FILE__) . 'assets/default-thumb.jpg' ); ?>" alt="<?php esc_attr_e('Logo', 'noticias-reporte'); ?>">
    </header>

    <div class="nr-categories">
        <strong>Selecciona categoría(s):</strong>
        <form id="nr-filter-form" onsubmit="return false;">
            <?php foreach ($cats as $cat): ?>
                <label>
                    <input type="checkbox" name="cats[]" value="<?php echo esc_attr($cat->term_id); ?>">
                    <?php echo esc_html($cat->name); ?>
                </label>
            <?php endforeach; ?>
            <div style="margin-top:10px;">
                <button type="button" id="nr-filter-btn">Mostrar noticias (última semana)</button>
            </div>
        </form>
    </div>

    <div id="nr-results" style="margin-top:20px;">
        <?php
        // Render inicial (server-side): primera página con posts de última semana
        $this->render_posts_by_date_and_cats([], 20);
        ?>
    </div>

    <div id="nr-export" style="margin-top:20px;">
        <strong>Exportar reporte:</strong>

        <!-- Usamos un form POST clásico para descargas que envía al endpoint admin-post.php -->
        <form id="nr-export-form" method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" style="display:inline-block;margin-left:8px;">
            <?php wp_nonce_field('nr_export_nonce', 'nr_export_nonce'); ?>
            <input type="hidden" name="action" value="nr_export">
            <input type="hidden" name="export_type" value="csv" id="export_type_input">
            <!-- Los inputs cats[] los inyecta JS al hacer submit -->
            <button type="submit" class="nr-export-btn" data-type="csv" onclick="document.getElementById('export_type_input').value='csv'">CSV</button>
            <button type="submit" class="nr-export-btn" data-type="word" onclick="document.getElementById('export_type_input').value='word'">Word (.doc)</button>
            <button type="button" class="nr-export-btn" id="btn-printable">PDF imprimible</button>
        </form>

    </div>

    <footer class="footer-news">
        <div class="footer-inner">
            <h3>Visítanos en nuestro sitio web</h3>
            <p>Mantente al tanto de todas las novedades visitando <a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html(home_url()); ?></a></p>
        </div>
    </footer>
</div>
        <?php
        return ob_get_clean();
    }

    /**
     * Server-side renderer para la lista (usado en inicial y en AJAX)
     * $cats: array de term_ids
     */
    public function render_posts_by_date_and_cats($cats = [], $limit = 20) {
        $args = [
            'post_type' => 'post',
            'posts_per_page' => intval($limit),
            'date_query' => [
                ['after' => date('Y-m-d', strtotime('-7 days')), 'inclusive' => true]
            ],
            'orderby' => 'date',
            'order' => 'DESC',
        ];
        if (!empty($cats)) $args['category__in'] = $cats;

        $q = new WP_Query($args);

        if ($q->have_posts()) {
            while ($q->have_posts()) {
                $q->the_post();
                $this->render_article_html(get_the_ID());
            }
            wp_reset_postdata();
        } else {
            echo '<p>No hay noticias en la última semana.</p>';
        }
    }

    /**
     * Imprime la estructura de cada artículo (escapando todo)
     */
    private function render_article_html($post_id) {
        $title = get_the_title($post_id);
        $permalink = get_permalink($post_id);
        $excerpt = get_the_excerpt($post_id);
        $thumb = get_the_post_thumbnail_url($post_id, 'medium') ?: plugin_dir_url(__FILE__) . 'assets/default-thumb.jpg';
        ?>
<article class="nr-article" id="post-<?php echo esc_attr($post_id); ?>">
    <h2><?php echo esc_html($title); ?></h2>
    <div class="image-container">
        <a target="_blank" href="<?php echo esc_url($permalink); ?>">
            <img src="<?php echo esc_url($thumb); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy">
        </a>
    </div>
    <div class="description-container">
        <p><?php echo wp_kses_post( wpautop( wp_trim_words( $excerpt, 45, '...' ) ) ); ?></p>
        <a target="_blank" href="<?php echo esc_url($permalink); ?>" class="button">Continuar leyendo</a>
    </div>
</article>
        <?php
    }

    /* ---------- AJAX: retorna JSON con posts ---------- */
    public function ajax_get_posts() {
        check_ajax_referer('nr_nonce', 'nonce');

        $cats = [];
        if (!empty($_POST['cats']) && is_array($_POST['cats'])) {
            $cats = array_map('intval', $_POST['cats']);
        }

        ob_start();
        $this->render_posts_by_date_and_cats($cats, 100);
        $html = ob_get_clean();

        wp_send_json_success(['html' => $html]);
    }

    /**
     * Maneja export (CSV o Word)
     * Envia archivo con header apropiado.
     */
    public function handle_export() {
        if (!isset($_POST['nr_export_nonce']) || !wp_verify_nonce($_POST['nr_export_nonce'], 'nr_export_nonce')) {
            wp_die('Nonce no válido', 'Error', ['response' => 403]);
        }

        $export_type = isset($_POST['export_type']) ? sanitize_text_field($_POST['export_type']) : 'csv';
        $cats = [];
        if (!empty($_POST['cats']) && is_array($_POST['cats'])) {
            $cats = array_map('intval', $_POST['cats']);
        }

        // Recuperar posts (misma query)
        $args = [
            'post_type' => 'post',
            'posts_per_page' => -1,
            'date_query' => [
                ['after' => date('Y-m-d', strtotime('-7 days')), 'inclusive' => true]
            ],
            'orderby' => 'date',
            'order' => 'DESC',
        ];
        if (!empty($cats)) $args['category__in'] = $cats;

        $q = new WP_Query($args);
        $rows = [];
        if ($q->have_posts()) {
            while ($q->have_posts()) {
                $q->the_post();
                $rows[] = [
                    'ID' => get_the_ID(),
                    'title' => get_the_title(),
                    'date' => get_the_date('Y-m-d H:i'),
                    'excerpt' => wp_strip_all_tags(get_the_excerpt()),
                    'thumbnail' => get_the_post_thumbnail_url(get_the_ID(), 'full') ?: '',
                    'permalink' => get_permalink(),
                    'categories' => implode(', ', wp_get_post_categories(get_the_ID(), ['fields' => 'names'])),
                ];
            }
            wp_reset_postdata();
        }

        if ($export_type === 'csv') {
            $this->output_csv($rows);
        } elseif ($export_type === 'word') {
            $this->output_word($rows);
        } else {
            wp_die('Formato de export no soportado');
        }

        exit;
    }

    /* ---------- Export helpers ---------- */

    private function output_csv($rows) {
        if (headers_sent()) { wp_die('Headers already sent'); }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=noticias_reporte_'.date('Ymd_His').'.csv');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID','Título','Fecha','Extracto','URL imagen','Enlace','Categorías']);
        foreach ($rows as $r) {
            fputcsv($out, [$r['ID'],$r['title'],$r['date'],$r['excerpt'],$r['thumbnail'],$r['permalink'],$r['categories']]);
        }
        fclose($out);
        exit;
    }

    private function output_word($rows) {
        if (headers_sent()) { wp_die('Headers already sent'); }
        header('Content-Type: application/msword; charset=utf-8');
        header('Content-Disposition: attachment; filename=noticias_reporte_'.date('Ymd_His').'.doc');

        echo '<html><meta http-equiv="Content-Type" content="text/html; charset=utf-8"><body>';
        echo '<h1>Reporte de noticias - última semana</h1>';
        echo '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse:collapse;">';
        echo '<tr><th>ID</th><th>Título</th><th>Fecha</th><th>Extracto</th><th>Imagen</th><th>Categorías</th></tr>';
        foreach ($rows as $r) {
            $imgHtml = $r['thumbnail'] ? '<img src="'.esc_url($r['thumbnail']).'" style="max-width:120px;">' : '';
            echo '<tr>';
            echo '<td>'.esc_html($r['ID']).'</td>';
            echo '<td>'.esc_html($r['title']).'</td>';
            echo '<td>'.esc_html($r['date']).'</td>';
            echo '<td>'.esc_html($r['excerpt']).'</td>';
            echo '<td>'.$imgHtml.'</td>';
            echo '<td>'.esc_html($r['categories']).'</td>';
            echo '</tr>';
        }
        echo '</table></body></html>';
        exit;
    }

}

new NR_Plugin();