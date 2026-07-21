<?php
/**
 * Shared email header. Expects $site_name in scope.
 *
 * @package Appointiva
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$appointiva_theme_mode = \Appointiva\Support\General_Settings::get( 'theme_mode' );

// Dark-mode declarations below. In "auto" they stay wrapped in the media query so the
// email client decides; for an explicit "dark"/"light" choice they're emitted unwrapped
// (dark) or omitted entirely (light) so the admin's choice always wins.
$appointiva_dark_rules = '
	body { background-color: #111827 !important; color: #e5e7eb !important; }
	.appointiva-email-card { background-color: #1f2937 !important; border-color: #374151 !important; }
	.appointiva-email-heading { color: #f9fafb !important; }
	.appointiva-email-meta-table td { border-color: #374151 !important; }
	.appointiva-email-meta-table td:first-child { color: #9ca3af !important; }
	.appointiva-email-footer { color: #6b7280 !important; }
';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="color-scheme" content="<?php echo esc_attr( 'auto' === $appointiva_theme_mode ? 'light dark' : $appointiva_theme_mode ); ?>">
<meta name="supported-color-schemes" content="<?php echo esc_attr( 'auto' === $appointiva_theme_mode ? 'light dark' : $appointiva_theme_mode ); ?>">
<style>
	body { margin: 0; padding: 0; background-color: #f4f5f7; color: #1f2933; font-family: -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; }
	.appointiva-email-wrap { max-width: 480px; margin: 0 auto; padding: 32px 16px; }
	.appointiva-email-card { background-color: #ffffff; border-radius: 8px; padding: 32px; border: 1px solid #e4e7eb; }
	.appointiva-email-heading { font-size: 20px; margin: 0 0 16px; color: #111827; }
	.appointiva-email-meta-table { width: 100%; border-collapse: collapse; margin: 16px 0; }
	.appointiva-email-meta-table td { padding: 8px 0; border-bottom: 1px solid #e4e7eb; font-size: 14px; }
	.appointiva-email-meta-table td:first-child { color: #6b7280; width: 40%; }
	.appointiva-email-footer { text-align: center; font-size: 12px; color: #9aa5b1; padding-top: 24px; }

	<?php if ( 'dark' === $appointiva_theme_mode ) : ?>
		<?php echo $appointiva_dark_rules; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static CSS string declared above, no user input. ?>
	<?php elseif ( 'light' !== $appointiva_theme_mode ) : ?>
	@media (prefers-color-scheme: dark) {
		<?php echo $appointiva_dark_rules; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static CSS string declared above, no user input. ?>
	}
	<?php endif; ?>
</style>
</head>
<body>
<div class="appointiva-email-wrap">
	<div class="appointiva-email-card">
