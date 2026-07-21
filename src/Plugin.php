<?php
/**
 * Wires up every service and exposes them to Appointiva Pro (and other
 * integrations) through a small service container plus WordPress hooks.
 *
 * @package Appointiva
 */

namespace Appointiva;

use Appointiva\Admin\Admin;
use Appointiva\Admin\Assets as Admin_Assets;
use Appointiva\Booking\Availability;
use Appointiva\Booking\Booking_Manager;
use Appointiva\Booking\Booking_Repository;
use Appointiva\Booking\Customer_Repository;
use Appointiva\Database\Installer;
use Appointiva\Frontend\Assets as Frontend_Assets;
use Appointiva\Frontend\Block;
use Appointiva\Frontend\Shortcode;
use Appointiva\I18n\I18n;
use Appointiva\Integrations\Google_Calendar;
use Appointiva\Notifications\Channels\Email_Channel;
use Appointiva\Notifications\Mailer;
use Appointiva\Notifications\Notification_Manager;
use Appointiva\Notifications\Smtp_Settings;
use Appointiva\Payments\Payment_Manager;
use Appointiva\Payments\Payment_Repository;
use Appointiva\Privacy\GDPR;
use Appointiva\Rest\Admin_Controller;
use Appointiva\Rest\Public_Controller;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {

	private Container $container;
	private bool $booted = false;

	public function __construct() {
		$this->container = new Container();
	}

	/** Public accessor so templates, other hooks, and Appointiva Pro can reach any registered service. */
	public function get( string $id ): object {
		return $this->container->get( $id );
	}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		$this->register_services();

		Installer::maybe_upgrade();

		foreach ( $this->container_service_ids_with_hooks() as $id ) {
			$service = $this->container->get( $id );

			if ( method_exists( $service, 'register_hooks' ) ) {
				$service->register_hooks();
			}
		}

		add_filter( 'appointiva_reminder_lead_hours', array( $this, 'filter_reminder_lead_hours' ) );

		/**
		 * Fires once Appointiva's own services are fully wired up. Appointiva
		 * Pro hooks in here (also on plugins_loaded, lower priority) to
		 * register its own services against the same container/hooks.
		 *
		 * @param Plugin $plugin
		 */
		do_action( 'appointiva_loaded', $this );
	}

	public function filter_reminder_lead_hours( int $default ): int {
		return (int) get_option( 'appointiva_reminder_lead_hours', $default );
	}

	private function register_services(): void {
		$c = $this->container;

		$c->set( 'i18n', fn() => new I18n() );

		$c->set( 'booking.availability', fn() => new Availability() );
		$c->set( 'booking.repository', fn() => new Booking_Repository() );
		$c->set( 'booking.customers', fn() => new Customer_Repository() );
		$c->set(
			'booking.manager',
			fn( $c ) => new Booking_Manager(
				$c->get( 'booking.availability' ),
				$c->get( 'booking.repository' ),
				$c->get( 'booking.customers' )
			)
		);

		$c->set( 'notifications.mailer', fn() => new Mailer() );
		$c->set( 'notifications.smtp', fn() => new Smtp_Settings() );
		$c->set(
			'notifications.channels.email',
			fn( $c ) => new Email_Channel( $c->get( 'notifications.mailer' ), $c->get( 'booking.customers' ) )
		);
		$c->set(
			'notifications.manager',
			fn( $c ) => new Notification_Manager( $c->get( 'booking.customers' ), $c->get( 'booking.repository' ) )
		);

		$c->set( 'payments.repository', fn() => new Payment_Repository() );
		$c->set( 'payments.manager', fn( $c ) => new Payment_Manager( $c->get( 'payments.repository' ) ) );

		$c->set( 'integrations.google_calendar', fn() => new Google_Calendar() );

		$c->set( 'frontend.assets', fn() => new Frontend_Assets() );
		$c->set( 'frontend.shortcode', fn( $c ) => new Shortcode( $c->get( 'frontend.assets' ) ) );
		$c->set( 'frontend.block', fn() => new Block() );

		$c->set( 'rest.public', fn( $c ) => new Public_Controller( $c->get( 'booking.availability' ), $c->get( 'booking.manager' ) ) );
		$c->set(
			'rest.admin',
			fn( $c ) => new Admin_Controller( $c->get( 'booking.manager' ), $c->get( 'notifications.smtp' ) )
		);

		$c->set( 'admin.assets', fn() => new Admin_Assets() );
		$c->set( 'admin.menu', fn( $c ) => new Admin( $c->get( 'admin.assets' ) ) );

		$c->set( 'privacy.gdpr', fn() => new GDPR() );

		add_filter( 'appointiva_registered_notification_channels', array( $this, 'filter_registered_channels' ) );
	}

	public function filter_registered_channels( array $channels ): array {
		$channels['email'] = $this->container->get( 'notifications.channels.email' );

		return $channels;
	}

	/** @return string[] Container ids for every service that exposes register_hooks(). */
	private function container_service_ids_with_hooks(): array {
		return array(
			'i18n',
			'notifications.smtp',
			'notifications.manager',
			'payments.manager',
			'integrations.google_calendar',
			'frontend.assets',
			'frontend.shortcode',
			'frontend.block',
			'rest.public',
			'rest.admin',
			'privacy.gdpr',
			'admin.menu',
		);
	}
}
