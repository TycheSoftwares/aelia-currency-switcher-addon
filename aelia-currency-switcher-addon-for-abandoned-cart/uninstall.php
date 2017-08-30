<?php
/**
 * Aelia Currency Switcher addon for Abandoned Cart Plugin Uninstall
 *
 * Uninstalling Aelia Currency Switcher addon for Abandoned Cart Plugin deletes table.
 *
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

$table_name = $wpdb->prefix . "abandoned_cart_aelia_currency";

$acfac_delete_table= "DROP TABLE " . $table_name ;
require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
$wpdb->get_results( $acfac_delete_table );
    
wp_cache_flush();