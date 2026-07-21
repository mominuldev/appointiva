<?php
/**
 * [appointiva_booking] shortcode.
 *
 * @package Appointiva
 */

namespace Appointiva\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Shortcode {

	public function __construct( private Assets $assets ) {}

	public function register_hooks(): void {
		add_shortcode( 'appointiva_booking', array( $this, 'render' ) );
	}

	public function render( array $atts ): string {
		$this->assets->enqueue();

		$atts = shortcode_atts(
			array(
				'service_id' => '',
			),
			$atts,
			'appointiva_booking'
		);

		return Widget_Renderer::render( (int) $atts['service_id'] );
	}
}
