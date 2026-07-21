<?php
/**
 * Reads/writes the "General" settings group (admin email, date format,
 * currency display, theme mode) and formats values against them. Backed by
 * the appointiva_settings table (key "general"), unlike the older
 * reminder/uninstall options which predate this class and stayed on
 * wp_options for backward compatibility.
 *
 * @package Appointiva
 */

namespace Appointiva\Support;

use Appointiva\Database\Settings_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class General_Settings {

	public const THEME_MODES        = array( 'auto', 'light', 'dark' );
	public const CURRENCY_POSITIONS = array( 'before', 'after' );

	public static function defaults(): array {
		$date_format = get_option( 'date_format' );

		return array(
			'admin_email'       => get_option( 'admin_email' ),
			'date_format'       => $date_format ? $date_format : 'Y-m-d',
			'currency'          => 'USD',
			'currency_symbol'   => '$',
			'currency_position' => 'before',
			'theme_mode'        => 'auto',
		);
	}

	public static function all(): array {
		return array_merge( self::defaults(), Settings_Repository::get( 'general', array() ) );
	}

	public static function get( string $key ): mixed {
		return self::all()[ $key ] ?? null;
	}

	public static function save( array $input ): void {
		$admin_email     = sanitize_email( $input['admin_email'] ?? '' );
		$date_format     = sanitize_text_field( $input['date_format'] ?? '' );
		$currency        = strtoupper( substr( sanitize_text_field( $input['currency'] ?? '' ), 0, 3 ) );
		$currency_symbol = sanitize_text_field( $input['currency_symbol'] ?? '' );

		Settings_Repository::set(
			'general',
			array(
				'admin_email'       => '' !== $admin_email ? $admin_email : get_option( 'admin_email' ),
				'date_format'       => '' !== $date_format ? $date_format : 'Y-m-d',
				'currency'          => '' !== $currency ? $currency : 'USD',
				'currency_symbol'   => '' !== $currency_symbol ? $currency_symbol : '$',
				'currency_position' => in_array( $input['currency_position'] ?? '', self::CURRENCY_POSITIONS, true ) ? $input['currency_position'] : 'before',
				'theme_mode'        => in_array( $input['theme_mode'] ?? '', self::THEME_MODES, true ) ? $input['theme_mode'] : 'auto',
			),
			true
		);
	}
}
