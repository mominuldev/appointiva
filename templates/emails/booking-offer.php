<?php
/**
 * Offer email — sent when an admin moves a pending booking to 'offer_sent'.
 * Deliberately does NOT receive admin_notes: that field is an internal-only
 * note and must never be added to this template's variables.
 *
 * @package Appointiva
 * @var object             $booking
 * @var object             $customer
 * @var DateTimeImmutable  $starts_at
 * @var string             $site_name
 * @var string             $accept_url
 * @var string             $decline_url
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
		esc_html__( 'You have a new offer, %s', 'appointiva' ),
		esc_html( $customer->first_name )
	);
	?>
</h1>
<p><?php esc_html_e( 'Please review the details below and let us know if this works for you:', 'appointiva' ); ?></p>
<table class="appointiva-email-meta-table">
	<tr>
		<td><?php esc_html_e( 'Date & time', 'appointiva' ); ?></td>
		<td><?php echo esc_html( wp_date( \Appointiva\Support\General_Settings::get( 'date_format' ) . ' ' . get_option( 'time_format' ), $starts_at->getTimestamp() ) ); ?></td>
	</tr>
	<?php if ( (float) $booking->price > 0 ) : ?>
	<tr>
		<td><?php esc_html_e( 'Price', 'appointiva' ); ?></td>
		<td><?php echo esc_html( number_format_i18n( (float) $booking->price, 2 ) . ' ' . $booking->currency ); ?></td>
	</tr>
	<?php endif; ?>
	<tr>
		<td><?php esc_html_e( 'Reference', 'appointiva' ); ?></td>
		<td><?php echo esc_html( strtoupper( substr( $booking->uuid, 0, 8 ) ) ); ?></td>
	</tr>
</table>
<table style="width: 100%; margin: 24px 0;">
	<tr>
		<td style="padding-right: 8px;">
			<a href="<?php echo esc_url( $accept_url ); ?>" style="display: block; text-align: center; background-color: #4f46e5; color: #ffffff; text-decoration: none; padding: 12px 16px; border-radius: 6px; font-size: 14px; font-weight: 600;">
				<?php esc_html_e( 'Accept', 'appointiva' ); ?>
			</a>
		</td>
		<td style="padding-left: 8px;">
			<a href="<?php echo esc_url( $decline_url ); ?>" style="display: block; text-align: center; background-color: #ffffff; color: #4f46e5; text-decoration: none; padding: 12px 16px; border-radius: 6px; font-size: 14px; font-weight: 600; border: 1px solid #4f46e5;">
				<?php esc_html_e( 'Decline', 'appointiva' ); ?>
			</a>
		</td>
	</tr>
</table>
<?php
/**
 * Fires inside the offer email body, after the standard details table and
 * accept/decline buttons.
 *
 * @param object $booking
 * @param object $customer
 */
do_action( 'appointiva_email_offer_body', $booking, $customer );

include __DIR__ . '/partials/footer.php';
