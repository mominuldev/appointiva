<?php
/**
 * Key/value settings store backed by the appointiva_settings table.
 *
 * @package Appointiva
 */

namespace Appointiva\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings_Repository {

	/**
	 * In-request cache of autoloaded settings, populated on first read.
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $autoload_cache = null;

	public static function get( string $key, mixed $default = null ): mixed {
		self::prime_autoload_cache();

		if ( array_key_exists( $key, self::$autoload_cache ) ) {
			return self::$autoload_cache[ $key ];
		}

		global $wpdb;
		$table = Installer::table_names()['settings'];

		$row = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT setting_value FROM %i WHERE setting_key = %s',
				$table,
				$key
			)
		);

		if ( null === $row ) {
			return $default;
		}

		return self::unserialize( $row );
	}

	public static function set( string $key, mixed $value, bool $autoload = true ): bool {
		global $wpdb;
		$table = Installer::table_names()['settings'];

		$stored = maybe_serialize( $value );

		$result = $wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (setting_key, setting_value, autoload, updated_at)
				VALUES (%s, %s, %d, %s)
				ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), autoload = VALUES(autoload), updated_at = VALUES(updated_at)',
				$table,
				$key,
				$stored,
				$autoload ? 1 : 0,
				current_time( 'mysql' )
			)
		);

		self::$autoload_cache = null;

		return false !== $result;
	}

	public static function delete( string $key ): bool {
		global $wpdb;
		$table = Installer::table_names()['settings'];

		$result = $wpdb->delete( $table, array( 'setting_key' => $key ), array( '%s' ) );

		self::$autoload_cache = null;

		return false !== $result;
	}

	private static function prime_autoload_cache(): void {
		if ( null !== self::$autoload_cache ) {
			return;
		}

		global $wpdb;
		$table = Installer::table_names()['settings'];

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT setting_key, setting_value FROM %i WHERE autoload = 1',
				$table
			)
		);

		self::$autoload_cache = array();

		foreach ( (array) $rows as $row ) {
			self::$autoload_cache[ $row->setting_key ] = self::unserialize( $row->setting_value );
		}
	}

	private static function unserialize( string $value ): mixed {
		return maybe_unserialize( $value );
	}
}
