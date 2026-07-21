<?php
/**
 * Registers the appointiva/booking-widget Gutenberg block.
 *
 * @package Appointiva
 */

namespace Appointiva\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Block {

	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register' ) );
	}

	public function register(): void {
		register_block_type( APPOINTIVA_PLUGIN_PATH . 'blocks/booking-widget' );
	}
}
