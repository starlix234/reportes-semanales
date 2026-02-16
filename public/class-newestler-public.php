<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Newestler_Public {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init() {
        // Registrar shortcode
        add_shortcode( 'newestler_boletin', array( $this, 'render_shortcode' ) );

        // Registrar assets
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
    }

    public function register_assets() {
        // Tipografías y estilos públicos
        wp_register_style( 'newestler-google-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=Merriweather:wght@700&display=swap', array(), null );
        wp_register_style( 'newestler-public', NEWESTLER_URL . 'assets/css/public.css', array('newestler-google-fonts'), NEWESTLER_VERSION );
        wp_register_script( 'newestler-public', NEWESTLER_URL . 'assets/js/public.js', array( 'jquery' ), NEWESTLER_VERSION, true );
    }

    /**
     * Render del shortcode [newestler_boletin]
     * Parámetros:
     *  - categoria: slug o ID (opcional)
     *  - start_date, end_date: YYYY-MM-DD (opcionales)
     *  - limit: número de posts (opcionales)
     */
    public function render_shortcode( $atts = array() ) {
        $atts = shortcode_atts( array(
            'categoria'  => '',
            'start_date' => '',
            'end_date'   => '',
            'limit'      => 0, // 0 = todos
        ), $atts, 'newestler_boletin' );

        // Encolamos assets (solo cuando se renderiza el shortcode)
        if ( ! wp_style_is( 'newestler-public', 'enqueued' ) ) {
            wp_enqueue_style( 'newestler-public' );
        }
        if ( ! wp_script_is( 'newestler-public', 'enqueued' ) ) {
            wp_enqueue_script( 'newestler-public' );
        }

        // ==== 1) Rango de fechas: si no vienen, usamos la semana pasada (lunes->domingo)
        if ( empty( $atts['start_date'] ) || empty( $atts['end_date'] ) ) {
            if ( function_exists( 'newestler_get_last_week_range' ) ) {
                $range = newestler_get_last_week_range();
                $start_date = $range['start'];
                $end_date   = $range['end'];
            } else {
                // fallback seguro: últimos 7 días
                $start_date = date( 'Y-m-d H:i:s', strtotime( '-7 days', current_time( 'timestamp' ) ) );
                $end_date   = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) );
            }
        } else {
            $start_date = sanitize_text_field( $atts['start_date'] ) . ' 00:00:00';
            $end_date   = sanitize_text_field( $atts['end_date'] ) . ' 23:59:59';
        }

        // ==== 2) Construimos WP_Query para traer posts del periodo
        $posts_per_page = (int) $atts['limit'] > 0 ? (int) $atts['limit'] : -1;

        $args = array(
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => $posts_per_page,
            'date_query'     => array(
                array(
                    'after'     => $start_date,
                    'before'    => $end_date,
                    'inclusive' => true,
                ),
            ),
            'orderby' => 'date',
            'order'   => 'DESC',
        );

        if ( ! empty( $atts['categoria'] ) ) {
            $cat = $atts['categoria'];
            if ( is_numeric( $cat ) ) {
                $args['category__in'] = array( intval( $cat ) );
            } else {
                $args['category_name'] = sanitize_text_field( $cat );
            }
        }

        $query = new WP_Query( $args );

        // ==== 3) Renderizamos todos los posts como artículos tipo "hero"
        ob_start();
        ?>
        <div class="newestler-boletin">

            <header class="nw-header">
                <h2 class="nw-edition-title">Boletín semanal</h2>
                <div class="nw-periodo">
                    <?php echo esc_html( date_i18n( 'd M Y', strtotime( $start_date ) ) ); ?>
                    &nbsp;—&nbsp;
                    <?php echo esc_html( date_i18n( 'd M Y', strtotime( $end_date ) ) ); ?>
                    <?php if ( ! empty( $atts['categoria'] ) ) : ?>
                        <div class="nw-subcategoria small">Categoría: <?php echo esc_html( $atts['categoria'] ); ?></div>
                    <?php endif; ?>
                </div>
            </header>

            <?php if ( $query->have_posts() ) : ?>

                <div class="newestler-grid">
                    <div class="newestler-main">

                        <?php
                        // Recorremos posts; TODOS se muestran con estructura hero-like
                        while ( $query->have_posts() ) : $query->the_post();
                            ?>
                            <article class="nw-hero" style="margin-bottom:28px;">
                                <?php if ( has_post_thumbnail() ) : ?>
                                    <div class="nw-hero-image">
                                        <a href="<?php the_permalink(); ?>">
                                            <?php the_post_thumbnail( 'large' ); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <h2 class="nw-hero-title" style="font-size:22px;margin-top:12px;">
                                    <a href="<?php the_permalink(); ?>" style="color:inherit;text-decoration:none;"><?php the_title(); ?></a>
                                </h2>

                                <div class="nw-meta small" style="margin-bottom:10px;">
                                    <?php echo esc_html( get_the_date() ); ?> &nbsp;—&nbsp; <?php the_author(); ?>
                                </div>

                                <div class="nw-hero-excerpt">
                                    <?php
                                        // Mostrar excerpt si existe; si no, un recorte del contenido.
                                        $excerpt = get_the_excerpt();
                                        if ( empty( $excerpt ) ) {
                                            $content = wp_strip_all_tags( get_the_content() );
                                            echo wp_kses_post( wp_trim_words( $content, 60, '...' ) );
                                        } else {
                                            echo wp_kses_post( wp_trim_words( $excerpt, 60, '...' ) );
                                        }
                                    ?>
                                </div>

                                <a class="nw-readmore-btn" href="<?php the_permalink(); ?>">
                                    Continuar leyendo
                                </a>
                            </article>
                            <?php
                        endwhile;
                        ?>

                    </div> <!-- .newestler-main -->

                    <aside class="newestler-sidebar">
                        <div class="nw-panel">
                            <h4>Ediciones guardadas</h4>
                            <p class="small text-muted">Accede a ediciones anteriores desde el panel de administración.</p>
                            <!-- Aquí vendrán dinámicamente las ediciones guardadas (más adelante) -->
                            <div class="nw-edition">
                                <div class="ed-left">Ejemplo — Sem. anterior</div>
                                <div class="ed-actions">
                                    <a class="small" href="#">PDF</a>
                                    <a class="small" href="#">DOCX</a>
                                </div>
                            </div>
                        </div>
                    </aside>
                </div> <!-- .newestler-grid -->

            <?php else : ?>

                <div class="nw-empty">
                    <p>No hay noticias para este periodo y categoría.</p>
                </div>

            <?php endif; ?>

            <?php wp_reset_postdata(); ?>

            <div class="newestler-footer">
                <small class="text-muted">Generado automáticamente · Newestler</small>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

} // end class
?>