<?php
/**
 * Main plugin bootstrap class.
 *
 * Coordinates component loading and exposes activation/deactivation hooks via
 * a singleton instance, keeping the global namespace clean.
 *
 * @package MortgageCalculatorBlock
 */

// Abort if this file is called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Singleton bootstrap for the Mortgage Calculator Block plugin.
 */
final class MCB_Mortgage_Calculator_Block {

	/**
	 * The single instance of this class.
	 *
	 * @var MCB_Mortgage_Calculator_Block|null
	 */
	private static $instance = null;

	/**
	 * Instantiates the plugin and wires up its components.
	 */
	private function __construct() {
		$this->includes();
		$this->init_components();
	}

	/**
	 * Retrieves the singleton instance.
	 *
	 * @return MCB_Mortgage_Calculator_Block The plugin instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Loads the class files that make up the plugin.
	 */
	private function includes() {
		require_once MCB_PLUGIN_DIR . 'includes/helpers.php';
		require_once MCB_PLUGIN_DIR . 'includes/class-mcb-i18n.php';
		require_once MCB_PLUGIN_DIR . 'includes/class-mcb-assets.php';
		require_once MCB_PLUGIN_DIR . 'includes/class-mcb-block-registration.php';
		require_once MCB_PLUGIN_DIR . 'includes/class-mcb-rest-api.php';
		require_once MCB_PLUGIN_DIR . 'includes/class-mcb-settings.php';
	}

	/**
	 * Boots each component; components register their own hooks.
	 */
	private function init_components() {
		$i18n = new MCB_I18n();
		$i18n->register_hooks();

		$assets = new MCB_Assets();
		$assets->register_hooks();

		$blocks = new MCB_Block_Registration();
		$blocks->register_hooks();

		$rest_api = new MCB_REST_API();
		$rest_api->register_hooks();

		if ( is_admin() ) {
			$settings = new MCB_Settings();
			$settings->register_hooks();
		}
	}

	/**
	 * Activation callback: seeds the settings option with defaults.
	 */
	public static function activate() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		if ( false === get_option( 'mcb_settings', false ) ) {
			add_option( 'mcb_settings', mcb_get_default_settings() );
		}
	}

	/**
	 * Deactivation callback.
	 *
	 * No transient cleanup is required yet; kept as the single place where
	 * future teardown logic belongs.
	 */
	public static function deactivate() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
	}

	/**
	 * Prevents cloning of the singleton.
	 */
	private function __clone() {}

	/**
	 * Prevents unserializing of the singleton.
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Unserializing is not allowed.', MCB_TEXT_DOMAIN ), '1.0.0' );
	}
}
