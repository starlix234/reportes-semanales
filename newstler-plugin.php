<?php
/**
 * Plugin Name: Newstler - Boletín Semanal
 * Description: Trae entradas filtradas por categoría y rango de fechas, muestra preview y permite generar un reporte HTML para descarga.
 * Version:     0.1.0
 * Author:      constanza 
 * Text Domain: newstler
 * Domain Path: /languages
 *
 * @package Newstler
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'NEWSTLER_VERSION', '0.1.0' );
define( 'NEWSTLER_PLUGIN_FILE', __FILE__ );
define( 'NEWSTLER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'NEWSTLER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Autoload flexible: soporta dos convenciones de nombre de archivo:
 * - src/.../Newstler_ClassName.php  (PSR-like)
 * - src/.../class-newstler-class-name.php (kebab/class- prefixed)
 *
 * Las clases deben usar namespace Newstler\Xxx y clase final Newstler_Xxx (opcional).
 */
spl_autoload_register( function( $class ) {
    // Solo nos interesan clases bajo 'Newstler\'
    $prefix = 'Newstler\\';
    if ( 0 !== strpos( $class, $prefix ) ) {
        return;
    }

    // Obtengo la parte después del namespace
    $relative = substr( $class, strlen( $prefix ) );
    // Convertir namespace separators a path
    $parts = explode( '\\', $relative );

    // Construyo posibles file paths
    // 1) PSR-ish: NEWSTLER_PLUGIN_DIR . 'src/' . 'Public/Newstler_Public.php'
    $psr_file = NEWSTLER_PLUGIN_DIR . 'src/' . implode( DIRECTORY_SEPARATOR, $parts ) . '.php';

    // 2) kebab prefixed con class- : produce 'src/Public/class-newstler-public.php'
    $kebab_parts = array_map( function( $p ) {
        // Convertir CamelCase / PascalCase a kebab-case (naive)
        $k = preg_replace( '/([a-z])([A-Z])/', '$1-$2', $p );
        $k = str_replace( '_', '-', $k );
        return strtolower( $k );
    }, $parts );

    $kebab_file = NEWSTLER_PLUGIN_DIR . 'src/' . implode( DIRECTORY_SEPARATOR, $kebab_parts );
    $kebab_file = dirname( $kebab_file ) . DIRECTORY_SEPARATOR . 'class-' . basename( $kebab_file ) . '.php';

    if ( file_exists( $psr_file ) ) {
        require_once $psr_file;
    } elseif ( file_exists( $kebab_file ) ) {
        require_once $kebab_file;
    }
} );

/**
 * Inicialización
 */
function newstler_init_plugin() {
    load_plugin_textdomain( 'newstler', false, dirname( plugin_basename( NEWSTLER_PLUGIN_FILE ) ) . '/languages' );

    // Instanciar clases si existen (la autoload las cargará según convención de archivo)
    if ( class_exists( 'Newstler\\Public\\Newstler_Public' ) ) {
        Newstler\Public\Newstler_Public::instance();
    } elseif ( class_exists( 'Newstler\\Public\\NewstlerPublic' ) ) {
        // fallback si alguien usó otro nombre de clase
    }

    if ( class_exists( 'Newstler\\Admin\\Newstler_Admin' ) ) {
        Newstler\Admin\Newstler_Admin::instance();
    }
}
add_action( 'plugins_loaded', 'newstler_init_plugin' );

/**
 * Encolar FontAwesome (preferir local, evitar en admin/editor/REST/AJAX).
 *
 * Recomendación:
 * - Descarga Font Awesome (free) y coloca:
 *   assets/vendor/fontawesome/css/all.min.css
 *   assets/vendor/fontawesome/webfonts/*
 *
 * Si no existe local, se usa CDN como fallback.
 */
