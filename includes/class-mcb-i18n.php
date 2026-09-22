<?php
/**
 * Internationalization loader.
 *
 * @package MortgageCalculatorBlock
 */

// Abort if this file is called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads the plugin text domain so PHP strings are translatable.
 *
 * JavaScript strings are made translatable separately via
 * wp_set_script_translations() in mtgb_Assets.
 */
class mtgb_I18n {

	/**
	 * Registers the hooks this component responds to.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Loads the plugin text domain from the /languages directory.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			mtgb_TEXT_DOMAIN,
			false,
			dirname( plugin_basename( mtgb_PLUGIN_FILE ) ) . '/languages'
		);
	}
}
