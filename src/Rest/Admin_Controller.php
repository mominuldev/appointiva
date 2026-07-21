<?php
/**
 * Authenticated REST endpoints backing the wp-admin React app. Every route
 * requires `manage_options` (checked via permission_callback) and relies on
 * WordPress core's cookie + X-WP-Nonce verification for CSRF protection —
 * appropriate here because, unlike the public widget endpoints, these are
 * only ever called from a logged-in admin screen.
 *
 * @package Appointiva
 */

namespace Appointiva\Rest;

use Appointiva\Admin\Admin;
use Appointiva\Booking\Booking_Manager;
use Appointiva\Booking\Status;
use Appointiva\Database\Installer;
use Appointiva\Database\Settings_Repository;
use Appointiva\Integrations\Google_Calendar;
use Appointiva\Notifications\Smtp_Settings;
use Appointiva\Support\Encryption;
use Appointiva\Support\General_Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin_Controller {

	private const NAMESPACE = 'appointiva/v1';

	public function __construct(
		private Booking_Manager $bookings,
		private Smtp_Settings $smtp,
		private Google_Calendar $google_calendar
	) {}

	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'admin_init', array( $this, 'maybe_handle_google_calendar_oauth' ) );
	}

	public function register_routes(): void {
		$permission = array( $this, 'check_permission' );

		register_rest_route( self::NAMESPACE, '/admin/stats', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_stats' ),
			'permission_callback' => $permission,
		) );

		$this->register_crud_routes( 'services', $permission );
		$this->register_crud_routes( 'staff', $permission );
		$this->register_crud_routes( 'locations', $permission );

		register_rest_route( self::NAMESPACE, '/admin/availability/recurring', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_recurring_availability' ),
				'permission_callback' => $permission,
			),
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'save_recurring_availability' ),
				'permission_callback' => $permission,
			),
		) );

		register_rest_route( self::NAMESPACE, '/admin/bookings', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'list_bookings' ),
			'permission_callback' => $permission,
		) );

		register_rest_route( self::NAMESPACE, '/admin/bookings/(?P<id>\d+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_booking' ),
				'permission_callback' => $permission,
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_booking' ),
				'permission_callback' => $permission,
			),
		) );

		register_rest_route( self::NAMESPACE, '/admin/bookings/(?P<id>\d+)/status', array(
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => array( $this, 'update_booking_status' ),
			'permission_callback' => $permission,
		) );

		register_rest_route( self::NAMESPACE, '/admin/bookings/(?P<id>\d+)/offer', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'send_booking_offer' ),
			'permission_callback' => $permission,
		) );

		foreach ( array( 'smtp', 'stripe', 'paypal', 'google-calendar', 'general' ) as $group ) {
			register_rest_route( self::NAMESPACE, '/admin/settings/' . $group, array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => fn( WP_REST_Request $r ) => $this->get_settings( $group ),
					'permission_callback' => $permission,
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => fn( WP_REST_Request $r ) => $this->save_settings( $group, $r ),
					'permission_callback' => $permission,
				),
			) );
		}

		// Connect/callback are deliberately NOT REST routes: both are reached by
		// a plain browser navigation (the "Connect" link, and Google's own
		// redirect back), never by fetch(), and WordPress's REST cookie auth
		// (rest_cookie_check_errors()) resets the current user to a guest on
		// any cookie-authenticated request that doesn't carry an X-WP-Nonce
		// header/param — which a plain navigation never does. See
		// maybe_handle_google_calendar_oauth(), hooked to admin_init instead.
		register_rest_route( self::NAMESPACE, '/admin/google-calendar/disconnect', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'disconnect_google_calendar' ),
			'permission_callback' => $permission,
		) );
	}

	public function check_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	private function register_crud_routes( string $resource, callable $permission ): void {
		register_rest_route( self::NAMESPACE, '/admin/' . $resource, array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => fn( WP_REST_Request $r ) => $this->list_rows( $resource ),
				'permission_callback' => $permission,
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => fn( WP_REST_Request $r ) => $this->create_row( $resource, $r ),
				'permission_callback' => $permission,
			),
		) );

		register_rest_route( self::NAMESPACE, '/admin/' . $resource . '/(?P<id>\d+)', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => fn( WP_REST_Request $r ) => $this->update_row( $resource, $r ),
				'permission_callback' => $permission,
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => fn( WP_REST_Request $r ) => $this->delete_row( $resource, $r ),
				'permission_callback' => $permission,
			),
		) );
	}

	// --- Generic resource CRUD (services, staff, locations) -----------------

	private const RESOURCE_FIELDS = array(
		'services'  => array(
			'name'                 => 's',
			'description'          => 's',
			'image_id'             => 'd',
			'duration_minutes'     => 'd',
			'buffer_after_minutes' => 'd',
			'booking_mode'         => 'k',
			'available_from'       => 's',
			'available_until'      => 's',
			'min_lead_days'        => 'd',
			'price'                => 'f',
			'currency'             => 's',
			'status'               => 's',
		),
		'staff'     => array( 'display_name' => 's', 'email' => 's', 'phone' => 's' ),
		'locations' => array( 'name' => 's', 'address' => 's', 'phone' => 's' ),
	);

	private function table( string $resource ): string {
		return Installer::table_names()[ $resource ];
	}

	private function list_rows( string $resource ): WP_REST_Response {
		global $wpdb;

		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC', $this->table( $resource ) ) );

		return new WP_REST_Response( array_values( $rows ) );
	}

	private function create_row( string $resource, WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$data = $this->sanitize_fields( $resource, $request );
		$now  = current_time( 'mysql' );

		if ( 'services' === $resource ) {
			$data['slug']       = sanitize_title( $data['name'] );
			$data['status']     = $data['status'] ?? 'active';
			$data['created_at'] = $now;
			$data['updated_at'] = $now;
		} elseif ( 'staff' === $resource ) {
			$data['status']     = 'active';
			$data['created_at'] = $now;
			$data['updated_at'] = $now;
		} elseif ( 'locations' === $resource ) {
			$data['status']     = 'active';
			$data['timezone']   = wp_timezone_string();
			$data['created_at'] = $now;
			$data['updated_at'] = $now;
		}

		$wpdb->insert( $this->table( $resource ), $data );

		return new WP_REST_Response( array( 'id' => (int) $wpdb->insert_id ), 201 );
	}

	private function update_row( string $resource, WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$id   = (int) $request->get_param( 'id' );
		$data = $this->sanitize_fields( $resource, $request );

		if ( empty( $data ) ) {
			return new WP_Error( 'appointiva_no_fields', __( 'No valid fields provided.', 'appointiva' ), array( 'status' => 400 ) );
		}

		$data['updated_at'] = current_time( 'mysql' );

		$wpdb->update( $this->table( $resource ), $data, array( 'id' => $id ) );

		return new WP_REST_Response( array( 'id' => $id ) );
	}

	private function delete_row( string $resource, WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$id = (int) $request->get_param( 'id' );

		$wpdb->delete( $this->table( $resource ), array( 'id' => $id ), array( '%d' ) );

		return new WP_REST_Response( array( 'deleted' => true ) );
	}

	private function sanitize_fields( string $resource, WP_REST_Request $request ): array {
		$fields = self::RESOURCE_FIELDS[ $resource ] ?? array();
		$data   = array();

		foreach ( $fields as $field => $type ) {
			$value = $request->get_param( $field );

			if ( null === $value ) {
				continue;
			}

			$data[ $field ] = match ( $type ) {
				'd'     => absint( $value ),
				'f'     => (float) $value,
				'k'     => sanitize_key( (string) $value ),
				default => sanitize_text_field( (string) $value ),
			};
		}

		return $data;
	}

	// --- Stats ---------------------------------------------------------------

	public function get_stats(): WP_REST_Response {
		global $wpdb;
		$bookings = $this->table( 'bookings' );

		$today       = gmdate( 'Y-m-d' );
		$week_ahead  = gmdate( 'Y-m-d', time() + 7 * DAY_IN_SECONDS );
		$month_start = gmdate( 'Y-m-01' );
		$month_end   = gmdate( 'Y-m-t' );

		$calendar_rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT DATE(starts_at) AS day, COUNT(*) AS total FROM %i WHERE starts_at BETWEEN %s AND %s GROUP BY DATE(starts_at)',
				$bookings,
				$month_start . ' 00:00:00',
				$month_end . ' 23:59:59'
			)
		);

		$calendar = array();
		foreach ( $calendar_rows as $row ) {
			$calendar[ $row->day ] = (int) $row->total;
		}

		return new WP_REST_Response(
			array(
				'today'      => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE DATE(starts_at) = %s', $bookings, $today ) ),
				'upcoming'   => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE starts_at BETWEEN %s AND %s', $bookings, $today, $week_ahead ) ),
				'pending'    => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s', $bookings, Status::PENDING->value ) ),
				'this_month' => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE starts_at >= %s', $bookings, $month_start ) ),
				'calendar'   => $calendar,
			)
		);
	}

	// --- Availability ----------------------------------------------------------

	public function get_recurring_availability(): WP_REST_Response {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT day_of_week, is_available, start_time, end_time FROM %i WHERE type = %s AND staff_id IS NULL ORDER BY day_of_week ASC", $this->table( 'availability' ), 'recurring' )
		);

		return new WP_REST_Response( $rows );
	}

	public function save_recurring_availability( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;
		$week = $request->get_param( 'week' );

		if ( ! is_array( $week ) ) {
			return new WP_Error( 'appointiva_invalid_payload', __( 'Invalid availability payload.', 'appointiva' ), array( 'status' => 400 ) );
		}

		$table = $this->table( 'availability' );

		$wpdb->delete( $table, array( 'type' => 'recurring', 'staff_id' => null ) );

		foreach ( $week as $day ) {
			if ( empty( $day['is_available'] ) ) {
				continue;
			}

			$wpdb->insert(
				$table,
				array(
					'type'         => 'recurring',
					'day_of_week'  => absint( $day['day_of_week'] ),
					'start_time'   => sanitize_text_field( $day['start_time'] ),
					'end_time'     => sanitize_text_field( $day['end_time'] ),
					'is_available' => 1,
					'created_at'   => current_time( 'mysql' ),
					'updated_at'   => current_time( 'mysql' ),
				)
			);
		}

		return new WP_REST_Response( array( 'saved' => true ) );
	}

	// --- Bookings --------------------------------------------------------------

	public function list_bookings( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;
		$t = Installer::table_names();

		$where  = array( '1 = 1' );
		$values = array();

		$status = sanitize_key( (string) $request->get_param( 'status' ) );
		if ( $status && Status::tryFrom( $status ) ) {
			$where[]  = 'b.status = %s';
			$values[] = $status;
		}

		$service_id = (int) $request->get_param( 'service_id' );
		if ( $service_id > 0 ) {
			$where[]  = 'b.service_id = %d';
			$values[] = $service_id;
		}

		$date_from = sanitize_text_field( (string) $request->get_param( 'date_from' ) );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ) {
			$where[]  = 'b.starts_at >= %s';
			$values[] = $date_from . ' 00:00:00';
		}

		$date_to = sanitize_text_field( (string) $request->get_param( 'date_to' ) );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
			$where[]  = 'b.starts_at <= %s';
			$values[] = $date_to . ' 23:59:59';
		}

		$search = sanitize_text_field( (string) $request->get_param( 'search' ) );
		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = "(CONCAT(c.first_name, ' ', c.last_name) LIKE %s OR c.email LIKE %s)";
			$values[] = $like;
			$values[] = $like;
		}

		$per_page = min( 100, max( 1, (int) ( $request->get_param( 'per_page' ) ?: 25 ) ) );
		$page     = max( 1, (int) ( $request->get_param( 'page' ) ?: 1 ) );
		$offset   = ( $page - 1 ) * $per_page;

		$sort_column = 'created_at' === $request->get_param( 'sort' ) ? 'b.created_at' : 'b.starts_at';

		$where_sql = implode( ' AND ', $where );

		$from_sql = "FROM %i b
			LEFT JOIN %i c ON c.id = b.customer_id
			LEFT JOIN %i s ON s.id = b.service_id
			WHERE {$where_sql}";

		// $from_sql is assembled entirely from static text and %i/%s/%d
		// placeholder tokens above — every real value still flows through
		// $wpdb->prepare()'s own argument list below, never concatenated in.
		$total = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) {$from_sql}", $t['bookings'], $t['customers'], $t['services'], ...$values ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		);

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.id, b.uuid, b.status, b.starts_at, b.price, b.currency, b.created_at,
					CONCAT(c.first_name, ' ', c.last_name) AS customer_name, s.name AS service_name
				{$from_sql}
				ORDER BY {$sort_column} DESC
				LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				...array( $t['bookings'], $t['customers'], $t['services'], ...$values, $per_page, $offset )
			)
		);

		return new WP_REST_Response(
			array(
				'items' => $rows,
				'total' => $total,
			)
		);
	}

	public function get_booking( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;
		$t  = Installer::table_names();
		$id = (int) $request->get_param( 'id' );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT b.id, b.uuid, b.status, b.starts_at, b.ends_at, b.price, b.currency, b.notes, b.admin_notes, b.created_at, b.updated_at,
					CONCAT(c.first_name, ' ', c.last_name) AS customer_name, c.email AS customer_email, c.phone AS customer_phone,
					s.name AS service_name
				FROM %i b
				LEFT JOIN %i c ON c.id = b.customer_id
				LEFT JOIN %i s ON s.id = b.service_id
				WHERE b.id = %d",
				$t['bookings'],
				$t['customers'],
				$t['services'],
				$id
			)
		);

		if ( ! $row ) {
			return new WP_Error( 'appointiva_not_found', __( 'Booking not found.', 'appointiva' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response( $row );
	}

	public function update_booking_status( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id     = (int) $request->get_param( 'id' );
		$status = sanitize_key( (string) $request->get_param( 'status' ) );

		$status_enum = Status::tryFrom( $status );

		if ( ! $status_enum ) {
			return new WP_Error( 'appointiva_invalid_status', __( 'Invalid status.', 'appointiva' ), array( 'status' => 400 ) );
		}

		$result = $this->bookings->change_status( $id, $status_enum );

		if ( ! $result->ok ) {
			return new WP_Error( 'appointiva_' . $result->error_code, $result->error_message, array( 'status' => 422 ) );
		}

		return new WP_REST_Response( array( 'status' => $result->data->status ) );
	}

	public function send_booking_offer( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id         = (int) $request->get_param( 'id' );
		$price      = (float) $request->get_param( 'price' );
		$admin_note = sanitize_textarea_field( (string) $request->get_param( 'admin_note' ) );

		$result = $this->bookings->send_offer( $id, $price, $admin_note );

		if ( ! $result->ok ) {
			return new WP_Error( 'appointiva_' . $result->error_code, $result->error_message, array( 'status' => 422 ) );
		}

		return new WP_REST_Response( array( 'status' => $result->data->status ) );
	}

	public function delete_booking( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = (int) $request->get_param( 'id' );

		$result = $this->bookings->delete_booking( $id );

		if ( ! $result->ok ) {
			return new WP_Error( 'appointiva_' . $result->error_code, $result->error_message, array( 'status' => 404 ) );
		}

		return new WP_REST_Response( array( 'deleted' => true ) );
	}

	// --- Settings ----------------------------------------------------------------

	private function get_settings( string $group ): WP_REST_Response {
		if ( 'general' === $group ) {
			return new WP_REST_Response(
				array_merge(
					General_Settings::all(),
					array(
						'reminder_lead_hours'      => (int) apply_filters( 'appointiva_reminder_lead_hours', 24 ),
						'delete_data_on_uninstall' => (bool) get_option( 'appointiva_delete_data_on_uninstall', false ),
					)
				)
			);
		}

		$key      = str_replace( '-', '_', $group );
		$settings = Settings_Repository::get( $key, array() );

		$settings['configured'] = ! empty( $settings['secret_key_encrypted'] ) || ! empty( $settings['client_secret_encrypted'] );

		if ( 'google-calendar' === $group ) {
			$settings['connected'] = $this->google_calendar->is_connected();
		}

		unset( $settings['password_encrypted'], $settings['secret_key_encrypted'], $settings['client_secret_encrypted'], $settings['refresh_token_encrypted'] );

		return new WP_REST_Response( $settings );
	}

	private function save_settings( string $group, WP_REST_Request $request ): WP_REST_Response {
		$input = $request->get_json_params() ?? array();

		if ( 'general' === $group ) {
			update_option( 'appointiva_reminder_lead_hours', absint( $input['reminder_lead_hours'] ?? 24 ) );
			update_option( 'appointiva_delete_data_on_uninstall', ! empty( $input['delete_data_on_uninstall'] ) );
			General_Settings::save( $input );

			return new WP_REST_Response( array( 'saved' => true ) );
		}

		if ( 'smtp' === $group ) {
			$this->smtp->save( $input );

			return new WP_REST_Response( array( 'saved' => true ) );
		}

		if ( 'stripe' === $group ) {
			$existing = Settings_Repository::get( 'stripe', array() );

			Settings_Repository::set(
				'stripe',
				array(
					'enabled'              => ! empty( $input['enabled'] ),
					'publishable_key'      => sanitize_text_field( $input['publishable_key'] ?? '' ),
					'secret_key_encrypted' => ! empty( $input['secret_key'] ) ? Encryption::encrypt( $input['secret_key'] ) : ( $existing['secret_key_encrypted'] ?? '' ),
				),
				true
			);

			return new WP_REST_Response( array( 'saved' => true ) );
		}

		if ( 'paypal' === $group ) {
			$existing = Settings_Repository::get( 'paypal', array() );

			Settings_Repository::set(
				'paypal',
				array(
					'enabled'                 => ! empty( $input['enabled'] ),
					'sandbox'                 => ! empty( $input['sandbox'] ),
					'client_id'               => sanitize_text_field( $input['client_id'] ?? '' ),
					'client_secret_encrypted' => ! empty( $input['client_secret'] ) ? Encryption::encrypt( $input['client_secret'] ) : ( $existing['client_secret_encrypted'] ?? '' ),
				),
				true
			);

			return new WP_REST_Response( array( 'saved' => true ) );
		}

		if ( 'google-calendar' === $group ) {
			$existing = Settings_Repository::get( 'google_calendar', array() );

			Settings_Repository::set(
				'google_calendar',
				array(
					'client_id'               => sanitize_text_field( $input['client_id'] ?? '' ),
					'client_secret_encrypted' => ! empty( $input['client_secret'] ) ? Encryption::encrypt( $input['client_secret'] ) : ( $existing['client_secret_encrypted'] ?? '' ),
					'calendar_id'             => sanitize_text_field( $input['calendar_id'] ?? 'primary' ),
					'refresh_token_encrypted' => $existing['refresh_token_encrypted'] ?? '',
				),
				true
			);

			return new WP_REST_Response( array( 'saved' => true ) );
		}

		return new WP_REST_Response( array( 'saved' => false ), 400 );
	}

	// --- Google Calendar OAuth ----------------------------------------------------
	//
	// connect/callback run as a plain admin_init handler rather than REST
	// routes: both are reached by a real browser navigation (the "Connect"
	// link, and Google's own redirect back), never by fetch(), and those
	// carry no X-WP-Nonce. WordPress's REST cookie auth
	// (rest_cookie_check_errors()) treats any cookie-authenticated REST
	// request without a nonce as anonymous — a plain navigation would 401 no
	// matter what the permission_callback says. A normal wp-admin page load
	// doesn't have that restriction, so this sidesteps it entirely. CSRF
	// protection between the redirect out and the redirect back is instead a
	// standard OAuth `state` value, itself a WP nonce.

	private const GCAL_OAUTH_STATE_ACTION = 'appointiva_google_calendar_oauth_state';

	public function maybe_handle_google_calendar_oauth(): void {
		if ( ! isset( $_GET['page'], $_GET['appointiva_gcal_action'] ) || Admin::PAGE_SLUG !== $_GET['page'] ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_GET['appointiva_gcal_action'] ) );

		if ( 'connect' === $action ) {
			$this->start_google_calendar_oauth();
		} elseif ( 'callback' === $action ) {
			$this->finish_google_calendar_oauth();
		}
	}

	/** Sends the browser to Google's consent screen. */
	private function start_google_calendar_oauth(): void {
		$url = $this->google_calendar->get_authorize_url( $this->google_calendar_redirect_uri() );

		if ( '' === $url ) {
			wp_safe_redirect( $this->google_calendar_settings_url( 'missing_client_id' ) );
			exit;
		}

		$state = wp_create_nonce( self::GCAL_OAUTH_STATE_ACTION );

		wp_redirect( add_query_arg( 'state', $state, $url ) ); // phpcs:ignore WordPress.Security.SafeRedirect -- external Google URL built from our own stored client_id, not user input.
		exit;
	}

	/** Google redirects the admin's browser back here after the consent screen. */
	private function finish_google_calendar_oauth(): void {
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';

		if ( ! wp_verify_nonce( $state, self::GCAL_OAUTH_STATE_ACTION ) ) {
			wp_safe_redirect( $this->google_calendar_settings_url( 'error' ) );
			exit;
		}

		$code      = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		$connected = '' !== $code && $this->google_calendar->handle_oauth_callback( $code, $this->google_calendar_redirect_uri() );

		wp_safe_redirect( $this->google_calendar_settings_url( $connected ? 'connected' : 'error' ) );
		exit;
	}

	public function disconnect_google_calendar(): WP_REST_Response {
		$settings = Settings_Repository::get( 'google_calendar', array() );

		$settings['refresh_token_encrypted'] = '';

		Settings_Repository::set( 'google_calendar', $settings, true );

		return new WP_REST_Response( array( 'disconnected' => true ) );
	}

	private function google_calendar_redirect_uri(): string {
		return add_query_arg(
			array(
				'page'                   => Admin::PAGE_SLUG,
				'appointiva_gcal_action' => 'callback',
			),
			admin_url( 'admin.php' )
		);
	}

	private function google_calendar_settings_url( string $status ): string {
		return add_query_arg( 'appointiva_gcal', $status, admin_url( 'admin.php?page=' . Admin::PAGE_SLUG ) );
	}
}
