<?php
/**
 * Produces the booking widget's server-rendered markup shell. The vanilla
 * JS widget hydrates this container; nothing here requires JavaScript to be
 * present for the container itself to be valid, accessible markup.
 *
 * @package Appointiva
 */

namespace Appointiva\Frontend;

use Appointiva\Support\General_Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Widget_Renderer {

	public static function render( int $service_id = 0 ): string {
		$attrs = array(
			'class'                  => 'appointiva-widget',
			'data-appointiva-widget' => '',
			'data-appointiva-theme'  => General_Settings::get( 'theme_mode' ),
			'role'                   => 'form',
			'aria-label'             => esc_attr__( 'Appointment booking form', 'appointiva' ),
		);

		if ( $service_id > 0 ) {
			$attrs['data-service-id'] = (string) $service_id;
		}

		$attr_string = '';
		foreach ( $attrs as $name => $value ) {
			$attr_string .= '' === $value ? ' ' . esc_attr( $name ) : sprintf( ' %s="%s"', esc_attr( $name ), esc_attr( $value ) );
		}

		ob_start();
		?>
		<div<?php echo $attr_string; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $attr_string built entirely from esc_attr() above. ?>>
			<noscript>
				<p><?php esc_html_e( 'Please enable JavaScript to book an appointment, or contact us directly.', 'appointiva' ); ?></p>
			</noscript>
			<p class="appointiva-widget__loading" aria-live="polite">
				<?php esc_html_e( 'Loading booking form…', 'appointiva' ); ?>
			</p>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
