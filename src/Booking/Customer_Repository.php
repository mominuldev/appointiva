<?php
/**
 * Find-or-create access to the customers table.
 *
 * @package Appointiva
 */

namespace Appointiva\Booking;

use Appointiva\Database\Installer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Customer_Repository {

	/**
	 * @param array{first_name: string, last_name?: string, email: string, phone?: string, locale?: string, gdpr_consent?: bool} $data
	 */
	public function find_or_create( array $data ): int {
		global $wpdb;
		$table = Installer::table_names()['customers'];
		$email = sanitize_email( $data['email'] );

		$existing_id = $wpdb->get_var(
			$wpdb->prepare( 'SELECT id FROM %i WHERE email = %s', $table, $email )
		);

		$now = current_time( 'mysql' );

		if ( $existing_id ) {
			$wpdb->update(
				$table,
				array(
					'first_name' => sanitize_text_field( $data['first_name'] ),
					'last_name'  => sanitize_text_field( $data['last_name'] ?? '' ),
					'phone'      => sanitize_text_field( $data['phone'] ?? '' ),
					'locale'     => sanitize_text_field( $data['locale'] ?? '' ),
					'updated_at' => $now,
				),
				array( 'id' => (int) $existing_id ),
				array( '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);

			return (int) $existing_id;
		}

		$wpdb->insert(
			$table,
			array(
				'first_name'      => sanitize_text_field( $data['first_name'] ),
				'last_name'       => sanitize_text_field( $data['last_name'] ?? '' ),
				'email'           => $email,
				'phone'           => sanitize_text_field( $data['phone'] ?? '' ),
				'locale'          => sanitize_text_field( $data['locale'] ?? '' ),
				'gdpr_consent_at' => ! empty( $data['gdpr_consent'] ) ? $now : null,
				'created_at'      => $now,
				'updated_at'      => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	public function get( int $customer_id ): ?object {
		global $wpdb;
		$table = Installer::table_names()['customers'];

		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table, $customer_id )
		);

		return $row ?: null;
	}
}
