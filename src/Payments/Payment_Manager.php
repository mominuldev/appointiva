<?php
/**
 * Registry of available payment gateways.
 *
 * @package Appointiva
 */

namespace Appointiva\Payments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Payment_Manager {

	/** @var array<string, Gateway_Interface> */
	private array $gateways = array();

	public function __construct( private Payment_Repository $payments ) {}

	public function register_hooks(): void {
		add_filter( 'appointiva_payment_gateways', array( $this, 'register_default_gateways' ) );
	}

	/**
	 * @param array<string, Gateway_Interface> $gateways
	 * @return array<string, Gateway_Interface>
	 */
	public function register_default_gateways( array $gateways ): array {
		$gateways['stripe'] = new Gateways\Stripe_Gateway( $this->payments );
		$gateways['paypal'] = new Gateways\PayPal_Gateway( $this->payments );

		return $gateways;
	}

	/** @return array<string, Gateway_Interface> Only gateways with valid credentials. */
	public function configured_gateways(): array {
		/**
		 * Filters the full set of registered payment gateways, keyed by id.
		 * Pro adds nothing here directly (payments stay Free-tier), but
		 * third-party gateway add-ons use this to register themselves.
		 *
		 * @param array<string, Gateway_Interface> $gateways
		 */
		$all = apply_filters( 'appointiva_payment_gateways', array() );

		return array_filter( $all, static fn( Gateway_Interface $gateway ) => $gateway->is_configured() );
	}

	public function get( string $id ): ?Gateway_Interface {
		return $this->configured_gateways()[ $id ] ?? null;
	}
}
