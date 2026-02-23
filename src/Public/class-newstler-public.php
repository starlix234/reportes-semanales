<?php
namespace Newstler\Public;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Newstler_Public {
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
            self::$instance->init();
        }
        return self::$instance;
    }

    public function init() {
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ), 20 );
        add_action( 'init', array( $this, 'register_block_assets' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_shortcode( 'newstler_boletin', array( $this, 'render_shortcode' ) );
    }
    
    public function enqueue_frontend_assets() {
        // Evitar cargar en admin, REST, AJAX
        if ( is_admin() || ( defined('REST_REQUEST') && REST_REQUEST ) || ( defined('DOING_AJAX') && DOING_AJAX ) ) {
            return;
        }
        
        // Siempre enqueue los estilos en el frontend
        wp_enqueue_style( 'newstler-public-css' );
        wp_enqueue_script( 'newstler-public-js' );
    }

    public function register_assets() {
        wp_register_style(
            'newstler-public-css',
            NEWSTLER_PLUGIN_URL . 'assets/css/public.css',
            array(),
            NEWSTLER_VERSION
        );

        wp_register_script(
            'newstler-public-js',
            NEWSTLER_PLUGIN_URL . 'assets/js/public.js',
            array( 'jquery' ),
            NEWSTLER_VERSION,
            true
        );

        // Obtener categorías en PHP (sólo las públicas y ordenadas por nombre)
        $terms = get_terms( array(
            'taxonomy'   => 'category',
            'hide_empty' => false, // cambiar a true si sólo quieres categorías con posts
            'orderby'    => 'name',
            'order'      => 'ASC',
        ) );

        $categories_for_js = array();
        if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
            foreach ( $terms as $t ) {
                $categories_for_js[] = array(
                    'id'    => (int) $t->term_id,
                    'name'  => (string) $t->name,
                    'slug'  => (string) $t->slug,
                    'count' => (int) $t->count,
                );
            }
        }

        // Localizamos las URLs, nonce y además pasamos las categorías para que JS no tenga
        // que hacer la petición REST por su cuenta
        wp_localize_script( 'newstler-public-js', 'newstlerPublic', array(
            'restUrl'      => esc_url_raw( rest_url( 'newstler/v1/news' ) ),
            'reportUrl'    => esc_url_raw( rest_url( 'newstler/v1/report' ) ),
            'categories'   => $categories_for_js,
            'nonce'        => wp_create_nonce( 'wp_rest' ),
        ) );
    }

    public function register_block_assets() {
        // Registrar script del bloque - usamos la ruta de src/Blocks/block.js
        wp_register_script(
            'newstler-block-js',
            NEWSTLER_PLUGIN_URL . 'src/Blocks/block.js',
            array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-editor', 'wp-i18n' ),
            NEWSTLER_VERSION,
            true
        );

        wp_register_style(
            'newstler-block-editor-css',
            NEWSTLER_PLUGIN_URL . 'src/Blocks/block-editor.scss',
            array(),
            NEWSTLER_VERSION
        );

        wp_register_style(
            'newstler-block-public-css',
            NEWSTLER_PLUGIN_URL . 'assets/css/public.css',
            array(),
            NEWSTLER_VERSION
        );

        if ( function_exists( 'register_block_type' ) ) {
            register_block_type( 'newstler/boletin', array(
                'editor_script' => 'newstler-block-js',
                'editor_style'  => 'newstler-block-editor-css',
                'style'         => 'newstler-block-public-css',
                'render_callback' => array( $this, 'render_shortcode' ),
            ) );
        }
    }

    public function register_rest_routes() {
        register_rest_route( 'newstler/v1', '/news', array(
            'methods'  => 'GET',
            'callback' => array( $this, 'rest_get_news' ),
            // Mantener permiso por defecto (requiere edit_posts). Cambia a '__return_true' solo si quieres público.
            'permission_callback' => function () {
                return current_user_can( 'edit_posts' );
            },
            'args' => $this->rest_news_args(),
        ) );

        register_rest_route( 'newstler/v1', '/report', array(
            'methods' => 'GET',
            'callback' => array( $this, 'rest_get_report' ),
            'permission_callback' => function () {
                return current_user_can( 'edit_posts' );
            },
            'args' => $this->rest_news_args(),
        ) );
    }

    private function rest_news_args() {
        return array(
            'categories' => array(
                'required' => false,
                'validate_callback' => function ( $param ) {
                    return is_string( $param ) || is_array( $param );
                }
            ),
            'date_start' => array(
                'required' => false,
                'validate_callback' => function ( $param ) {
                    return empty( $param ) || strtotime( $param ) !== false;
                }
            ),
            'date_end' => array(
                'required' => false,
                'validate_callback' => function ( $param ) {
                    return empty( $param ) || strtotime( $param ) !== false;
                }
            ),
            'per_page' => array(
                'required' => false,
                'default' => 50,
                'validate_callback' => function ( $param ) {
                    return is_numeric( $param ) && $param > 0 && $param <= 200;
                }
            ),
        );
    }

    public function rest_get_news( \WP_REST_Request $request ) {
        $args = $this->build_query_args( $request );

        $q = new \WP_Query( $args );

        $items = array();
        if ( $q->have_posts() ) {
            while ( $q->have_posts() ) {
                $q->the_post();
                $items[] = array(
                    'id' => get_the_ID(),
                    'title' => get_the_title(),
                    'link'  => get_permalink(),
                    'excerpt' => get_the_excerpt(),
                    'date' => get_the_date( 'c' ),
                    'author' => get_the_author_meta( 'display_name', get_post_field( 'post_author', get_the_ID() ) ),
                    'thumbnail' => get_the_post_thumbnail_url( get_the_ID(), 'thumbnail' )
                );
            }
            wp_reset_postdata();
        }

        return rest_ensure_response( array(
            'total' => (int) $q->found_posts,
            'items' => $items,
        ) );
    }

    public function rest_get_report( \WP_REST_Request $request ) {
        $args = $this->build_query_args( $request );

        $q = new \WP_Query( $args );

        if ( class_exists( '\Newstler\Reports\Report_Generator' ) ) {
            $generator = new \Newstler\Reports\Report_Generator();
            $html = $generator->generate_from_query( $q, $request->get_param( 'date_start' ), $request->get_param( 'date_end' ), $request->get_param( 'categories' ) );
        } else {
            $html = '<h1>Report</h1><p>Report generator missing.</p>';
        }

        $download = $request->get_param( 'download' );
        if ( $download ) {
            // Forzar descarga
            header( 'Content-Description: File Transfer' );
            header( 'Content-Type: text/html; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename="boletin-report.html"' );
            echo $html;
            exit;
        }

        return rest_ensure_response( array( 'html' => $html ) );
    }

    private function build_query_args( \WP_REST_Request $request ) {
        $categories = $request->get_param( 'categories' );
        $date_start = $request->get_param( 'date_start' );
        $date_end   = $request->get_param( 'date_end' );
        $per_page   = (int) $request->get_param( 'per_page' ) ?: 50;

        $args = array(
            'post_type' => 'post',
            'posts_per_page' => $per_page,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
        );

        if ( ! empty( $categories ) ) {
            if ( is_string( $categories ) ) {
                $categories = array_filter( array_map( 'intval', explode( ',', $categories ) ) );
            } elseif ( is_array( $categories ) ) {
                $categories = array_map( 'intval', $categories );
            }

            if ( ! empty( $categories ) ) {
                $args['tax_query'] = array(
                    array(
                        'taxonomy' => 'category',
                        'field' => 'term_id',
                        'terms' => $categories,
                    )
                );
            }
        }

        $date_query = array();
        if ( ! empty( $date_start ) ) {
            $date_query['after'] = date( 'Y-m-d 00:00:00', strtotime( $date_start ) );
        }
        if ( ! empty( $date_end ) ) {
            $date_query['before'] = date( 'Y-m-d 23:59:59', strtotime( $date_end ) );
        }
        if ( ! empty( $date_query ) ) {
            $args['date_query'] = array( $date_query );
        }

        return $args;
    }

    public function render_shortcode( $atts = array() ) {
        // Los estilos ya se cargan en enqueue_frontend_assets()
        $html = '<div id="newstler-boletin-root" class="newstler-boletin">';
        $html .= '<!-- Newstler: UI será inyectada por JS -->';
        $html .= '</div>';

        return $html;
    }
}
?>