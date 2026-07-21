<?php
/**
 * Renders email templates and sends mail in the recipient's locale.
 *
 * switch_to_locale() does not reload a plugin's own text domain, so
 * translation calls inside our templates would silently fall back to the
 * admin's locale unless we force a reload here.
 *
 * @package Appointiva
 */

namespace Appointiva\Notifications;

use Appointiva\Support\General_Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Mailer {

	public function send( string $to, string $subject, string $template, array $vars, string $locale = '' ): bool {
		$locale = $locale ?: get_locale();
		$switched = false;

		if ( $locale && $locale !== get_locale() ) {
			$switched = switch_to_locale( $locale );
		}

		if ( $switched ) {
			unload_textdomain( 'appointiva' );
			load_plugin_textdomain( 'appointiva', false, dirname( APPOINTIVA_PLUGIN_BASENAME ) . '/languages' );
		}

		$html = $this->render( $template, $vars );

		add_filter( 'wp_mail_content_type', array( $this, 'html_content_type' ) );

		/**
		 * Filters the "From" name used for Appointiva transactional email.
		 *
		 * @param string $from_name
		 */
		$from_name = apply_filters( 'appointiva_email_from_name', get_bloginfo( 'name' ) );

		$headers = array();
		if ( $from_name ) {
			$admin_email = General_Settings::get( 'admin_email' );
			$headers[]   = sprintf( 'From: %s <%s>', $from_name, $admin_email );
		}

		$sent = wp_mail( $to, $subject, $html, $headers );

		remove_filter( 'wp_mail_content_type', array( $this, 'html_content_type' ) );

		if ( $switched ) {
			restore_previous_locale();
			unload_textdomain( 'appointiva' );
			load_plugin_textdomain( 'appointiva', false, dirname( APPOINTIVA_PLUGIN_BASENAME ) . '/languages' );
		}

		return $sent;
	}

	public function html_content_type(): string {
		return 'text/html';
	}

	private function render( string $template, array $vars ): string {
		$path = APPOINTIVA_TEMPLATES_PATH . 'emails/' . $template . '.php';

		/**
		 * Filters the absolute path to an email template, letting a theme or
		 * Pro plugin ship overrides.
		 *
		 * @param string $path     Default template path.
		 * @param string $template Template slug, e.g. 'booking-confirmation'.
		 */
		$path = apply_filters( 'appointiva_email_template_path', $path, $template );

		if ( ! file_exists( $path ) ) {
			return '';
		}

		extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract

		ob_start();
		include $path;

		return (string) ob_get_clean();
	}
}
