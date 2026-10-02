<?php
/**
 * Translation loading.
 *
 * @package Amortexa
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads the plugin text domain.
 */
class Amortexa_I18n {

	/**
	 * Registers the hooks this component responds to.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Loads the text domain from the /languages directory.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			AMORTEXA_TEXT_DOMAIN,
			false,
			dirname( plugin_basename( AMORTEXA_PLUGIN_FILE ) ) . '/languages'
		);
	}
}
