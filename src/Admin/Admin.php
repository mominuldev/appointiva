<?php
/**
 * Registers the wp-admin menu and renders the React app's mount point.
 *
 * @package Appointiva
 */

namespace Appointiva\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {

	public const CAPABILITY = 'manage_options';
	public const PAGE_SLUG  = 'appointiva';

	public function __construct( private Assets $assets ) {}

	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
	}

	public function register_menu(): void {
		$hook = add_menu_page(
			__( 'Appointiva', 'appointiva' ),
			__( 'Appointiva', 'appointiva' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			'dashicons-calendar-alt',
			30
		);

		add_action(
			'admin_enqueue_scripts',
			function ( string $hook_suffix ) use ( $hook ) {
				if ( $hook_suffix === $hook ) {
					wp_enqueue_media();
					$this->assets->register();
					$this->assets->enqueue();
				}
			}
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'appointiva' ) );
		}

		echo '<div id="appointiva-admin-root" class="appointiva-admin-root"></div>';
	}
}
