<?php
/**
 * PayPal Orders v2 integration, called directly over the REST API rather
 * than bundling the PayPal SDK.
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

final class PayPal_Gateway implements Gateway_Interface {

	public function __construct( private Payment_Repository $payments ) {}

	public function id(): string {
		return 'paypal';
	}

	public function label(): string {
		return __( 'PayPal', 'appointiva' );
	}

	public function is_configured(): bool {
		return '' !== $this->client_id() && '' !== $this->client_secret();
	}

	public function create_payment( object $booking, ?float $amount = null, string $type = 'full' ): array {
		$amount ??= (float) $booking->price;
		$token    = $this->get_access_token();

		if ( ! $token ) {
			return array( 'error' => __( 'PayPal is not configured correctly.', 'appointiva' ) );
		}

		$response = wp_remote_post(
			$this->api_base() . '/v2/checkout/orders',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'intent'         => 'CAPTURE',
						'purchase_units' => array(
							array(
								'reference_id' => $booking->uuid,
								'amount'       => array(
									'currency_code' => $booking->currency,
									'value'         => number_format( $amount, 2, '.', '' ),
								),
							),
						),
					)
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

		$approve_url = '';
		foreach ( $body['links'] ?? array() as $link ) {
			if ( 'approve' === ( $link['rel'] ?? '' ) ) {
				$approve_url = $link['href'];
				break;
			}
		}

		return array( 'order_id' => $body['id'], 'approve_url' => $approve_url );
	}

	public function confirm_payment( array $payload ): bool {
		$order_id = $payload['order_id'] ?? '';
		$token    = $this->get_access_token();

		if ( ! $order_id || ! $token ) {
			return false;
		}

		$response = wp_remote_post(
			$this->api_base() . '/v2/checkout/orders/' . rawurlencode( $order_id ) . '/capture',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => '',
			)
		);

		$body      = $this->decode( $response );
		$completed = 'COMPLETED' === ( $body['status'] ?? '' );

		$existing = $this->payments->find_by_transaction_id( $order_id );

		if ( $existing ) {
			$this->payments->update_status( (int) $existing->id, $completed ? 'completed' : 'failed', $body );
		}

		return $completed;
	}

	private function get_access_token(): string {
		$response = wp_remote_post(
			$this->api_base() . '/v1/oauth2/token',
			array(
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $this->client_id() . ':' . $this->client_secret() ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				),
				'body'    => array( 'grant_type' => 'client_credentials' ),
			)
		);

		$body = $this->decode( $response );

		return (string) ( $body['access_token'] ?? '' );
	}

	private function api_base(): string {
		$settings = Settings_Repository::get( 'paypal', array() );

		return ! empty( $settings['sandbox'] ) ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
	}

	private function client_id(): string {
		$settings = Settings_Repository::get( 'paypal', array() );

		return (string) ( $settings['client_id'] ?? '' );
	}

	private function client_secret(): string {
		$settings = Settings_Repository::get( 'paypal', array() );

		return Encryption::decrypt( $settings['client_secret_encrypted'] ?? '' );
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

		return $body['message'] ?? __( 'PayPal payment could not be started.', 'appointiva' );
	}
}
