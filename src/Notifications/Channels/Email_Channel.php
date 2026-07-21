<?php
/**
 * Email delivery channel, the only channel shipped in the Free plugin.
 *
 * @package Appointiva
 */

namespace Appointiva\Notifications\Channels;

use Appointiva\Booking\Customer_Repository;
use Appointiva\Booking\Offer_Token;
use Appointiva\Notifications\Channel_Interface;
use Appointiva\Notifications\Mailer;
use DateTimeImmutable;
use DateTimeZone;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Email_Channel implements Channel_Interface {

	private const TEMPLATES = array(
		'confirmation' => 'booking-confirmation',
		'reminder'     => 'booking-reminder',
		'cancellation' => 'booking-cancellation',
		'offer'        => 'booking-offer',
	);

	public function __construct( private Mailer $mailer, private Customer_Repository $customers ) {}

	public function id(): string {
		return 'email';
	}

	public function send( object $booking, string $type ): bool {
		if ( ! isset( self::TEMPLATES[ $type ] ) ) {
			return false;
		}

		$customer = $this->customers->get( (int) $booking->customer_id );

		if ( ! $customer || ! is_email( $customer->email ) ) {
			return false;
		}

		$timezone  = new DateTimeZone( $booking->timezone ?: wp_timezone_string() );
		$starts_at = new DateTimeImmutable( $booking->starts_at, $timezone );

		$subjects = array(
			'confirmation' => __( 'Your booking is confirmed', 'appointiva' ),
			'reminder'     => __( 'Reminder: your upcoming appointment', 'appointiva' ),
			'cancellation' => __( 'Your booking has been cancelled', 'appointiva' ),
			'offer'        => __( 'A new offer for your appointment', 'appointiva' ),
		);

		/**
		 * Filters the subject line of a booking notification email.
		 *
		 * @param string $subject
		 * @param string $type
		 * @param object $booking
		 */
		$subject = apply_filters( 'appointiva_email_subject', $subjects[ $type ], $type, $booking );

		// Explicit, hand-built arg list per type — admin_notes (internal-only)
		// must never end up in a customer-facing template's variables.
		$vars = array(
			'booking'   => $booking,
			'customer'  => $customer,
			'starts_at' => $starts_at,
			'site_name' => get_bloginfo( 'name' ),
		);

		if ( 'offer' === $type ) {
			$vars['accept_url']  = $this->offer_response_url( $booking->uuid, 'accept' );
			$vars['decline_url'] = $this->offer_response_url( $booking->uuid, 'decline' );
		}

		return $this->mailer->send(
			$customer->email,
			$subject,
			self::TEMPLATES[ $type ],
			$vars,
			$customer->locale ?: ''
		);
	}

	private function offer_response_url( string $uuid, string $action ): string {
		return add_query_arg(
			array(
				'uuid'   => $uuid,
				'action' => $action,
				'token'  => Offer_Token::generate( $uuid, $action ),
			),
			rest_url( 'appointiva/v1/booking-response' )
		);
	}
}
