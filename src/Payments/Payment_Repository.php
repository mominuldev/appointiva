<?php
/**
 * Data access for the payments table.
 *
 * @package Appointiva
 */

namespace Appointiva\Payments;

use Appointiva\Database\Installer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Payment_Repository {

	public function insert( array $data ): int {
		global $wpdb;

		$wpdb->insert(
			Installer::table_names()['payments'],
			array(
				'booking_id'     => $data['booking_id'],
				'gateway'        => $data['gateway'],
				'transaction_id' => $data['transaction_id'] ?? null,
				'type'           => $data['type'] ?? 'full',
				'amount'         => $data['amount'],
				'currency'       => $data['currency'],
				'status'         => $data['status'] ?? 'pending',
				'raw_response'   => isset( $data['raw_response'] ) ? wp_json_encode( $data['raw_response'] ) : null,
				'created_at'     => current_time( 'mysql' ),
				'updated_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	public function update_status( int $payment_id, string $status, array $raw_response = array() ): bool {
		global $wpdb;

		$data   = array( 'status' => $status, 'updated_at' => current_time( 'mysql' ) );
		$format = array( '%s', '%s' );

		if ( ! empty( $raw_response ) ) {
			$data['raw_response'] = wp_json_encode( $raw_response );
			$format[]             = '%s';
		}

		return false !== $wpdb->update( Installer::table_names()['payments'], $data, array( 'id' => $payment_id ), $format, array( '%d' ) );
	}

	public function find_by_transaction_id( string $transaction_id ): ?object {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE transaction_id = %s',
				Installer::table_names()['payments'],
				$transaction_id
			)
		);

		return $row ?: null;
	}
}
