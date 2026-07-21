<?php
/**
 * Contract for a payment gateway (Stripe, PayPal in the Free plugin).
 *
 * @package Appointiva
 */

namespace Appointiva\Payments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface Gateway_Interface {

	/** Unique gateway id, e.g. 'stripe', 'paypal'. */
	public function id(): string;

	public function label(): string;

	/** Whether the merchant has entered valid API credentials for this gateway. */
	public function is_configured(): bool;

	/**
	 * Starts a payment for a booking and returns whatever the frontend widget
	 * needs to complete it client-side (e.g. a Stripe client_secret or a
	 * PayPal order id/approval URL).
	 *
	 * @param float|null $amount Amount to charge; defaults to the booking's
	 *                           full price when null (e.g. a deposit add-on
	 *                           can pass a smaller amount instead).
	 * @param string     $type   Recorded on the payments row as-is (e.g.
	 *                           'full', 'deposit') — purely informational,
	 *                           the gateway charges whatever $amount says.
	 * @return array<string, mixed>
	 */
	public function create_payment( object $booking, ?float $amount = null, string $type = 'full' ): array;

	/**
	 * Confirms/captures a payment given the gateway's callback/webhook
	 * payload and records the result via Payment_Repository.
	 */
	public function confirm_payment( array $payload ): bool;
}
