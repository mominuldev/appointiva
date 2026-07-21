<?php
/**
 * Public REST endpoints backing the frontend booking widget: listing
 * bookable services/slots and submitting a booking. Every route here is
 * intentionally anonymous-accessible (`__return_true`) because the booking
 * widget itself is used by signed-out visitors — the same trust model as a
 * public contact form. No `wp_rest` nonce is embedded in page output for
 * these routes, since one would add no real protection here and risks the
 * cached-page/cross-visitor nonce-mismatch bug on pages served from a page
 * cache.
 *
 * @package Appointiva
 */

namespace Appointiva\Rest;

use Appointiva\Booking\Availability;
use Appointiva\Booking\Booking_Manager;
use Appointiva\Database\Installer;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Public_Controller {

	private const NAMESPACE = 'appointiva/v1';

	public function __construct( private Availability $availability, private Booking_Manager $bookings ) {}

	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/services',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_services' ),
				'permission_callback' => '__return_true', // Public catalog data, no PII involved.
				'args'                => array(
					'lang' => array( 'required' => false, 'type' => 'string' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/slots',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_slots' ),
				'permission_callback' => '__return_true', // Availability is public information shown before any booking exists.
				'args'                => array(
					'service_id' => array( 'required' => true, 'type' => 'integer' ),
					'from'       => array( 'required' => true, 'type' => 'string' ),
					'to'         => array( 'required' => true, 'type' => 'string' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/bookings',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_booking' ),
				'permission_callback' => '__return_true', // The booking form is filled in by anonymous visitors by design.
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/booking-response',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'booking_response' ),
				'permission_callback' => '__return_true', // Reached from an emailed accept/decline link; the token param is the auth.
			)
		);
	}

	public function list_services( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, name, description, duration_minutes, buffer_after_minutes, booking_mode, available_from, available_until, min_lead_days, price, currency, status FROM %i WHERE status = %s ORDER BY sort_order ASC',
				Installer::table_names()['services'],
				'active'
			)
		);

		$locale = sanitize_text_field( (string) $request->get_param( 'lang' ) );

		/**
		 * Filters the public services list. Primarily so a translation add-on
		 * can localize name/description for the requested locale — Free's own
		 * output is unchanged when nothing hooks this (the $locale param is
		 * simply unused). Free ships no locale-switching mechanism of its own;
		 * $locale is whatever the caller passed via ?lang=, e.g. the widget's
		 * own site locale, or a value a multilingual plugin/theme supplies.
		 *
		 * @param object[] $rows   Service rows, as queried.
		 * @param string   $locale Requested locale, may be empty.
		 */
		$rows = apply_filters( 'appointiva_public_services', $rows, $locale );

		return new WP_REST_Response( array_values( $rows ) );
	}

	public function get_slots( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$service_id = (int) $request->get_param( 'service_id' );
		$from       = sanitize_text_field( (string) $request->get_param( 'from' ) );
		$to         = sanitize_text_field( (string) $request->get_param( 'to' ) );

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) ) {
			return new WP_Error( 'appointiva_invalid_range', __( 'Invalid date range.', 'appointiva' ), array( 'status' => 400 ) );
		}

		return new WP_REST_Response( $this->availability->get_slots( $service_id, $from, $to ) );
	}

	public function create_booking( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		// Honeypot: a hidden field a human never fills in; bots that autofill every input trip it.
		if ( ! empty( $request->get_param( 'website' ) ) ) {
			return new WP_Error( 'appointiva_rejected', __( 'Submission rejected.', 'appointiva' ), array( 'status' => 400 ) );
		}

		$required = array( 'service_id', 'starts_at', 'first_name', 'email', 'gdpr_consent' );

		foreach ( $required as $field ) {
			if ( null === $request->get_param( $field ) || '' === $request->get_param( $field ) ) {
				return new WP_Error(
					'appointiva_missing_field',
					/* translators: %s: field name */
					sprintf( __( 'Missing required field: %s', 'appointiva' ), $field ),
					array( 'status' => 400 )
				);
			}
		}

		if ( ! is_email( (string) $request->get_param( 'email' ) ) ) {
			return new WP_Error( 'appointiva_invalid_email', __( 'Please provide a valid email address.', 'appointiva' ), array( 'status' => 400 ) );
		}

		$result = $this->bookings->create_booking(
			array(
				'service_id'   => (int) $request->get_param( 'service_id' ),
				'starts_at'    => sanitize_text_field( (string) $request->get_param( 'starts_at' ) ),
				'first_name'   => sanitize_text_field( (string) $request->get_param( 'first_name' ) ),
				'last_name'    => sanitize_text_field( (string) $request->get_param( 'last_name' ) ),
				'email'        => sanitize_email( (string) $request->get_param( 'email' ) ),
				'phone'        => sanitize_text_field( (string) $request->get_param( 'phone' ) ),
				'notes'        => sanitize_textarea_field( (string) $request->get_param( 'notes' ) ),
				'gdpr_consent' => (bool) $request->get_param( 'gdpr_consent' ),
				'source'       => 'widget',
			)
		);

		if ( ! $result->ok ) {
			return new WP_Error( 'appointiva_' . $result->error_code, $result->error_message, array( 'status' => 422 ) );
		}

		return new WP_REST_Response(
			array(
				'uuid'      => $result->data->uuid,
				'status'    => $result->data->status,
				'starts_at' => $result->data->starts_at,
			),
			201
		);
	}

	/**
	 * Landing page for the Accept/Decline links in an offer email. Renders a
	 * small standalone HTML page and exits, rather than returning JSON —
	 * this is clicked directly from an email client, not called by the
	 * widget's JS. Exiting here means WP_REST_Server::serve_request() never
	 * regains control to wrap the output as JSON, the same "nothing runs
	 * after this" contract wp_die() relies on.
	 */
	public function booking_response( WP_REST_Request $request ): never {
		$uuid   = sanitize_text_field( (string) $request->get_param( 'uuid' ) );
		$token  = sanitize_text_field( (string) $request->get_param( 'token' ) );
		$action = sanitize_key( (string) $request->get_param( 'action' ) );

		if ( ! preg_match( '/^[0-9a-f-]{36}$/i', $uuid ) || '' === $token || ! in_array( $action, array( 'accept', 'decline' ), true ) ) {
			$this->render_response_page( __( 'Invalid link', 'appointiva' ), __( 'This link is missing required information.', 'appointiva' ), 400 );
		}

		$result = $this->bookings->respond_to_offer( $uuid, $token, $action );

		if ( ! $result->ok ) {
			$status_map = array(
				'not_found'          => 404,
				'invalid_token'      => 403,
				'invalid_action'     => 400,
				'invalid_transition' => 409,
			);

			$this->render_response_page(
				__( 'This link can no longer be used', 'appointiva' ),
				$result->error_message,
				$status_map[ $result->error_code ] ?? 400
			);
		}

		$title   = 'accept' === $action ? __( 'Booking confirmed', 'appointiva' ) : __( 'Booking declined', 'appointiva' );
		$message = 'accept' === $action
			? __( 'Thanks — your appointment is confirmed. You can close this page.', 'appointiva' )
			: __( "You've declined this offer. You can close this page.", 'appointiva' );

		$this->render_response_page( $title, $message, 200 );
	}

	private function render_response_page( string $title, string $message, int $status ): never {
		nocache_headers();
		status_header( $status );
		header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );

		printf(
			'<!DOCTYPE html><html %s><head><meta charset="%s"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>%s</title>
			<style>body{margin:0;padding:48px 16px;background:#f4f5f7;color:#1f2933;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;}
			.card{max-width:420px;margin:0 auto;background:#fff;border:1px solid #e4e7eb;border-radius:8px;padding:32px;text-align:center;}
			h1{font-size:20px;margin:0 0 12px;}p{margin:0;color:#52606d;}</style></head>
			<body><div class="card"><h1>%s</h1><p>%s</p></div></body></html>',
			get_language_attributes(),
			esc_attr( get_bloginfo( 'charset' ) ),
			esc_html( $title ),
			esc_html( $title ),
			esc_html( $message )
		);

		exit;
	}
}
