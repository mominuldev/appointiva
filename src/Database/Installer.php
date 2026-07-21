<?php
/**
 * Creates and upgrades Appointiva's custom database tables.
 *
 * @package Appointiva
 */

namespace Appointiva\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Installer {

	private const DB_VERSION_OPTION = 'appointiva_db_version';

	/**
	 * Create tables on activation, or upgrade them if the plugin was updated
	 * while deactivated (dbDelta is idempotent and safe to re-run).
	 */
	public static function install(): void {
		self::create_tables();
		update_option( self::DB_VERSION_OPTION, APPOINTIVA_DB_VERSION );
	}

	/**
	 * Runs on every boot; only touches the database if the stored schema
	 * version is behind the plugin's current version.
	 */
	public static function maybe_upgrade(): void {
		$installed = get_option( self::DB_VERSION_OPTION, '' );

		if ( $installed === APPOINTIVA_DB_VERSION ) {
			return;
		}

		self::create_tables();
		update_option( self::DB_VERSION_OPTION, APPOINTIVA_DB_VERSION );

		/**
		 * Fires after Appointiva's schema has been created or upgraded.
		 *
		 * Appointiva Pro hooks in here to run its own migrations for its own
		 * tables/columns, entirely separate from the Free plugin's schema.
		 *
		 * @param string $installed Previously installed schema version, empty string on first install.
		 * @param string $current   Schema version now installed.
		 */
		do_action( 'appointiva_db_upgraded', $installed, APPOINTIVA_DB_VERSION );
	}

	/**
	 * @return string[] Fully-prefixed table names, keyed by short name.
	 */
	public static function table_names(): array {
		global $wpdb;

		return array(
			'services'      => $wpdb->prefix . 'appointiva_services',
			'staff'         => $wpdb->prefix . 'appointiva_staff',
			'availability'  => $wpdb->prefix . 'appointiva_availability',
			'customers'     => $wpdb->prefix . 'appointiva_customers',
			'bookings'      => $wpdb->prefix . 'appointiva_bookings',
			'payments'      => $wpdb->prefix . 'appointiva_payments',
			'notifications' => $wpdb->prefix . 'appointiva_notifications',
			'locations'     => $wpdb->prefix . 'appointiva_locations',
			'settings'      => $wpdb->prefix . 'appointiva_settings',
		);
	}

	private static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$t               = self::table_names();

		$schemas   = array();
		$schemas[] = "CREATE TABLE {$t['locations']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			address TEXT NULL,
			phone VARCHAR(50) NULL,
			timezone VARCHAR(64) NOT NULL DEFAULT 'UTC',
			is_default TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status)
		) {$charset_collate};";

		$schemas[] = "CREATE TABLE {$t['staff']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NULL,
			display_name VARCHAR(191) NOT NULL,
			email VARCHAR(191) NOT NULL,
			phone VARCHAR(50) NULL,
			avatar_url VARCHAR(500) NULL,
			timezone VARCHAR(64) NOT NULL DEFAULT 'UTC',
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			sort_order INT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY status (status)
		) {$charset_collate};";

		$schemas[] = "CREATE TABLE {$t['services']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			location_id BIGINT UNSIGNED NULL,
			staff_id BIGINT UNSIGNED NULL,
			image_id BIGINT UNSIGNED NULL,
			name VARCHAR(191) NOT NULL,
			slug VARCHAR(191) NOT NULL,
			description TEXT NULL,
			duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
			buffer_before_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			buffer_after_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			booking_mode VARCHAR(20) NOT NULL DEFAULT 'timeslot',
			available_from TIME NULL,
			available_until TIME NULL,
			min_lead_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			currency VARCHAR(3) NOT NULL DEFAULT 'USD',
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			sort_order INT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY location_id (location_id),
			KEY staff_id (staff_id),
			KEY status (status),
			KEY slug (slug(64))
		) {$charset_collate};";

		$schemas[] = "CREATE TABLE {$t['availability']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			staff_id BIGINT UNSIGNED NULL,
			location_id BIGINT UNSIGNED NULL,
			type VARCHAR(20) NOT NULL DEFAULT 'recurring',
			day_of_week TINYINT UNSIGNED NULL,
			date DATE NULL,
			start_time TIME NULL,
			end_time TIME NULL,
			is_available TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY staff_id (staff_id),
			KEY type_day (type, day_of_week),
			KEY date (date)
		) {$charset_collate};";

		$schemas[] = "CREATE TABLE {$t['customers']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NULL,
			first_name VARCHAR(100) NOT NULL,
			last_name VARCHAR(100) NULL,
			email VARCHAR(191) NOT NULL,
			phone VARCHAR(50) NULL,
			locale VARCHAR(10) NULL,
			gdpr_consent_at DATETIME NULL,
			notes TEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY email (email),
			KEY user_id (user_id)
		) {$charset_collate};";

		$schemas[] = "CREATE TABLE {$t['bookings']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			uuid VARCHAR(36) NOT NULL,
			service_id BIGINT UNSIGNED NOT NULL,
			staff_id BIGINT UNSIGNED NULL,
			location_id BIGINT UNSIGNED NULL,
			customer_id BIGINT UNSIGNED NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			starts_at DATETIME NOT NULL,
			ends_at DATETIME NOT NULL,
			timezone VARCHAR(64) NOT NULL DEFAULT 'UTC',
			price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			currency VARCHAR(3) NOT NULL DEFAULT 'USD',
			notes TEXT NULL,
			admin_notes TEXT NULL,
			source VARCHAR(20) NOT NULL DEFAULT 'widget',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uuid (uuid),
			KEY staff_starts (staff_id, starts_at),
			KEY status (status),
			KEY customer_id (customer_id),
			KEY service_id (service_id)
		) {$charset_collate};";

		$schemas[] = "CREATE TABLE {$t['payments']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			booking_id BIGINT UNSIGNED NOT NULL,
			gateway VARCHAR(30) NOT NULL,
			transaction_id VARCHAR(191) NULL,
			type VARCHAR(20) NOT NULL DEFAULT 'full',
			amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
			currency VARCHAR(3) NOT NULL DEFAULT 'USD',
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			raw_response LONGTEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY booking_id (booking_id),
			KEY status (status),
			KEY transaction_id (transaction_id(64))
		) {$charset_collate};";

		$schemas[] = "CREATE TABLE {$t['notifications']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			booking_id BIGINT UNSIGNED NULL,
			customer_id BIGINT UNSIGNED NULL,
			channel VARCHAR(20) NOT NULL DEFAULT 'email',
			type VARCHAR(30) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'queued',
			recipient VARCHAR(191) NOT NULL,
			error_message TEXT NULL,
			sent_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY booking_id (booking_id),
			KEY status (status),
			KEY channel_type (channel, type)
		) {$charset_collate};";

		$schemas[] = "CREATE TABLE {$t['settings']} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			setting_key VARCHAR(191) NOT NULL,
			setting_value LONGTEXT NULL,
			autoload TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY setting_key (setting_key)
		) {$charset_collate};";

		foreach ( $schemas as $sql ) {
			dbDelta( $sql );
		}
	}
}
