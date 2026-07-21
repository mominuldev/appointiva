<?php
/**
 * Text domain loading.
 *
 * @package Appointiva
 */

namespace Appointiva\I18n;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class I18n {

	public function register_hooks(): void {
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	public function load_textdomain(): void {
		load_plugin_textdomain( 'appointiva', false, dirname( APPOINTIVA_PLUGIN_BASENAME ) . '/languages' );
	}
}
