<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;
$like = '%newstler_%';
$wpdb->query( $wpdb->prepare(
    "DELETE FROM $wpdb->options WHERE option_name LIKE %s OR option_name LIKE %s",
    $like, '_transient_' . $like
) );
?>