<?php
/**
 * Plugin Name:       Appointiva
 * Plugin URI:        https://appointiva.com
 * Description:       Appointment and service booking for salons, consultants, tutors, clinics, and service businesses. Multilingual, RTL-ready, and accessible out of the box.
 * Version:           1.0.1
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Appointiva
 * Author URI:        https://appointiva.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       appointiva
 * Domain Path:       /languages
 *
 * @package Appointiva
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Plugin constants. APPOINTIVA_ is this plugin's exclusive prefix across constants, options, hooks, tables, and handles.
define( 'APPOINTIVA_VERSION', '1.0.1' );
define( 'APPOINTIVA_DB_VERSION', '1.0.1' );
define( 'APPOINTIVA_PLUGIN_FILE', __FILE__ );
define( 'APPOINTIVA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'APPOINTIVA_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'APPOINTIVA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'APPOINTIVA_TEMPLATES_PATH', APPOINTIVA_PLUGIN_PATH . 'templates/' );
define( 'APPOINTIVA_ASSETS_URL', APPOINTIVA_PLUGIN_URL . 'assets/' );

// Composer PSR-4 autoloader (Appointiva\ => src/).
$appointiva_autoloader = APPOINTIVA_PLUGIN_PATH . 'vendor/autoload.php';
if ( file_exists( $appointiva_autoloader ) ) {
	require_once $appointiva_autoloader;
} else {
	add_action(
		'admin_notices',
		function () {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			echo '<div class="notice notice-error"><p>' .
				esc_html__( 'Appointiva: Composer dependencies are missing. Run `composer install` in the plugin directory.', 'appointiva' ) .
				'</p></div>';
		}
	);
	return;
}

register_activation_hook( APPOINTIVA_PLUGIN_FILE, array( \Appointiva\Activator::class, 'activate' ) );
register_deactivation_hook( APPOINTIVA_PLUGIN_FILE, array( \Appointiva\Deactivator::class, 'deactivate' ) );

/**
 * Access the plugin singleton.
 *
 * Available to Appointiva Pro and other integrations once `plugins_loaded`
 * (priority 5) has run.
 */
function appointiva(): \Appointiva\Plugin {
	static $plugin = null;

	if ( null === $plugin ) {
		$plugin = new \Appointiva\Plugin();
	}

	return $plugin;
}

add_action(
	'plugins_loaded',
	function () {
		appointiva()->boot();
	},
	5
);
