<?php
/**
 * Contract for a notification delivery channel (email, and Pro's WhatsApp).
 *
 * @package Appointiva
 */

namespace Appointiva\Notifications;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface Channel_Interface {

	/** Unique channel id, e.g. 'email', 'whatsapp'. */
	public function id(): string;

	/**
	 * Sends a notification of the given type ('confirmation'|'reminder'|'cancellation')
	 * for a booking. Returns true on success.
	 */
	public function send( object $booking, string $type ): bool;
}
