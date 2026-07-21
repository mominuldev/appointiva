<?php
/**
 * Dispatches notifications across whichever channels are registered, and
 * logs every attempt to the notifications table.
 *
 * @package Appointiva
 */

namespace Appointiva\Notifications;

use Appointiva\Booking\Booking_Repository;
use Appointiva\Booking\Customer_Repository;
use Appointiva\Booking\Status;
use Appointiva\Database\Installer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Notification_Manager {

	public function __construct( private Customer_Repository $customers, private Booking_Repository $bookings ) {}

	public function register_hooks(): void {
		add_action( 'appointiva_after_booking_created', array( $this, 'on_booking_created' ) );
		add_action( 'appointiva_booking_status_changed', array( $this, 'on_status_changed' ), 10, 3 );
		add_action( 'appointiva_send_reminder_notifications', array( $this, 'send_due_reminders' ) );
	}

	public function on_booking_created( object $booking ): void {
		$this->dispatch( $booking, Status::from( $booking->status ) === Status::CONFIRMED ? 'confirmation' : 'pending' );
	}

	public function on_status_changed( object $booking, Status $from, Status $to ): void {
		if ( Status::CONFIRMED === $to ) {
			$this->dispatch( $booking, 'confirmation' );
		} elseif ( Status::CANCELLED === $to ) {
			$this->dispatch( $booking, 'cancellation' );
		} elseif ( Status::OFFER_SENT === $to ) {
			$this->dispatch( $booking, 'offer' );
		}
	}

	public function send_due_reminders(): void {
		/**
		 * Hours before an appointment that a reminder should go out.
		 *
		 * @param int $hours
		 */
		$lead_hours = (int) apply_filters( 'appointiva_reminder_lead_hours', 24 );

		$from = gmdate( 'Y-m-d H:i:s' );
		$to   = gmdate( 'Y-m-d H:i:s', time() + $lead_hours * HOUR_IN_SECONDS );

		foreach ( $this->bookings->get_upcoming_needing_reminder( $from, $to ) as $booking ) {
			$this->dispatch( $booking, 'reminder' );
		}
	}

	private function dispatch( object $booking, string $type ): void {
		$customer = $this->customers->get( (int) $booking->customer_id );

		if ( ! $customer ) {
			return;
		}

		/**
		 * Which channels attempt delivery for Appointiva notifications.
		 * Free plugin ships only 'email'; Pro registers 'whatsapp' here.
		 *
		 * @param string[] $channels
		 */
		$channel_ids = apply_filters( 'appointiva_notification_channels', array( 'email' ) );

		/**
		 * The instantiated channel objects available to send through.
		 * Registered separately from the id list above so Pro can add its
		 * Channel_Interface implementation without the Free plugin knowing
		 * its class exists.
		 *
		 * @param array<string, Channel_Interface> $channels Keyed by channel id.
		 */
		$channels = apply_filters( 'appointiva_registered_notification_channels', array() );

		foreach ( $channel_ids as $channel_id ) {
			if ( ! isset( $channels[ $channel_id ] ) ) {
				continue;
			}

			$this->send_and_log( $channels[ $channel_id ], $booking, $customer, $type );
		}
	}

	private function send_and_log( Channel_Interface $channel, object $booking, object $customer, string $type ): void {
		global $wpdb;

		$sent  = false;
		$error = '';

		try {
			$sent = $channel->send( $booking, $type );
		} catch ( \Throwable $e ) {
			$error = $e->getMessage();
		}

		$wpdb->insert(
			Installer::table_names()['notifications'],
			array(
				'booking_id'    => $booking->id,
				'customer_id'   => $customer->id,
				'channel'       => $channel->id(),
				'type'          => $type,
				'status'        => $sent ? 'sent' : 'failed',
				'recipient'     => 'email' === $channel->id() ? $customer->email : $customer->phone,
				'error_message' => $error,
				'sent_at'       => $sent ? current_time( 'mysql' ) : null,
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}
}
