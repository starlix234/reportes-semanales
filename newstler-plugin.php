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
    // Encolar el CSS principal de reportes si existe
    $reports_css = NEWSTLER_PLUGIN_DIR . 'assets/css/public.css';
    if ( file_exists( $reports_css ) ) {
        wp_enqueue_style( 'newstler-public-css', NEWSTLER_PLUGIN_URL . 'assets/css/public.css', array(), NEWSTLER_VERSION );
    }
}
add_action('wp_enqueue_scripts', 'boletin_front_assets', 20);

/**
 * Maneja la exportación POST para generar un archivo HTML descargable con la selección.
 * Se engancha en init para capturar el POST antes de que WordPress renderice la página.
 */
/**
 * Genera la URL de descarga vía admin-ajax con nonce.
 * Acepta un array de índices seleccionados opcional.
 * Devuelve un enlace absoluto listo para usar en <a href="">
 */
function newstler_generate_download_link( $selected = array() ) {
    $nonce = wp_create_nonce( 'newstler_download' );
    $base = admin_url( 'admin-ajax.php' );
    $args = array( 'action' => 'newstler_download', 'nonce' => $nonce );
    if ( ! empty( $selected ) && is_array( $selected ) ) {
        // serializar selección en base64 para GET
        $args['sel'] = base64_encode( wp_json_encode( array_values( $selected ) ) );
    }
    return add_query_arg( $args, $base );
}


/**
 * Handler AJAX (logged and not logged) que crea un archivo HTML temporal y lo sirve con readfile().
 * Requisitos: wp_verify_nonce, headers con application/octet-stream, readfile() y exit.
 */
function newstler_ajax_download() {
    // Seguridad: nonce obligatorio
    $nonce = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
    // Solo usuarios logueados pueden descargar
    if ( ! is_user_logged_in() ) {
        wp_die( 'Debes iniciar sesión para descargar este archivo.', 403 );
    }

    if ( ! wp_verify_nonce( $nonce, 'newstler_download' ) ) {
        wp_die( 'Nonce no válido.', 403 );
    }

    // Recolectar datos: soporta POST arrays title[], url[], excerpt[], image[] y select[] o GET sel=base64(json)
    $titles   = isset( $_REQUEST['title'] ) ? (array) $_REQUEST['title'] : array();
    $urls     = isset( $_REQUEST['url'] ) ? (array) $_REQUEST['url'] : array();
    $excerpts = isset( $_REQUEST['excerpt'] ) ? (array) $_REQUEST['excerpt'] : array();
    $images   = isset( $_REQUEST['image'] ) ? (array) $_REQUEST['image'] : array();

    $selected = array();
    if ( isset( $_REQUEST['select'] ) && is_array( $_REQUEST['select'] ) ) {
        $selected = (array) $_REQUEST['select'];
    } elseif ( isset( $_REQUEST['sel'] ) ) {
        $raw = sanitize_text_field( wp_unslash( $_REQUEST['sel'] ) );
        $decoded = json_decode( base64_decode( $raw ), true );
        if ( is_array( $decoded ) ) {
            $selected = $decoded;
        }
    }

    // Si no hay selección, devolver error simple
    if ( empty( $selected ) ) {
        wp_die( 'No hay elementos seleccionados para exportar.', 400 );
    }

    // Construir HTML
    $date = gmdate( 'Y-m-d' );
    $html = '<!doctype html>\n<html lang="es">\n<head>\n<meta charset="utf-8">\n<meta name="viewport" content="width=device-width,initial-scale=1">\n<title>Boletín ' . esc_html( $date ) . '</title>\n';
    $html .= '<style>body{font-family:Arial,Helvetica,sans-serif;color:#222;background:#fff;margin:0;padding:20px} .article{margin-bottom:24px;padding:12px;border-bottom:1px solid #eee} .article h2{margin:0 0 8px 0;color:#004d40} .article img{max-width:100%;height:auto;border-radius:6px;display:block;margin-bottom:8px} .excerpt{color:#333}</style>\n';
    $html .= '</head>\n<body>\n<header><h1>Boletín ' . esc_html( $date ) . '</h1></header>\n<main>\n';

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

    // Incluir CSS de reportes inline en el HTML exportado si existe
    $reports_css = NEWSTLER_PLUGIN_DIR . 'assets/css/public.css';
    $inline_css = '';
    if ( file_exists( $reports_css ) ) {
        $inline_css = file_get_contents( $reports_css );
    }

    // Crear archivo temporal en uploads
    $uploads = wp_upload_dir();
    $dir = trailingslashit( $uploads['basedir'] );
    $filename = sprintf( 'newstler-boletin-%s-%s.html', $date, wp_generate_uuid4() );
    $file_path = $dir . $filename;

    // Insertar CSS inline si lo tenemos
    if ( $inline_css ) {
        // añadir dentro del head antes del cierre
        $html = str_replace( '</head>', "<style>" . $inline_css . "</style></head>", $html );
    }

    // Escribir el archivo
    if ( false === file_put_contents( $file_path, $html ) ) {
        wp_die( 'Error al crear el archivo temporal.', 500 );
    }

    // Forzar descarga usando headers requeridos
    if ( headers_sent() ) {
        // No podemos enviar headers
        wp_die( 'No se pueden enviar headers, la salida ya fue empezada.', 500 );
    }

    header( 'Content-Description: File Transfer' );
    header( 'Content-Type: application/octet-stream' );
    header( 'Content-Disposition: attachment; filename="' . basename( $file_path ) . '"' );
    header( 'Expires: 0' );
    header( 'Cache-Control: must-revalidate' );
    header( 'Pragma: public' );
    header( 'Content-Length: ' . filesize( $file_path ) );

    // Leer y enviar el archivo
    readfile( $file_path );

    // Eliminar archivo temporal
    @unlink( $file_path );

    exit;
}
add_action( 'wp_ajax_newstler_download', 'newstler_ajax_download' );

