<?php
/**
 * Computes bookable time slots for a service.
 *
 * @package Appointiva
 */

namespace Appointiva\Booking;

use Appointiva\Database\Installer;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use DateTimeZone;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Availability {

	/**
	 * Returns bookable slots for a service between two dates (inclusive),
	 * in the site's timezone.
	 *
	 * @return array<int, array{start: string, end: string}> Slots as ISO 8601 datetime strings.
	 */
	public function get_slots( int $service_id, string $from_date, string $to_date ): array {
		global $wpdb;

		$service = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d AND status = %s',
				Installer::table_names()['services'],
				$service_id,
				'active'
			)
		);

		if ( ! $service ) {
			return array();
		}

		$timezone    = new DateTimeZone( wp_timezone_string() );
		$slot_length = (int) $service->duration_minutes + (int) $service->buffer_before_minutes + (int) $service->buffer_after_minutes;

		/**
		 * Filters which staff member(s) can serve a given service.
		 *
		 * Free's default is a single staff id (or null, meaning "no specific
		 * staff required"). Appointiva Pro hooks this — when licensed — to
		 * return every staff member assigned to the service via its own
		 * staff/service join table, so a slot shows as bookable as long as AT
		 * LEAST ONE assigned staff member is free, without this file needing
		 * to know anything about how Pro tracks those assignments.
		 *
		 * @param array<int, int|null> $staff_ids Candidate staff ids, in priority order.
		 * @param object                $service   Service row being queried.
		 */
		$staff_ids = apply_filters(
			'appointiva_service_staff_ids',
			array( $service->staff_id ? (int) $service->staff_id : null ),
			$service
		);

		$period = new DatePeriod(
			new DateTimeImmutable( $from_date, $timezone ),
			new DateInterval( 'P1D' ),
			( new DateTimeImmutable( $to_date, $timezone ) )->modify( '+1 day' )
		);

		// Slots are merged across candidate staff and de-duplicated by start
		// time: the public slot list never exposes which staff serves a
		// given time (Free's booking widget has no staff picker), it only
		// needs to know a slot is bookable by someone. Booking_Manager
		// resolves the specific staff member at booking time via
		// Availability::is_staff_available(), re-checking each candidate in
		// the same priority order this method used.
		$slots_by_start = array();

		foreach ( $staff_ids as $staff_id ) {
			$rules         = $this->get_availability_rules( $staff_id );
			$booked_ranges = $this->get_booked_ranges( $staff_id, $from_date, $to_date, $timezone );

			foreach ( $period as $day ) {
				foreach ( $this->windows_for_day( $day, $rules, $timezone ) as $window ) {
					foreach ( $this->slice_window( $window['start'], $window['end'], $slot_length, (int) $service->duration_minutes, $booked_ranges, (int) $service->min_lead_days ) as $slot ) {
						$slots_by_start[ $slot['start'] ] = $slot;
					}
				}
			}
		}

		ksort( $slots_by_start );
		$slots = array_values( $slots_by_start );

		/**
		 * Filters the final list of bookable slots for a service.
		 *
		 * @param array<int, array{start: string, end: string}> $slots      Computed slots.
		 * @param int                                            $service_id Service being queried.
		 * @param array{from: string, to: string}                $range      Requested date range.
		 */
		return apply_filters(
			'appointiva_available_time_slots',
			$slots,
			$service_id,
			array( 'from' => $from_date, 'to' => $to_date )
		);
	}

	/**
	 * Checks whether a single staff member (or the "no specific staff"
	 * bucket when $staff_id is null) is free for one specific slot. Used by
	 * Booking_Manager to resolve which staff a booking is actually assigned
	 * to when Availability::get_slots() merged candidates from multiple
	 * staff into one slot list.
	 */
	public function is_staff_available( ?int $staff_id, object $service, DateTimeImmutable $starts_at, DateTimeZone $timezone ): bool {
		$slot_length = (int) $service->duration_minutes + (int) $service->buffer_before_minutes + (int) $service->buffer_after_minutes;
		$occupied_until = $starts_at->modify( "+{$slot_length} minutes" );

		$rules       = $this->get_availability_rules( $staff_id );
		$day_windows = $this->windows_for_day( $starts_at->setTime( 0, 0 ), $rules, $timezone );

		$within_a_window = false;
		foreach ( $day_windows as $window ) {
			if ( $starts_at >= $window['start'] && $occupied_until <= $window['end'] ) {
				$within_a_window = true;
				break;
			}
		}

		if ( ! $within_a_window ) {
			return false;
		}

		$date          = $starts_at->format( 'Y-m-d' );
		$booked_ranges = $this->get_booked_ranges( $staff_id, $date, $date, $timezone );

		return ! $this->overlaps( $starts_at, $occupied_until, $booked_ranges );
	}

	/**
	 * @return object[] Availability rule rows for the given staff (or global rules when $staff_id is null).
	 */
	private function get_availability_rules( ?int $staff_id ): array {
		global $wpdb;
		$table = Installer::table_names()['availability'];

		if ( null === $staff_id ) {
			return (array) $wpdb->get_results(
				$wpdb->prepare( 'SELECT * FROM %i WHERE staff_id IS NULL', $table )
			);
		}

		return (array) $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i WHERE staff_id = %d OR staff_id IS NULL', $table, $staff_id )
		);
	}

	/**
	 * @return array<int, array{start: DateTimeImmutable, end: DateTimeImmutable}>
	 */
	private function windows_for_day( DateTimeImmutable $day, array $rules, DateTimeZone $timezone ): array {
		$date        = $day->format( 'Y-m-d' );
		$day_of_week = (int) $day->format( 'w' );

		// A date-specific override (including a full-day block) always wins over recurring rules.
		$overrides = array_values(
			array_filter(
				$rules,
				static fn( $rule ) => 'date_override' === $rule->type && $rule->date === $date
			)
		);

		$blocks = array_values(
			array_filter(
				$rules,
				static fn( $rule ) => 'blocked' === $rule->type && $rule->date === $date
			)
		);

		if ( ! empty( $blocks ) && null === $blocks[0]->start_time ) {
			return array(); // Whole day blocked.
		}

		$source = ! empty( $overrides )
			? $overrides
			: array_values(
				array_filter(
					$rules,
					static fn( $rule ) => 'recurring' === $rule->type && (int) $rule->day_of_week === $day_of_week && $rule->is_available
				)
			);

		$windows = array();

		foreach ( $source as $rule ) {
			if ( ! $rule->start_time || ! $rule->end_time ) {
				continue;
			}

			$windows[] = array(
				'start' => new DateTimeImmutable( $date . ' ' . $rule->start_time, $timezone ),
				'end'   => new DateTimeImmutable( $date . ' ' . $rule->end_time, $timezone ),
			);
		}

		return $windows;
	}

	/**
	 * @param DateTimeImmutable $window_start
	 * @param DateTimeImmutable $window_end
	 * @param int               $slot_length_minutes Total time a slot occupies on the calendar (duration + buffers).
	 * @param int               $bookable_minutes     Duration actually offered to the customer for the slot label.
	 * @param array<int, array{start: DateTimeImmutable, end: DateTimeImmutable}> $booked_ranges
	 * @param int               $min_lead_days        Minimum number of days' notice the service requires before a booking.
	 * @return array<int, array{start: string, end: string}>
	 */
	private function slice_window(
		DateTimeImmutable $window_start,
		DateTimeImmutable $window_end,
		int $slot_length_minutes,
		int $bookable_minutes,
		array $booked_ranges,
		int $min_lead_days = 0
	): array {
		if ( $slot_length_minutes <= 0 ) {
			return array();
		}

		$slots  = array();
		$cursor = $window_start;
		$now    = ( new DateTimeImmutable( 'now', $window_start->getTimezone() ) )->modify( "+{$min_lead_days} days" );

		while ( $cursor->modify( "+{$slot_length_minutes} minutes" ) <= $window_end ) {
			$slot_end = $cursor->modify( "+{$bookable_minutes} minutes" );
			$occupied_until = $cursor->modify( "+{$slot_length_minutes} minutes" );

			if ( $cursor > $now && ! $this->overlaps( $cursor, $occupied_until, $booked_ranges ) ) {
				$slots[] = array(
					'start' => $cursor->format( DATE_ATOM ),
					'end'   => $slot_end->format( DATE_ATOM ),
				);
			}

			$cursor = $cursor->modify( "+{$slot_length_minutes} minutes" );
		}

		return $slots;
	}

	/**
	 * @param array<int, array{start: DateTimeImmutable, end: DateTimeImmutable}> $ranges
	 */
	private function overlaps( DateTimeImmutable $start, DateTimeImmutable $end, array $ranges ): bool {
		foreach ( $ranges as $range ) {
			if ( $start < $range['end'] && $end > $range['start'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return array<int, array{start: DateTimeImmutable, end: DateTimeImmutable}>
	 */
	private function get_booked_ranges( ?int $staff_id, string $from_date, string $to_date, DateTimeZone $timezone ): array {
		global $wpdb;
		$table = Installer::table_names()['bookings'];

		$statuses = array_map( static fn( Status $s ) => $s->value, Status::slot_holding_statuses() );
		$placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );

		$query_args = array_merge( array( $table ), $statuses, array( $from_date . ' 00:00:00', $to_date . ' 23:59:59' ) );

		$sql = "SELECT starts_at, ends_at, staff_id FROM %i WHERE status IN ({$placeholders}) AND starts_at BETWEEN %s AND %s";

		if ( null !== $staff_id ) {
			$sql         .= ' AND staff_id = %d';
			$query_args[] = $staff_id;
		}

		// $sql is built from a fixed template with %i/%s/%d placeholders only (never raw
		// user input); $query_args supplies exactly one value per placeholder in order.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$query_args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array_map(
			static fn( $row ) => array(
				'start' => new DateTimeImmutable( $row->starts_at, $timezone ),
				'end'   => new DateTimeImmutable( $row->ends_at, $timezone ),
			),
			(array) $rows
		);
	}
}
