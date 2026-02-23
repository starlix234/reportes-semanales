<?php
namespace Newstler\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Newstler_Admin {
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
            self::$instance->init();
        }
        return self::$instance;
    }

    public function init() {
        // Aquí podrías añadir menús de settings más adelante.
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
    }

    public function add_admin_menu() {
        add_menu_page( 'Newstler', 'Newstler', 'manage_options', 'newstler', array( $this, 'page' ), 'dashicons-media-document' );
    }

    public function page() {
        echo '<div class="wrap"><h1>Newstler — Configuración</h1><p>Opciones del plugin.</p></div>';
    }
}