/**
 * Shortcode simple [newstler_boletin] que muestra posts con checkboxes y botón para exportar HTML.
 */
function newstler_boletin_shortcode( $atts ) {
    $atts = shortcode_atts( array( 'posts' => 20 ), $atts, 'newstler_boletin' );

    $query = new WP_Query( array( 'post_type' => 'post', 'posts_per_page' => intval( $atts['posts'] ) ) );

    ob_start();
    ?>
    <?php $ajax_action_url = admin_url( 'admin-ajax.php' ); ?>
    <div class="newstler-instructions" style="background:#eef7f4;border:1px solid #d6f0e8;padding:12px;border-radius:6px;margin-bottom:12px;color:#004d40">
        <strong>Instrucciones:</strong>
        <ol style="margin:6px 0 0 18px;padding:0">
            <li>Selecciona las noticias marcando las casillas.</li>
            <li>Haz clic en "Descargar HTML del boletín" para generar y descargar el archivo.</li>
            <li>También puedes usar el enlace "Descargar todo" para bajar todas las noticias listadas.</li>
            <li>Nota: Debes <em>iniciar sesión</em> para descargar el archivo.</li>
        </ol>
    </div>
    <?php $nonce = wp_create_nonce( 'newstler_download' ); ?>
    <form method="post" action="<?php echo esc_url( add_query_arg( 'action', 'newstler_download', $ajax_action_url ) ); ?>" id="newstler-export-form">
        <input type="hidden" name="nonce" value="<?php echo esc_attr( $nonce ); ?>" />
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
            <?php if ( is_user_logged_in() ) : ?>
                <button type="submit" class="button button-primary">Descargar HTML del boletín</button>
                <?php
                    // Generar enlace para descargar todo (sin selección) - incluye nonce
                    $all_indexes = range( 0, max( 0, intval( $i - 1 ) ) );
                    $download_all_link = newstler_generate_download_link( $all_indexes );
                ?>
                <a href="<?php echo esc_url( $download_all_link ); ?>" class="button">Descargar todo (enlace)</a>
            <?php else : ?>
                <div style="color:#a00">Debes <a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">iniciar sesión</a> para descargar.</div>
            <?php endif; ?>
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