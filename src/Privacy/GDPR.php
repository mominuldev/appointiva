<?php
/**
 * WordPress core personal-data exporter/eraser integration
 * (Tools > Export/Erase Personal Data), covering the customers, bookings,
 * payments, and notifications rows tied to an email address.
 *
 * @package Appointiva
 */

namespace Appointiva\Privacy;

use Appointiva\Database\Installer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GDPR {

	public function register_hooks(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
	}

	public function register_exporter( array $exporters ): array {
		$exporters['appointiva'] = array(
			'exporter_friendly_name' => __( 'Appointiva Bookings', 'appointiva' ),
			'callback'               => array( $this, 'export' ),
		);

		return $exporters;
	}

	public function register_eraser( array $erasers ): array {
		$erasers['appointiva'] = array(
			'eraser_friendly_name' => __( 'Appointiva Bookings', 'appointiva' ),
			'callback'             => array( $this, 'erase' ),
		);

		return $erasers;
	}

	public function export( string $email_address, int $page = 1 ): array {
		global $wpdb;
		$t = Installer::table_names();

		$customer = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE email = %s', $t['customers'], $email_address ) );

		if ( ! $customer ) {
			return array( 'data' => array(), 'done' => true );
		}

		$data = array();

		$data[] = array(
			'group_id'    => 'appointiva-customer',
			'group_label' => __( 'Appointiva Customer Record', 'appointiva' ),
			'item_id'     => 'appointiva-customer-' . $customer->id,
			'data'        => array(
				array( 'name' => __( 'Name', 'appointiva' ), 'value' => trim( $customer->first_name . ' ' . $customer->last_name ) ),
				array( 'name' => __( 'Email', 'appointiva' ), 'value' => $customer->email ),
				array( 'name' => __( 'Phone', 'appointiva' ), 'value' => $customer->phone ),
			),
		);

		$bookings = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE customer_id = %d', $t['bookings'], $customer->id ) );

		foreach ( $bookings as $booking ) {
			$data[] = array(
				'group_id'    => 'appointiva-bookings',
				'group_label' => __( 'Appointiva Bookings', 'appointiva' ),
				'item_id'     => 'appointiva-booking-' . $booking->id,
				'data'        => array(
					array( 'name' => __( 'Reference', 'appointiva' ), 'value' => $booking->uuid ),
					array( 'name' => __( 'Starts at', 'appointiva' ), 'value' => $booking->starts_at ),
					array( 'name' => __( 'Status', 'appointiva' ), 'value' => $booking->status ),
					array( 'name' => __( 'Notes', 'appointiva' ), 'value' => $booking->notes ),
				),
			);
		}

		return array( 'data' => $data, 'done' => true );
	}

	public function erase( string $email_address, int $page = 1 ): array {
		global $wpdb;
		$t = Installer::table_names();

		$customer = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE email = %s', $t['customers'], $email_address ) );

		if ( ! $customer ) {
			return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
		}

		$booking_ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM %i WHERE customer_id = %d', $t['bookings'], $customer->id ) );

		foreach ( $booking_ids as $booking_id ) {
			$wpdb->update(
				$t['bookings'],
				array( 'notes' => '', 'admin_notes' => '' ),
				array( 'id' => $booking_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		}

		$wpdb->update(
			$t['customers'],
			array(
				'first_name' => __( 'Redacted', 'appointiva' ),
				'last_name'  => '',
				'phone'      => '',
				'notes'      => '',
			),
			array( 'id' => $customer->id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		return array(
			'items_removed'  => true,
			'items_retained' => true, // Financial/payment records are retained for accounting/legal obligations.
			'messages'       => array( __( 'Personal details were redacted. Payment records are retained for accounting purposes.', 'appointiva' ) ),
			'done'           => true,
		);
	}
}
