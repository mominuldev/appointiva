<?php
/**
 * Enqueues the built admin React app (assets/dist/admin), produced by
 * `npm run build` from admin-app/src via the plugin's Vite config.
 *
 * @package Appointiva
 */

namespace Appointiva\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Assets {

	private const DIST_PATH = 'assets/dist/admin/';

	public function register(): void {
		if ( ! file_exists( APPOINTIVA_PLUGIN_PATH . self::DIST_PATH . 'admin.js' ) ) {
			add_action( 'admin_notices', array( $this, 'missing_build_notice' ) );
			return;
		}

		wp_register_style(
			'appointiva-admin',
			APPOINTIVA_ASSETS_URL . 'dist/admin/admin.css',
			array(),
			APPOINTIVA_VERSION
		);

		wp_register_script(
			'appointiva-admin',
			APPOINTIVA_ASSETS_URL . 'dist/admin/admin.js',
			array(),
			APPOINTIVA_VERSION,
			true
		);

		wp_localize_script(
			'appointiva-admin',
			'AppointivaAdminConfig',
			array(
				'apiUrl'    => esc_url_raw( rest_url( 'appointiva/v1' ) ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'assetsUrl' => APPOINTIVA_ASSETS_URL,
				'version'   => APPOINTIVA_VERSION,
			)
		);

		/**
		 * Fires after the Free plugin has registered its admin script/style
		 * handles. Appointiva Pro enqueues its own bundle here, and can
		 * depend on the 'appointiva-admin' handle so it always loads after
		 * ours.
		 */
		do_action( 'appointiva_admin_assets_registered' );
	}

	public function enqueue(): void {
		if ( ! wp_script_is( 'appointiva-admin', 'registered' ) ) {
			return;
		}

		wp_enqueue_style( 'appointiva-admin' );
		wp_enqueue_script( 'appointiva-admin' );
	}

	public function missing_build_notice(): void {
		echo '<div class="notice notice-warning"><p>' .
			esc_html__( 'Appointiva: the admin app has not been built yet. Run `npm install && npm run build` in the plugin directory.', 'appointiva' ) .
			'</p></div>';
	}
}
