<?php
/**
 * Stateless tokens that let an anonymous customer accept/decline an offer
 * from an emailed link, without a login or a new DB column. Keyed by
 * wp_salt('auth') — the same site-wide secret WordPress uses for auth
 * cookies — and bound to the specific action so an "accept" link can never
 * be replayed as "decline".
 *
 * @package Appointiva
 */

namespace Appointiva\Booking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Offer_Token {

	public static function generate( string $uuid, string $action ): string {
		return hash_hmac( 'sha256', $uuid . '|' . $action, wp_salt( 'auth' ) );
	}

	public static function verify( string $uuid, string $action, string $token ): bool {
		return hash_equals( self::generate( $uuid, $action ), $token );
	}
}
