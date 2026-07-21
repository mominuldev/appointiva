<?php
/**
 * Runs on plugin deactivation. Never deletes data — only Appointiva's own
 * behavior (cron) is cleaned up, so reactivating is loss-free.
 *
 * @package Appointiva
 */

namespace Appointiva;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Deactivator {

	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'appointiva_send_reminder_notifications' );
		wp_clear_scheduled_hook( 'appointiva_google_calendar_sync' );

		do_action( 'appointiva_deactivated' );
	}
}
