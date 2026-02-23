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