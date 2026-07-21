<?php
/**
 * Registers frontend assets. Enqueuing is left to Shortcode/Block, which
 * only pull these in on pages that actually render the widget.
 *
 * @package Appointiva
 */

namespace Appointiva\Frontend;

use Appointiva\Support\General_Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Assets {

	public function register_hooks(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'register' ) );
	}

	public function register(): void {
		wp_register_style(
			'appointiva-widget',
			APPOINTIVA_ASSETS_URL . 'css/widget.css',
			array(),
			APPOINTIVA_VERSION
		);

		wp_register_script(
			'appointiva-widget',
			APPOINTIVA_ASSETS_URL . 'js/widget.js',
			array(),
			APPOINTIVA_VERSION,
			true
		);

		$general = General_Settings::all();

		wp_localize_script(
			'appointiva-widget',
			'AppointivaWidgetConfig',
			array(
				'restUrl'          => esc_url_raw( rest_url( 'appointiva/v1' ) ),
				'locale'           => get_locale(),
				'isRtl'            => is_rtl(),
				'currencySymbol'   => $general['currency_symbol'],
				'currencyPosition' => $general['currency_position'],
				'i18n'             => array(
					'selectService'            => __( 'Select a service', 'appointiva' ),
					'selectServicePlaceholder' => __( 'Please select a service', 'appointiva' ),
					'selectTime'               => __( 'Select a time', 'appointiva' ),
					'firstName'                => __( 'First name', 'appointiva' ),
					'lastName'                 => __( 'Last name', 'appointiva' ),
					'email'                    => __( 'Email', 'appointiva' ),
					'phone'                    => __( 'Phone', 'appointiva' ),
					'notes'                    => __( 'Notes', 'appointiva' ),
					'consent'                  => __( 'I agree to the storage of my details to process this booking.', 'appointiva' ),
					'submit'                   => __( 'Book appointment', 'appointiva' ),
					'submitting'               => __( 'Booking...', 'appointiva' ),
					'success'                  => __( 'Your booking is confirmed. Check your email for details.', 'appointiva' ),
					'noSlots'                  => __( 'No available times in this range.', 'appointiva' ),
					'loadError'                => __( 'Something went wrong. Please try again.', 'appointiva' ),
				),
			)
		);
	}

	public function enqueue(): void {
		wp_enqueue_style( 'appointiva-widget' );
		wp_enqueue_script( 'appointiva-widget' );
	}
}
