<?php
/**
 * Plugin bootstrap and lifecycle.
 *
 * @package CalcForge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires the plugin components together and owns activation and deactivation.
 *
 * Each component registers its own hooks, so this class only decides which
 * components load and in what order. Keeping the global namespace limited to one
 * class is what lets the main plugin file stay a pure loader.
 */
final class CalcForge_Plugin {

	/**
	 * The single instance of this class.
	 *
	 * @var CalcForge_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Instantiates the plugin and wires up its components.
	 */
	private function __construct() {
		$this->init_components();
	}

	/**
	 * Retrieves the singleton instance.
	 *
	 * @return CalcForge_Plugin The plugin instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Boots every component; each one registers its own hooks.
	 */
	private function init_components() {
		$components = array(
			'CalcForge_I18n',
			'CalcForge_Assets',
			'CalcForge_Blocks',
			'CalcForge_REST',
			'CalcForge_Shortcode',
		);

		foreach ( $components as $class ) {
			$component = new $class();
			$component->register_hooks();
		}

		// The settings screen is admin-only, so it never loads on the front end.
		if ( is_admin() ) {
			$settings = new CalcForge_Settings();
			$settings->register_hooks();
		}
	}

	/**
	 * Seeds the settings option on activation.
	 */
	public static function activate() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		self::migrate_legacy_settings();

		if ( false === get_option( CalcForge_Settings::OPTION_NAME, false ) ) {
			add_option( CalcForge_Settings::OPTION_NAME, calcforge_get_default_settings() );
		}
	}

	/**
	 * Carries settings saved by the pre-rename build over to the current option.
	 *
	 * The plugin was renamed from "MT Gutenberg Blocks" to "CalcForge", which
	 * included renaming the settings option from `mtgb_settings` to
	 * `calcforge_settings`. Migrating on activation keeps site-wide defaults that
	 * were already configured instead of silently reverting them.
	 */
	private static function migrate_legacy_settings() {
		$legacy = get_option( 'mtgb_settings', false );

		if ( ! is_array( $legacy ) ) {
			return;
		}

		// Never clobber settings already saved under the current option name.
		if ( false === get_option( CalcForge_Settings::OPTION_NAME, false ) ) {
			update_option( CalcForge_Settings::OPTION_NAME, $legacy );
		}

		delete_option( 'mtgb_settings' );
	}

	/**
	 * Runs on deactivation.
	 *
	 * Nothing needs tearing down yet; kept as the single place where future
	 * teardown logic belongs.
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
		_doing_it_wrong(
			__FUNCTION__,
			esc_html__( 'Unserializing CalcForge_Plugin is not allowed.', CALCFORGE_TEXT_DOMAIN ),
			'1.0.0'
		);
	}
}
