<?php
/**
 * Booking reminder email.
 *
 * @package Appointiva
 * @var object             $booking
 * @var object             $customer
 * @var DateTimeImmutable  $starts_at
 * @var string             $site_name
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

include __DIR__ . '/partials/header.php';
?>
<h1 class="appointiva-email-heading">
	<?php
	printf(
		/* translators: %s: customer first name */
		esc_html__( 'See you soon, %s', 'appointiva' ),
		esc_html( $customer->first_name )
	);
	?>
</h1>
<p><?php esc_html_e( 'This is a reminder about your upcoming appointment:', 'appointiva' ); ?></p>
<table class="appointiva-email-meta-table">
	<tr>
		<td><?php esc_html_e( 'Date & time', 'appointiva' ); ?></td>
		<td><?php echo esc_html( wp_date( \Appointiva\Support\General_Settings::get( 'date_format' ) . ' ' . get_option( 'time_format' ), $starts_at->getTimestamp() ) ); ?></td>
	</tr>
	<tr>
		<td><?php esc_html_e( 'Reference', 'appointiva' ); ?></td>
		<td><?php echo esc_html( strtoupper( substr( $booking->uuid, 0, 8 ) ) ); ?></td>
	</tr>
</table>
<?php do_action( 'appointiva_email_reminder_body', $booking, $customer ); ?>
<?php include __DIR__ . '/partials/footer.php'; ?>
