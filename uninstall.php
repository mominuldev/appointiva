<?php
/**
 * Uninstall handler.
 *
 * Runs only when the plugin is deleted from Plugins screen (WP_UNINSTALL_PLUGIN
 * is defined by core). By default all booking data, settings, and custom
 * tables are KEPT — this mirrors the WooCommerce/Yoast/WPForms convention.
 * Destructive cleanup only happens if the site owner explicitly opted in via
 * the "Delete all data on uninstall" setting.
 *
 * @package Appointiva
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Opt-in flag, set from Settings > Advanced. Absent/false means "keep data".
$appointiva_delete_data = get_option( 'appointiva_delete_data_on_uninstall', false );

if ( ! $appointiva_delete_data ) {
	return;
}

global $wpdb;

$appointiva_tables = array(
	$wpdb->prefix . 'appointiva_bookings',
	$wpdb->prefix . 'appointiva_services',
	$wpdb->prefix . 'appointiva_staff',
	$wpdb->prefix . 'appointiva_availability',
	$wpdb->prefix . 'appointiva_customers',
	$wpdb->prefix . 'appointiva_payments',
	$wpdb->prefix . 'appointiva_notifications',
	$wpdb->prefix . 'appointiva_locations',
	$wpdb->prefix . 'appointiva_settings',
);

foreach ( $appointiva_tables as $appointiva_table ) {
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $appointiva_table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
}

$appointiva_options = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( 'appointiva_' ) . '%'
	)
);

foreach ( $appointiva_options as $appointiva_option ) {
	delete_option( $appointiva_option );
}

// Auto-created pages (e.g. booking-response landing page), identified by post meta.
$appointiva_page_ids = get_posts(
	array(
		'post_type'      => 'page',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_key'       => '_appointiva_auto_page', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'post_status'    => 'any',
	)
);

foreach ( $appointiva_page_ids as $appointiva_page_id ) {
	wp_delete_post( $appointiva_page_id, true );
}

wp_clear_scheduled_hook( 'appointiva_send_reminder_notifications' );
wp_clear_scheduled_hook( 'appointiva_google_calendar_sync' );
