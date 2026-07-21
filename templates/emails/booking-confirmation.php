<?php
/**
 * Booking confirmation email.
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
		esc_html__( "You're confirmed, %s!", 'appointiva' ),
		esc_html( $customer->first_name )
	);
	?>
</h1>
<p><?php esc_html_e( 'Here are your appointment details:', 'appointiva' ); ?></p>
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
<?php
/**
 * Fires inside the booking confirmation email body, after the standard
 * details table. Pro's deposit/payment summary and self-service dashboard
 * link are added here.
 *
 * @param object $booking
 * @param object $customer
 */
do_action( 'appointiva_email_confirmation_body', $booking, $customer );

include __DIR__ . '/partials/footer.php';
