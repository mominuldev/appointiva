<?php
/**
 * Runs once on plugin activation.
 *
 * @package Appointiva
 */

namespace Appointiva;

use Appointiva\Database\Installer;
use Appointiva\Database\Settings_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Activator {

	public static function activate(): void {
		Installer::install();
		self::seed_default_location();

		if ( ! wp_next_scheduled( 'appointiva_send_reminder_notifications' ) ) {
			wp_schedule_event( time(), 'hourly', 'appointiva_send_reminder_notifications' );
		}

		/**
		 * Fires after Appointiva has finished its own activation routine
		 * (tables created, default location seeded, cron scheduled).
		 */
		do_action( 'appointiva_activated' );

		set_transient( 'appointiva_activation_redirect', true, 30 );
	}

	private static function seed_default_location(): void {
		global $wpdb;
		$table = Installer::table_names()['locations'];

		$existing = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( $existing ) {
			return;
		}

		$wpdb->insert(
			$table,
			array(
				'name'       => get_bloginfo( 'name' ) ?: __( 'Main Location', 'appointiva' ),
				'timezone'   => wp_timezone_string(),
				'is_default' => 1,
				'status'     => 'active',
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s' )
		);
	}
}
