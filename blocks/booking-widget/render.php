<?php
/**
 * Server-side render for the appointiva/booking-widget block.
 *
 * @package Appointiva
 * @var array    $attributes
 * @var string   $content
 * @var WP_Block $block
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

appointiva()->get( 'frontend.assets' )->enqueue();

echo \Appointiva\Frontend\Widget_Renderer::render( (int) ( $attributes['serviceId'] ?? 0 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Widget_Renderer escapes internally.
