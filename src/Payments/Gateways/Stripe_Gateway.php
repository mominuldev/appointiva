<?php
/**
 * Stripe Payment Intents integration. Calls the Stripe REST API directly
 * over wp_remote_post rather than bundling the stripe-php SDK, keeping the
 * plugin's footprint small.
 *
 * @package Appointiva
 */

namespace Appointiva\Payments\Gateways;

use Appointiva\Database\Settings_Repository;
use Appointiva\Payments\Gateway_Interface;
use Appointiva\Payments\Payment_Repository;
use Appointiva\Support\Encryption;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Stripe_Gateway implements Gateway_Interface {

	private const API_BASE = 'https://api.stripe.com/v1';

	public function __construct( private Payment_Repository $payments ) {}

	public function id(): string {
		return 'stripe';
	}

	public function label(): string {
		return __( 'Stripe (cards)', 'appointiva' );
	}

	public function is_configured(): bool {
		return '' !== $this->secret_key();
	}

	public function create_payment( object $booking, ?float $amount = null, string $type = 'full' ): array {
		$amount ??= (float) $booking->price;

		$response = wp_remote_post(
			self::API_BASE . '/payment_intents',
			array(
				'headers' => $this->auth_headers(),
				'body'    => array(
					'amount'                    => (int) round( $amount * 100 ),
					'currency'                  => strtolower( $booking->currency ),
					'metadata'                  => array( 'appointiva_booking_uuid' => $booking->uuid ),
					'automatic_payment_methods' => array( 'enabled' => 'true' ),
				),
			)
		);

		$body = $this->decode( $response );

		if ( empty( $body['id'] ) ) {
			return array( 'error' => $this->error_message( $response, $body ) );
		}

		$this->payments->insert(
			array(
				'booking_id'     => $booking->id,
				'gateway'        => $this->id(),
				'transaction_id' => $body['id'],
				'type'           => $type,
				'amount'         => $amount,
				'currency'       => $booking->currency,
				'status'         => 'pending',
				'raw_response'   => $body,
			)
		);

		return array(
			'client_secret'   => $body['client_secret'] ?? '',
			'publishable_key' => $this->publishable_key(),
		);
	}

	public function confirm_payment( array $payload ): bool {
		$intent_id = $payload['payment_intent_id'] ?? '';

		if ( ! $intent_id ) {
			return false;
		}

		$response = wp_remote_get(
			self::API_BASE . '/payment_intents/' . rawurlencode( $intent_id ),
			array( 'headers' => $this->auth_headers() )
		);

		$body   = $this->decode( $response );
		$status = $body['status'] ?? '';
		$paid   = 'succeeded' === $status;

		$existing = $this->payments->find_by_transaction_id( $intent_id );

		if ( $existing ) {
			$this->payments->update_status( (int) $existing->id, $paid ? 'completed' : 'failed', $body );
		}

		return $paid;
	}

	private function auth_headers(): array {
		return array( 'Authorization' => 'Bearer ' . $this->secret_key() );
	}

	private function secret_key(): string {
		$settings = Settings_Repository::get( 'stripe', array() );

		return Encryption::decrypt( $settings['secret_key_encrypted'] ?? '' );
	}

	private function publishable_key(): string {
		$settings = Settings_Repository::get( 'stripe', array() );

		return (string) ( $settings['publishable_key'] ?? '' );
	}

	private function decode( $response ): array {
		if ( is_wp_error( $response ) ) {
			return array();
		}

		return (array) json_decode( wp_remote_retrieve_body( $response ), true );
	}

	private function error_message( $response, array $body ): string {
		if ( is_wp_error( $response ) ) {
			return $response->get_error_message();
		}

		return $body['error']['message'] ?? __( 'Stripe payment could not be started.', 'appointiva' );
	}
}
