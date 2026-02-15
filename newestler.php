<?php
/*
Plugin Name: Newestler
Description: Generador automático de ediciones/boletines desde WordPress (skeleton inicial).
Version: 0.1.0
Author: Constanza leiva
Text Domain: newestler
Domain Path: /languages
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/** Constantes útiles */
define( 'NEWESTLER_VERSION', '0.1.0' );
define( 'NEWESTLER_DIR', plugin_dir_path( __FILE__ ) );
define( 'NEWESTLER_URL', plugin_dir_url( __FILE__ ) );

/** Includes mínimos */
if ( file_exists( NEWESTLER_DIR . 'includes/helpers.php' ) ) {
    require_once NEWESTLER_DIR . 'includes/helpers.php';
}
if ( file_exists( NEWESTLER_DIR . 'public/class-newestler-public.php' ) ) {
    require_once NEWESTLER_DIR . 'public/class-newestler-public.php';
}

/** Inicia la parte pública (shortcode, assets) */
function newestler_init_public() {
    if ( class_exists( 'Newestler_Public' ) ) {
        Newestler_Public::instance()->init();
    }
}
add_action( 'plugins_loaded', 'newestler_init_public' );
?>