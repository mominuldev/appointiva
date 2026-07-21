<?php
/**
 * Data access for the bookings table.
 *
 * @package Appointiva
 */

namespace Appointiva\Booking;

use Appointiva\Database\Installer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Booking_Repository {

	public function insert( array $data ): int {
		global $wpdb;

		$wpdb->insert(
			Installer::table_names()['bookings'],
			array(
				'uuid'        => $data['uuid'],
				'service_id'  => $data['service_id'],
				'staff_id'    => $data['staff_id'] ?? null,
				'location_id' => $data['location_id'] ?? null,
				'customer_id' => $data['customer_id'],
				'status'      => $data['status'],
				'starts_at'   => $data['starts_at'],
				'ends_at'     => $data['ends_at'],
				'timezone'    => $data['timezone'],
				'price'       => $data['price'],
				'currency'    => $data['currency'],
				'notes'       => $data['notes'] ?? '',
				'source'      => $data['source'] ?? 'widget',
				'created_at'  => current_time( 'mysql' ),
				'updated_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	public function get( int $booking_id ): ?object {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', Installer::table_names()['bookings'], $booking_id )
		);

		return $row ?: null;
	}

	public function get_by_uuid( string $uuid ): ?object {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE uuid = %s', Installer::table_names()['bookings'], $uuid )
		);

		return $row ?: null;
	}

	public function update_status( int $booking_id, string $status ): bool {
		global $wpdb;

		$updated = $wpdb->update(
			Installer::table_names()['bookings'],
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $booking_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Moves a booking into 'offer_sent', optionally repricing it. A $price of
	 * 0.0 means "no price set" — the bookings.price column is NOT NULL, so
	 * this is the deliberate convention for an unpriced offer rather than a
	 * schema change.
	 */
	public function update_offer( int $booking_id, float $price, string $admin_notes ): bool {
		global $wpdb;

		$updated = $wpdb->update(
			Installer::table_names()['bookings'],
			array(
				'status'      => Status::OFFER_SENT->value,
				'price'       => $price,
				'admin_notes' => $admin_notes,
				'updated_at'  => current_time( 'mysql' ),
			),
			array( 'id' => $booking_id ),
			array( '%s', '%f', '%s', '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	public function delete( int $booking_id ): bool {
		global $wpdb;

		return false !== $wpdb->delete(
			Installer::table_names()['bookings'],
			array( 'id' => $booking_id ),
			array( '%d' )
		);
	}

	/**
	 * @return object[]
	 */
	public function get_for_customer( int $customer_id ): array {
		global $wpdb;

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE customer_id = %d ORDER BY starts_at DESC',
				Installer::table_names()['bookings'],
				$customer_id
			)
		);
	}

	/**
	 * Bookings starting within the given lead-time window, for reminder cron.
	 *
	 * @return object[]
	 */
	public function get_upcoming_needing_reminder( string $from, string $to ): array {
		global $wpdb;

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.* FROM %i b
				LEFT JOIN %i n ON n.booking_id = b.id AND n.type = %s
				WHERE b.status = %s AND b.starts_at BETWEEN %s AND %s AND n.id IS NULL",
				Installer::table_names()['bookings'],
				Installer::table_names()['notifications'],
				'reminder',
				Status::CONFIRMED->value,
				$from,
				$to
			)
		);
	}
}
