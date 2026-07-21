<?php
/**
 * AES-256-CBC encryption for at-rest storage of sensitive settings (SMTP
 * credentials, API secrets). Not for passwords that need hashing — this is
 * reversible encryption, used only where the plaintext must be recoverable
 * to place an outbound call (e.g. authenticating with an SMTP server).
 *
 * @package Appointiva
 */

namespace Appointiva\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Encryption {

	private const CIPHER = 'aes-256-cbc';

	public static function encrypt( string $plaintext ): string {
		$iv_length = openssl_cipher_iv_length( self::CIPHER );
		$iv        = openssl_random_pseudo_bytes( $iv_length );

		$ciphertext = openssl_encrypt( $plaintext, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv );

		if ( false === $ciphertext ) {
			return '';
		}

		return base64_encode( $iv . $ciphertext ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	public static function decrypt( string $encoded ): string {
		if ( '' === $encoded ) {
			return '';
		}

		$raw       = base64_decode( $encoded, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$iv_length = openssl_cipher_iv_length( self::CIPHER );

		if ( false === $raw || strlen( $raw ) <= $iv_length ) {
			return '';
		}

		$iv         = substr( $raw, 0, $iv_length );
		$ciphertext = substr( $raw, $iv_length );

		$plaintext = openssl_decrypt( $ciphertext, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv );

		return false === $plaintext ? '' : $plaintext;
	}

	/**
	 * Derives a 256-bit key from WordPress's own AUTH_KEY/AUTH_SALT secrets,
	 * scoped to this plugin. Nothing plugin-specific is stored separately, so
	 * there is no additional secret-management surface to protect.
	 */
	private static function key(): string {
		$secret = wp_salt( 'auth' ) . '|appointiva';

		return hash( 'sha256', $secret, true );
	}
}
