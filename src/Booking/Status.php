<?php
/**
 * Booking status state machine.
 *
 * Covers both a direct accept/decline flow (Pending -> Confirmed/Declined)
 * and an inquiry/offer flow (Pending -> Offer_Sent -> Confirmed/Declined),
 * where an admin prices a pending booking before the customer commits.
 * Expired is reserved for a future cron-driven timeout and is not yet
 * assigned by any Free plugin code path.
 *
 * @package Appointiva
 */

namespace Appointiva\Booking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

enum Status: string {

	/** Booking created, not yet confirmed. Used as the first state in every flow. */
	case PENDING = 'pending';

	/** An admin-priced offer has been sent to the customer and awaits a response. */
	case OFFER_SENT = 'offer_sent';

	/** Booking is confirmed and holds a slot on the calendar. */
	case CONFIRMED = 'confirmed';

	/** Customer declined the offer sent to them. */
	case DECLINED = 'declined';

	/** Booking was cancelled by the customer or an admin after being confirmed or pending. */
	case CANCELLED = 'cancelled';

	/** The appointment time has passed and the booking was fulfilled. */
	case COMPLETED = 'completed';

	/** The customer did not show up for a confirmed appointment. */
	case NO_SHOW = 'no_show';

	/** An offer or unconfirmed pending booking expired without a response. */
	case EXPIRED = 'expired';

	/**
	 * Statuses that hold a slot on the availability calendar.
	 *
	 * @return Status[]
	 */
	public static function slot_holding_statuses(): array {
		/**
		 * Filters which booking statuses count as occupying an availability slot.
		 *
		 * @param Status[] $statuses Statuses that block the slot from being re-booked.
		 */
		return apply_filters(
			'appointiva_slot_holding_statuses',
			array( self::PENDING, self::OFFER_SENT, self::CONFIRMED )
		);
	}

	/**
	 * Default allowed transitions, keyed by current status value.
	 *
	 * Filterable so an offer-based booking mode can register additional
	 * transitions (e.g. PENDING -> OFFER_SENT) without modifying this file.
	 *
	 * @return array<string, string[]>
	 */
	public static function transitions(): array {
		$defaults = array(
			self::PENDING->value      => array( self::OFFER_SENT->value, self::CONFIRMED->value, self::DECLINED->value, self::CANCELLED->value, self::EXPIRED->value ),
			self::OFFER_SENT->value   => array( self::CONFIRMED->value, self::DECLINED->value, self::CANCELLED->value, self::EXPIRED->value ),
			self::CONFIRMED->value    => array( self::CANCELLED->value, self::COMPLETED->value, self::NO_SHOW->value ),
			self::DECLINED->value     => array(),
			self::CANCELLED->value    => array(),
			self::COMPLETED->value    => array(),
			self::NO_SHOW->value      => array(),
			self::EXPIRED->value      => array(),
		);

		/**
		 * Filters the booking status transition map.
		 *
		 * @param array<string, string[]> $defaults Map of status value => allowed next status values.
		 */
		return apply_filters( 'appointiva_booking_status_transitions', $defaults );
	}

	public function can_transition_to( Status $next ): bool {
		$allowed = self::transitions()[ $this->value ] ?? array();

		return in_array( $next->value, $allowed, true );
	}

	public function label(): string {
		$labels = array(
			self::PENDING->value    => __( 'Pending', 'appointiva' ),
			self::OFFER_SENT->value => __( 'Offer Sent', 'appointiva' ),
			self::CONFIRMED->value  => __( 'Confirmed', 'appointiva' ),
			self::DECLINED->value   => __( 'Declined', 'appointiva' ),
			self::CANCELLED->value  => __( 'Cancelled', 'appointiva' ),
			self::COMPLETED->value  => __( 'Completed', 'appointiva' ),
			self::NO_SHOW->value    => __( 'No-show', 'appointiva' ),
			self::EXPIRED->value    => __( 'Expired', 'appointiva' ),
		);

		return $labels[ $this->value ] ?? $this->value;
	}
}
