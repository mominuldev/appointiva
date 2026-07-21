<?php
/**
 * One-way (push only) Google Calendar sync: every confirmed booking creates
 * an event on the connected calendar. Talks to the Calendar and OAuth REST
 * APIs directly via wp_remote_* rather than bundling google/apiclient.
 *
 * @package Appointiva
 */

namespace Appointiva\Integrations;

use Appointiva\Booking\Status;
use Appointiva\Database\Settings_Repository;
use Appointiva\Support\Encryption;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Google_Calendar {

	private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
	private const EVENTS_URL = 'https://www.googleapis.com/calendar/v3/calendars/%s/events';
	// Not just calendar.events: Appointiva Pro's two-way sync add-on reuses this
	// same connection to query the freeBusy API, which calendar.events alone
	// does not grant access to.
	private const SCOPE = 'https://www.googleapis.com/auth/calendar';

	public function register_hooks(): void {
		add_action( 'appointiva_after_booking_created', array( $this, 'maybe_push_event' ) );
		add_action( 'appointiva_booking_status_changed', array( $this, 'maybe_push_event_on_confirm' ), 10, 3 );
	}

	public function is_connected(): bool {
		return '' !== $this->refresh_token();
	}

	public function get_authorize_url( string $redirect_uri ): string {
		$settings = Settings_Repository::get( 'google_calendar', array() );

		if ( empty( $settings['client_id'] ) ) {
			return '';
		}

		// add_query_arg() does not URL-encode its values (see
		// _http_build_query()'s $urlencode=false in WP core) — callers are
		// expected to encode anything that isn't already known-safe. This
		// matters here because $redirect_uri legitimately contains its own
		// '?'/'&' (Pro's OAuth handler lives at admin.php?page=...), which
		// would otherwise corrupt the outer query string.
		return add_query_arg(
			array(
				'client_id'              => rawurlencode( $settings['client_id'] ),
				'redirect_uri'           => rawurlencode( $redirect_uri ),
				'response_type'          => 'code',
				'access_type'            => 'offline',
				'prompt'                 => 'consent',
				'scope'                  => rawurlencode( self::SCOPE ),
			),
			'https://accounts.google.com/o/oauth2/v2/auth'
		);
	}

	/**
	 * Exchanges an OAuth authorization code for tokens and stores the
	 * refresh token (encrypted). Called from the admin REST controller after
	 * the merchant completes the Google consent screen.
	 */
	public function handle_oauth_callback( string $code, string $redirect_uri ): bool {
		$settings = Settings_Repository::get( 'google_calendar', array() );

		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'body' => array(
					'code'          => $code,
					'client_id'     => $settings['client_id'] ?? '',
					'client_secret' => Encryption::decrypt( $settings['client_secret_encrypted'] ?? '' ),
					'redirect_uri'  => $redirect_uri,
					'grant_type'    => 'authorization_code',
				),
			)
		);

		$body = $this->decode( $response );

		if ( empty( $body['refresh_token'] ) ) {
			return false;
		}

		$settings['refresh_token_encrypted'] = Encryption::encrypt( $body['refresh_token'] );
		Settings_Repository::set( 'google_calendar', $settings, true );

		return true;
	}

	public function maybe_push_event_on_confirm( object $booking, Status $from, Status $to ): void {
		if ( Status::CONFIRMED === $to ) {
			$this->push_event( $booking );
		}
	}

	public function maybe_push_event( object $booking ): void {
		if ( Status::CONFIRMED->value === $booking->status ) {
			$this->push_event( $booking );
		}
	}

	private function push_event( object $booking ): void {
		if ( ! $this->is_connected() ) {
			return;
		}

		$access_token = $this->get_access_token();

		if ( ! $access_token ) {
			return;
		}

		$settings   = Settings_Repository::get( 'google_calendar', array() );
		$calendar_id = ! empty( $settings['calendar_id'] ) ? $settings['calendar_id'] : 'primary';

		/**
		 * Filters the Google Calendar event payload before it's pushed.
		 *
		 * @param array  $event   Calendar API event resource.
		 * @param object $booking Booking being synced.
		 */
		$event = apply_filters(
			'appointiva_google_calendar_event',
			array(
				'summary'     => sprintf( /* translators: %s: booking reference */ __( 'Appointment #%s', 'appointiva' ), strtoupper( substr( $booking->uuid, 0, 8 ) ) ),
				'description' => $booking->notes ?: '',
				'start'       => array( 'dateTime' => gmdate( 'c', strtotime( $booking->starts_at . ' UTC' ) ) ),
				'end'         => array( 'dateTime' => gmdate( 'c', strtotime( $booking->ends_at . ' UTC' ) ) ),
			),
			$booking
		);

		$response = wp_remote_post(
			sprintf( self::EVENTS_URL, rawurlencode( $calendar_id ) ),
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $event ),
			)
		);

		$body = $this->decode( $response );

		if ( empty( $body['id'] ) ) {
			return;
		}

		/**
		 * Fires after a booking's event was successfully created on the
		 * connected Google Calendar. Free itself does nothing with this (the
		 * push is fire-and-forget); a two-way sync add-on can use it to
		 * remember which Google event belongs to which booking, e.g. to
		 * delete the event if the booking is later cancelled.
		 *
		 * @param object $booking     Booking that was just synced.
		 * @param string $event_id    The Google Calendar event id.
		 * @param string $calendar_id The calendar it was created on.
		 */
		do_action( 'appointiva_google_calendar_event_pushed', $booking, $body['id'], $calendar_id );
	}

	private function get_access_token(): string {
		$settings = Settings_Repository::get( 'google_calendar', array() );

		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'body' => array(
					'refresh_token' => $this->refresh_token(),
					'client_id'     => $settings['client_id'] ?? '',
					'client_secret' => Encryption::decrypt( $settings['client_secret_encrypted'] ?? '' ),
					'grant_type'    => 'refresh_token',
				),
			)
		);

		$body = $this->decode( $response );

		return (string) ( $body['access_token'] ?? '' );
	}

	private function refresh_token(): string {
		$settings = Settings_Repository::get( 'google_calendar', array() );

		return Encryption::decrypt( $settings['refresh_token_encrypted'] ?? '' );
	}

	private function decode( $response ): array {
		if ( is_wp_error( $response ) ) {
			return array();
		}

		return (array) json_decode( wp_remote_retrieve_body( $response ), true );
	}
}