function boletin_front_assets() {

    // evitar admin / REST / AJAX
    if ( is_admin() || ( defined('REST_REQUEST') && REST_REQUEST ) || ( defined('DOING_AJAX') && DOING_AJAX ) ) {
        return;
    }

    /**
     * RUTA REAL DE TU PROYECTO
     * assets/fontawesome/css/all.min.css
     */
    $local_css = NEWSTLER_PLUGIN_DIR . 'assets/fontawesome/css/all.min.css';
    $local_url = NEWSTLER_PLUGIN_URL . 'assets/fontawesome/css/all.min.css';

    if ( file_exists( $local_css ) ) {

        wp_enqueue_style(
            'newstler-fontawesome',
            $local_url,
            array(),
            NEWSTLER_VERSION
        );

    } else {

        // fallback por si borras la carpeta
        wp_enqueue_style(
            'newstler-fontawesome-cdn',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css',
            array(),
            '5.15.4'
        );
    }
}
add_action('wp_enqueue_scripts', 'boletin_front_assets', 20);

/**
 * Maneja la exportación POST para generar un archivo HTML descargable con la selección.
 * Se engancha en init para capturar el POST antes de que WordPress renderice la página.
 */
function newstler_handle_export_html() {
    if ( empty( $_POST ) ) {
        return;
    }

    if ( ! empty( $_POST['newstler_export_html'] ) ) {
        // Verificar nonce si viene
        if ( empty( $_POST['_wpnonce_newstler_export'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce_newstler_export'] ) ), 'newstler_export' ) ) {
            wp_die( 'Nonce no válido.' );
        }

        // Esperamos arrays prefixados con índices iguales: title[], url[], excerpt[], image[] y select[] con índices seleccionados
        $titles   = isset( $_POST['title'] ) ? (array) $_POST['title'] : array();
        $urls     = isset( $_POST['url'] ) ? (array) $_POST['url'] : array();
        $excerpts = isset( $_POST['excerpt'] ) ? (array) $_POST['excerpt'] : array();
        $images   = isset( $_POST['image'] ) ? (array) $_POST['image'] : array();
        $selected = isset( $_POST['select'] ) ? (array) $_POST['select'] : array();

        // Construir documento HTML con encabezado simple y estilos inline (puedes ajustar plantilla)
        $date = gmdate( 'Y-m-d' );
        $filename = sprintf( 'boletin-%s.html', $date );

        $html = '<!doctype html>\n<html lang="es">\n<head>\n<meta charset="utf-8">\n<meta name="viewport" content="width=device-width,initial-scale=1">\n<title>Boletín ' . esc_html( $date ) . '</title>\n';
        $html .= '<style>body{font-family:Arial,Helvetica,sans-serif;color:#222;background:#fff;margin:0;padding:20px} .article{margin-bottom:24px;padding:12px;border-bottom:1px solid #eee} .article h2{margin:0 0 8px 0;color:#004d40} .article img{max-width:100%;height:auto;border-radius:6px;display:block;margin-bottom:8px} .excerpt{color:#333}</style>\n';
        $html .= '</head>\n<body>\n<header><h1>Boletín ' . esc_html( $date ) . '</h1></header>\n<main>\n';

        // Recorrer índices seleccionados y añadir contenido
        foreach ( $selected as $idx ) {
            $i = intval( $idx );
            $t = isset( $titles[ $i ] ) ? wp_kses_post( wp_unslash( $titles[ $i ] ) ) : '';
            $u = isset( $urls[ $i ] ) ? esc_url_raw( wp_unslash( $urls[ $i ] ) ) : '';
            $e = isset( $excerpts[ $i ] ) ? wp_kses_post( wp_unslash( $excerpts[ $i ] ) ) : '';
            $im = isset( $images[ $i ] ) ? esc_url_raw( wp_unslash( $images[ $i ] ) ) : '';

            $html .= '<article class="article">\n';
            if ( $im ) {
                $html .= '<a href="' . esc_url( $u ) . '"><img src="' . esc_url( $im ) . '" alt="' . esc_attr( $t ) . '"></a>\n';
            }
            $html .= '<h2><a href="' . esc_url( $u ) . '">' . $t . '</a></h2>\n';
            $html .= '<div class="excerpt">' . $e . '</div>\n';
            $html .= '</article>\n';
        }

        $html .= '</main>\n<footer><p>Generado por Newstler</p></footer>\n</body>\n</html>';

        // Forzar descarga
        header( 'Content-Description: File Transfer' );
        header( 'Content-Type: text/html; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Expires: 0' );
        header( 'Cache-Control: must-revalidate' );
        header( 'Pragma: public' );
        header( 'Content-Length: ' . strlen( $html ) );

        echo $html;
        exit;
    }
}
add_action( 'init', 'newstler_handle_export_html' );

/**
 * Shortcode simple [newstler_boletin] que muestra posts con checkboxes y botón para exportar HTML.
 */
function newstler_boletin_shortcode( $atts ) {
    $atts = shortcode_atts( array( 'posts' => 20 ), $atts, 'newstler_boletin' );

    $query = new WP_Query( array( 'post_type' => 'post', 'posts_per_page' => intval( $atts['posts'] ) ) );

    ob_start();
    ?>
    <form method="post" action="" id="newstler-export-form">
        <?php wp_nonce_field( 'newstler_export', '_wpnonce_newstler_export' ); ?>
        <input type="hidden" name="newstler_export_html" value="1" />
        <div class="newstler-list">
            <?php $i = 0; while ( $query->have_posts() ) : $query->the_post();
                $title = get_the_title();
                $url = get_permalink();
                $excerpt = wp_trim_words( get_the_excerpt() ?: get_the_content(), 40 );
                $thumb = get_the_post_thumbnail_url( get_the_ID(), 'large' );
            ?>
            <div class="newstler-item" style="display:flex;gap:12px;align-items:flex-start;margin-bottom:10px;">
                <div style="width:36px"><label><input type="checkbox" name="select[]" value="<?php echo esc_attr( $i ); ?>"></label></div>
                <div style="flex:1">
                    <h3 style="margin:0;font-size:1.05rem;"><?php echo esc_html( $title ); ?></h3>
                    <p style="margin:6px 0;color:#444"><?php echo wp_kses_post( wp_trim_words( $excerpt, 30 ) ); ?></p>
                    <a href="<?php echo esc_url( $url ); ?>" target="_blank">Ver</a>
                </div>
                <div style="width:140px">
                    <?php if ( $thumb ) : ?>
                        <img src="<?php echo esc_url( $thumb ); ?>" alt="" style="width:100%;height:auto;border-radius:6px" />
                    <?php endif; ?>
                </div>
                <?php // Hidden fields with data indexed by $i ?>
                <input type="hidden" name="title[<?php echo esc_attr( $i ); ?>]" value="<?php echo esc_attr( $title ); ?>" />
                <input type="hidden" name="url[<?php echo esc_attr( $i ); ?>]" value="<?php echo esc_url( $url ); ?>" />
                <input type="hidden" name="excerpt[<?php echo esc_attr( $i ); ?>]" value="<?php echo esc_attr( wp_strip_all_tags( $excerpt ) ); ?>" />
                <input type="hidden" name="image[<?php echo esc_attr( $i ); ?>]" value="<?php echo esc_url( $thumb ); ?>" />
            </div>
            <?php $i++; endwhile; wp_reset_postdata(); ?>
        </div>

        <div style="display:flex;gap:10px;align-items:center;margin-top:12px">
            <button type="submit" class="button button-primary">Descargar HTML del boletín</button>
            <span id="newstler-selected-count" style="margin-left:12px;display:none;font-weight:bold;color:#004d40">Seleccionados: <span id="newstler-count">0</span></span>
        </div>
    </form>

    <script>
        (function(){
            const form = document.getElementById('newstler-export-form');
            if(!form) return;
            const checkboxes = form.querySelectorAll('input[type="checkbox"][name="select[]"]');
            const info = document.getElementById('newstler-selected-count');
            const countEl = document.getElementById('newstler-count');
            function refresh(){
                const checked = Array.from(checkboxes).filter(c=>c.checked).length;
                if(checked>0){ info.style.display='inline'; countEl.textContent = checked; } else { info.style.display='none'; countEl.textContent = 0; }
            }
            checkboxes.forEach(cb=>cb.addEventListener('change', refresh));
            refresh();
        })();
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode( 'newstler_boletin', 'newstler_boletin_shortcode' );