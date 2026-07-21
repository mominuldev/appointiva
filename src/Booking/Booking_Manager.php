<?php
/**
 * Orchestrates booking creation and status transitions.
 *
 * @package Appointiva
 */

namespace Appointiva\Booking;

use Appointiva\Database\Installer;
use Appointiva\Support\Result;
use DateTimeImmutable;
use DateTimeZone;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Booking_Manager {

	public function __construct(
		private Availability $availability,
		private Booking_Repository $bookings,
		private Customer_Repository $customers
	) {}

	/**
	 * @param array{
	 *     service_id: int,
	 *     starts_at: string,
	 *     first_name: string,
	 *     last_name?: string,
	 *     email: string,
	 *     phone?: string,
	 *     notes?: string,
	 *     gdpr_consent?: bool,
	 *     source?: string,
	 * } $data
	 */
	public function create_booking( array $data ): Result {
		$service = $this->get_service( (int) $data['service_id'] );

		if ( ! $service ) {
			return Result::failure( 'invalid_service', __( 'This service is not available.', 'appointiva' ) );
		}

		if ( empty( $data['gdpr_consent'] ) ) {
			return Result::failure( 'consent_required', __( 'Please confirm you agree to the storage of your booking details.', 'appointiva' ) );
		}

		$timezone  = new DateTimeZone( wp_timezone_string() );
		$starts_at = new DateTimeImmutable( $data['starts_at'], $timezone );
		$ends_at   = $starts_at->modify( '+' . (int) $service->duration_minutes . ' minutes' );

		if ( ! $this->slot_is_open( $service, $starts_at, $timezone ) ) {
			return Result::failure( 'slot_unavailable', __( 'This time slot is no longer available. Please choose another.', 'appointiva' ) );
		}

		// Re-resolves which specific staff member covers this slot at the
		// moment of booking (not just "a slot list said this time was open"),
		// closing the race window between a customer viewing slots and
		// submitting the form. With Free's single-staff-per-service default
		// this always resolves to $service->staff_id; Pro's multi-staff
		// filter can offer several candidates, and this picks whichever one
		// is still actually free right now.
		$staff_id = $this->resolve_staff_id( $service, $starts_at, $timezone );

		if ( false === $staff_id ) {
			return Result::failure( 'slot_unavailable', __( 'This time slot is no longer available. Please choose another.', 'appointiva' ) );
		}

		$customer_id = $this->customers->find_or_create( $data );

		/**
		 * Default status assigned to a newly created booking. Filterable so
		 * an offer-based booking mode can start bookings at a different
		 * status (e.g. keep them at 'pending' until an admin sends a priced
		 * offer) without changing this method.
		 *
		 * @param string $status     Status value, defaults to Status::PENDING.
		 * @param object $service    Service row being booked.
		 * @param array  $data       Raw booking submission.
		 */
		$initial_status = apply_filters( 'appointiva_new_booking_status', Status::PENDING->value, $service, $data );

		/**
		 * Whether new bookings should auto-confirm immediately instead of
		 * staying pending for an admin to accept, decline, or send a priced
		 * offer. Free plugin default: false — every booking starts pending.
		 *
		 * @param bool   $auto_confirm
		 * @param object $service
		 */
		if ( apply_filters( 'appointiva_auto_confirm_bookings', false, $service ) && Status::PENDING->value === $initial_status ) {
			$initial_status = Status::CONFIRMED->value;
		}

		$booking_data = array(
			'uuid'        => wp_generate_uuid4(),
			'service_id'  => $service->id,
			'staff_id'    => $staff_id,
			'location_id' => $service->location_id,
			'customer_id' => $customer_id,
			'status'      => $initial_status,
			'starts_at'   => $starts_at->format( 'Y-m-d H:i:s' ),
			'ends_at'     => $ends_at->format( 'Y-m-d H:i:s' ),
			'timezone'    => $timezone->getName(),
			'price'       => $service->price,
			'currency'    => $service->currency,
			'notes'       => sanitize_textarea_field( $data['notes'] ?? '' ),
			'source'      => sanitize_key( $data['source'] ?? 'widget' ),
		);

		/**
		 * Fires before a booking row is written. Payment/deposit add-ons can
		 * short-circuit here (e.g. by throwing via their own validation) if
		 * a required payment step hasn't completed.
		 *
		 * @param array $booking_data
		 */
		do_action( 'appointiva_before_booking_created', $booking_data );

		$booking_id = $this->bookings->insert( $booking_data );
		$booking    = $this->bookings->get( $booking_id );

		/**
		 * Fires after a booking has been persisted. The notification manager
		 * and calendar sync integration listen here; Pro's WhatsApp channel
		 * and two-way sync hook into the same action.
		 *
		 * @param object $booking Full booking row, including generated id/uuid.
		 */
		do_action( 'appointiva_after_booking_created', $booking );

		return Result::success( $booking );
	}

	public function change_status( int $booking_id, Status $next ): Result {
		return $this->transition( $booking_id, $next );
	}

	/**
	 * Prices (optionally) a pending booking and moves it to 'offer_sent'.
	 * $price of 0.0 sends an unpriced offer ("we can fit you in, confirming
	 * details soon") — see Booking_Repository::update_offer(). $admin_note is
	 * an internal note only, never surfaced to the customer or their email.
	 */
	public function send_offer( int $booking_id, float $price, string $admin_note ): Result {
		$booking = $this->bookings->get( $booking_id );

		if ( ! $booking ) {
			return Result::failure( 'not_found', __( 'Booking not found.', 'appointiva' ) );
		}

		$current = Status::from( $booking->status );

		if ( ! $current->can_transition_to( Status::OFFER_SENT ) ) {
			return Result::failure(
				'invalid_transition',
				/* translators: 1: current status label */
				sprintf( __( 'Cannot send an offer for a booking that is %1$s.', 'appointiva' ), $current->label() )
			);
		}

		$this->bookings->update_offer( $booking_id, $price, $admin_note );
		$updated = $this->bookings->get( $booking_id );

		/** This action is documented above in change_status()/transition(). */
		do_action( 'appointiva_booking_status_changed', $updated, $current, Status::OFFER_SENT );

		return Result::success( $updated );
	}

	/**
	 * Customer-facing accept/decline for an offer, reached via an emailed
	 * link with no login — $token must be a valid Offer_Token for $uuid and
	 * $action (bound to that exact action, so an accept link can't be
	 * replayed as a decline).
	 */
	public function respond_to_offer( string $uuid, string $token, string $action ): Result {
		$next = match ( $action ) {
			'accept' => Status::CONFIRMED,
			'decline' => Status::DECLINED,
			default => null,
		};

		if ( ! $next ) {
			return Result::failure( 'invalid_action', __( 'Unknown response.', 'appointiva' ) );
		}

		$booking = $this->bookings->get_by_uuid( $uuid );

		if ( ! $booking ) {
			return Result::failure( 'not_found', __( 'Booking not found.', 'appointiva' ) );
		}

		if ( ! Offer_Token::verify( $uuid, $action, $token ) ) {
			return Result::failure( 'invalid_token', __( 'This link is invalid.', 'appointiva' ) );
		}

		return $this->transition( (int) $booking->id, $next );
	}

	public function delete_booking( int $booking_id ): Result {
		$booking = $this->bookings->get( $booking_id );

		if ( ! $booking ) {
			return Result::failure( 'not_found', __( 'Booking not found.', 'appointiva' ) );
		}

		$this->bookings->delete( $booking_id );

		return Result::success( $booking );
	}

	private function transition( int $booking_id, Status $next ): Result {
		$booking = $this->bookings->get( $booking_id );

		if ( ! $booking ) {
			return Result::failure( 'not_found', __( 'Booking not found.', 'appointiva' ) );
		}

		$current = Status::from( $booking->status );

		if ( ! $current->can_transition_to( $next ) ) {
			return Result::failure(
				'invalid_transition',
				/* translators: 1: current status label, 2: target status label */
				sprintf( __( 'Cannot move a booking from %1$s to %2$s.', 'appointiva' ), $current->label(), $next->label() )
			);
		}

		$this->bookings->update_status( $booking_id, $next->value );
		$updated = $this->bookings->get( $booking_id );

		/**
		 * Fires whenever a booking's status changes.
		 *
		 * @param object $booking Updated booking row.
		 * @param Status $from    Previous status.
		 * @param Status $to      New status.
		 */
		do_action( 'appointiva_booking_status_changed', $updated, $current, $next );

		return Result::success( $updated );
	}

	/**
	 * @return int|null|false Resolved staff id, null if the service needs no
	 *                        specific staff, or false if no candidate is
	 *                        actually free (caller should treat as booking failure).
	 */
	private function resolve_staff_id( object $service, DateTimeImmutable $starts_at, DateTimeZone $timezone ): int|null|false {
		/** This filter is documented in Availability::get_slots(). */
		$candidates = apply_filters(
			'appointiva_service_staff_ids',
			array( $service->staff_id ? (int) $service->staff_id : null ),
			$service
		);

		foreach ( $candidates as $staff_id ) {
			if ( $this->availability->is_staff_available( null === $staff_id ? null : (int) $staff_id, $service, $starts_at, $timezone ) ) {
				return null === $staff_id ? null : (int) $staff_id;
			}
		}

		return false;
	}

	private function slot_is_open( object $service, DateTimeImmutable $starts_at, DateTimeZone $timezone ): bool {
		$slots = $this->availability->get_slots( (int) $service->id, $starts_at->format( 'Y-m-d' ), $starts_at->format( 'Y-m-d' ) );

		foreach ( $slots as $slot ) {
			if ( ( new DateTimeImmutable( $slot['start'], $timezone ) )->getTimestamp() === $starts_at->getTimestamp() ) {
				return true;
			}
		}

		return false;
	}

	private function get_service( int $service_id ): ?object {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d AND status = %s',
				Installer::table_names()['services'],
				$service_id,
				'active'
			)
		);

		return $row ?: null;
	}
}
