<?php
/**
 * Dependency manifest for the block editor script. Hand-written since this
 * plugin's build pipeline doesn't use @wordpress/scripts; update the
 * dependency list here if index.js starts using another @wordpress/* package.
 *
 * @package Appointiva
 */

return array(
	'dependencies' => array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ),
	'version'      => APPOINTIVA_VERSION,
);
