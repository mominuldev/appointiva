<?php
/**
 * SMTP delivery with presets for common providers, so booking emails don't
 * depend on the host's often-broken default wp_mail() transport.
 *
 * @package Appointiva
 */

namespace Appointiva\Notifications;

use Appointiva\Database\Settings_Repository;
use Appointiva\Support\Encryption;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Smtp_Settings {

	public const PRESETS = array(
		'gmail'   => array( 'label' => 'Gmail', 'host' => 'smtp.gmail.com', 'port' => 587, 'encryption' => 'tls' ),
		'outlook' => array( 'label' => 'Outlook / Microsoft 365', 'host' => 'smtp.office365.com', 'port' => 587, 'encryption' => 'tls' ),
		'yahoo'   => array( 'label' => 'Yahoo Mail', 'host' => 'smtp.mail.yahoo.com', 'port' => 587, 'encryption' => 'tls' ),
		'icloud'  => array( 'label' => 'iCloud Mail', 'host' => 'smtp.mail.me.com', 'port' => 587, 'encryption' => 'tls' ),
		'zoho'    => array( 'label' => 'Zoho Mail', 'host' => 'smtp.zoho.com', 'port' => 587, 'encryption' => 'tls' ),
		'custom'  => array( 'label' => 'Custom SMTP server', 'host' => '', 'port' => 587, 'encryption' => 'tls' ),
	);

	public function register_hooks(): void {
		add_action( 'phpmailer_init', array( $this, 'configure_phpmailer' ) );
	}

	/**
	 * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer
	 */
	public function configure_phpmailer( $phpmailer ): void {
		$settings = Settings_Repository::get( 'smtp', array() );

		if ( empty( $settings['enabled'] ) || empty( $settings['host'] ) ) {
			return;
		}

		$phpmailer->isSMTP();
		$phpmailer->Host       = $settings['host'];
		$phpmailer->Port       = (int) ( $settings['port'] ?? 587 );
		$phpmailer->SMTPSecure = $settings['encryption'] ?? 'tls';
		$phpmailer->SMTPAuth   = ! empty( $settings['username'] );

		if ( $phpmailer->SMTPAuth ) {
			$phpmailer->Username = $settings['username'];
			$phpmailer->Password = Encryption::decrypt( $settings['password_encrypted'] ?? '' );
		}

		if ( ! empty( $settings['from_email'] ) ) {
			$phpmailer->setFrom( $settings['from_email'], $settings['from_name'] ?? get_bloginfo( 'name' ) );
		}
	}

	/**
	 * Persists SMTP settings, encrypting the password before it touches the
	 * database. Called from the admin REST controller after capability and
	 * nonce checks.
	 */
	public function save( array $input ): void {
		$existing = Settings_Repository::get( 'smtp', array() );

		$password_encrypted = $existing['password_encrypted'] ?? '';
		if ( ! empty( $input['password'] ) ) {
			$password_encrypted = Encryption::encrypt( $input['password'] );
		}

		Settings_Repository::set(
			'smtp',
			array(
				'enabled'             => ! empty( $input['enabled'] ),
				'preset'              => sanitize_key( $input['preset'] ?? 'custom' ),
				'host'                => sanitize_text_field( $input['host'] ?? '' ),
				'port'                => absint( $input['port'] ?? 587 ),
				'encryption'          => in_array( $input['encryption'] ?? 'tls', array( 'tls', 'ssl', '' ), true ) ? $input['encryption'] : 'tls',
				'username'            => sanitize_text_field( $input['username'] ?? '' ),
				'password_encrypted'  => $password_encrypted,
				'from_email'          => sanitize_email( $input['from_email'] ?? '' ),
				'from_name'           => sanitize_text_field( $input['from_name'] ?? '' ),
			),
			true
		);
	}
}
