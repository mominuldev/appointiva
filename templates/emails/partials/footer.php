<?php
/**
 * Shared email footer. Expects $site_name in scope.
 *
 * @package Appointiva
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
	</div>
	<p class="appointiva-email-footer">
		<?php
		printf(
			/* translators: %s: site name */
			esc_html__( 'This email was sent by %s.', 'appointiva' ),
			esc_html( $site_name ?? get_bloginfo( 'name' ) )
		);
		?>
	</p>
</div>
</body>
</html>
