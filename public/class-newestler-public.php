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

        // Registrar assets (no los encolamos todavía)
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
    }

    public function register_assets() {
        wp_register_style( 'newestler-public', NEWESTLER_URL . 'assets/css/public.css', array(), NEWESTLER_VERSION );
        wp_register_script( 'newestler-public', NEWESTLER_URL . 'assets/js/public.js', array( 'jquery' ), NEWESTLER_VERSION, true );
    }

    /**
     * Render del shortcode [newestler_boletin]
     * Aún solo placeholder; luego lo reemplazaremos por la vista real.
     */
    public function render_shortcode( $atts = array() ) {
    $atts = shortcode_atts( array(
        'categoria'  => '',
        'start_date' => '',
        'end_date'   => '',
    ), $atts, 'newestler_boletin' );

    // Encolamos assets si usamos el shortcode
    wp_enqueue_style( 'newestler-public' );
    wp_enqueue_script( 'newestler-public' );

    // ==== 1) Calculamos rango de fechas: si no vienen, usamos la semana pasada (lunes->domingo)
    if ( empty( $atts['start_date'] ) || empty( $atts['end_date'] ) ) {
        // usa helper que devuelve ['start' => 'YYYY-MM-DD HH:MM:SS', 'end' => 'YYYY-MM-DD HH:MM:SS']
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
        // Si el usuario pasa fechas por shortcode: asumimos YYYY-MM-DD
        $start_date = sanitize_text_field( $atts['start_date'] ) . ' 00:00:00';
        $end_date   = sanitize_text_field( $atts['end_date'] ) . ' 23:59:59';
    }

    // ==== 2) Construimos WP_Query
    $args = array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => 100, // límite razonable; ajusta si quieres todos (-1)
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

    // Filtrar por categoría: aceptamos slug (category_name) o ID numérico (category__in)
    if ( ! empty( $atts['categoria'] ) ) {
        $cat = $atts['categoria'];
        if ( is_numeric( $cat ) ) {
            $args['category__in'] = array( intval( $cat ) );
        } else {
            $args['category_name'] = sanitize_text_field( $cat );
        }
    }

    $query = new WP_Query( $args );

    // ==== 3) Renderizamos la vista (preview)
    ob_start();
    ?>
    <div class="newestler-boletin">
        <h3>Edición Newestler</h3>

        <p><strong>Periodo:</strong>
            <?php echo esc_html( date_i18n( 'Y-m-d H:i', strtotime( $start_date ) ) ); ?>
            &nbsp;→&nbsp;
            <?php echo esc_html( date_i18n( 'Y-m-d H:i', strtotime( $end_date ) ) ); ?>
        </p>

        <?php if ( $query->have_posts() ) : ?>
            <ul class="newestler-list" style="list-style:none;padding:0;">
                <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                    <li class="newestler-item" style="margin-bottom:1.25rem;padding-bottom:0.5rem;border-bottom:1px solid #eee;">
                        <a href="<?php the_permalink(); ?>" style="text-decoration:none;color:inherit;">
                            <strong><?php the_title(); ?></strong>
                        </a>
                        <br>
                        <small><?php echo esc_html( get_the_date() ); ?> — <?php echo esc_html( get_the_author() ); ?></small>

                        <?php if ( has_post_thumbnail() ) : ?>
                            <div class="newestler-thumb" style="margin-top:0.5rem;">
                                <a href="<?php the_permalink(); ?>"><?php the_post_thumbnail( array( 150, 150 ) ); ?></a>
                            </div>
                        <?php endif; ?>

                        <p style="margin-top:0.5rem;"><?php echo wp_kses_post( wp_trim_words( get_the_excerpt(), 24 ) ); ?></p>
                    </li>
                <?php endwhile; ?>
            </ul>
        <?php else : ?>
            <p>No hay noticias para este periodo y categoría.</p>
        <?php endif; ?>
    </div>
    <?php
    wp_reset_postdata();

    return ob_get_clean();
}

}
?>