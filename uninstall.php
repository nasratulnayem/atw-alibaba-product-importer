<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;
$table = $wpdb->prefix . 'importonbridge_usage_log';
$wpdb->query( $wpdb->prepare( "DROP TABLE IF EXISTS {$table}" ) );

delete_option( 'importonbridge_ai_settings' );
