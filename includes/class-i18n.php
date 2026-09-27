<?php
/**
 * Translation loading.
 *
 * @package CalcForge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads the plugin text domain.
 */
class CalcForge_I18n {

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
			CALCFORGE_TEXT_DOMAIN,
			false,
			dirname( plugin_basename( CALCFORGE_PLUGIN_FILE ) ) . '/languages'
		);
	}
}
