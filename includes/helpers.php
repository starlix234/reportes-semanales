<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Helpers mínimos para Newestler.
 * Aquí iremos añadiendo utilidades (date helpers, sanitizers, etc.)
 */

if ( ! function_exists( 'newestler_format_date' ) ) {
    function newestler_format_date( $date ) {
        $d = new DateTime( $date );
        return $d->format( 'Y-m-d' );
    }
}

/**
 * Obtiene el rango de la semana pasada (lunes a domingo)
 */
function newestler_get_last_week_range() {

    // Timestamp actual del sitio (respeta timezone WP)
    $now = current_time('timestamp');

    // Día de la semana (1 = lunes, 7 = domingo)
    $day_of_week = date('N', $now);

    // Retroceder hasta el lunes de esta semana
    $monday_this_week = strtotime('-' . ($day_of_week - 1) . ' days', $now);

    // Lunes de la semana pasada
    $monday_last_week = strtotime('-7 days', $monday_this_week);

    // Domingo de la semana pasada
    $sunday_last_week = strtotime('+6 days 23 hours 59 minutes 59 seconds', $monday_last_week);

    return array(
        'start' => date('Y-m-d H:i:s', $monday_last_week),
        'end'   => date('Y-m-d H:i:s', $sunday_last_week)
    );
}


?>


