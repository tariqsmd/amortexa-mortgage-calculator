<?php
/**
 * Plugin bootstrap and lifecycle.
 *
 * @package Amortexa
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
final class Amortexa_Plugin {

	/**
	 * The single instance of this class.
	 *
	 * @var Amortexa_Plugin|null
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
	 * @return Amortexa_Plugin The plugin instance.
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
			'Amortexa_I18n',
			'Amortexa_Assets',
			'Amortexa_Blocks',
			'Amortexa_REST',
			'Amortexa_Shortcode',
		);

		foreach ( $components as $class ) {
			$component = new $class();
			$component->register_hooks();
		}

		/*
		 * The widget is registered unconditionally, including on the widgets
		 * admin screen and in the customizer, which are both admin requests but
		 * still need the widget to exist.
		 */
		add_action( 'widgets_init', array( 'Amortexa_Widget', 'register' ) );

		// The settings screen is admin-only, so it never loads on the front end.
		if ( is_admin() ) {
			$settings = new Amortexa_Settings();
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

		if ( false === get_option( Amortexa_Settings::OPTION_NAME, false ) ) {
			add_option( Amortexa_Settings::OPTION_NAME, amortexa_get_default_settings() );
		}
	}

	/**
	 * Carries settings saved by earlier builds over to the current option.
	 *
	 * The plugin has been renamed twice: "MT Gutenberg Blocks" stored settings
	 * under `mtgb_settings`, and "CalcForge" under `calcforge_settings`. Both are
	 * migrated on activation so site-wide defaults that were already configured
	 * survive the rename instead of silently reverting.
	 */
	private static function migrate_legacy_settings() {
		foreach ( array( 'calcforge_settings', 'mtgb_settings' ) as $legacy_name ) {
			$legacy = get_option( $legacy_name, false );

			if ( ! is_array( $legacy ) ) {
				continue;
			}

			// Never clobber settings already saved under the current option name.
			if ( false === get_option( Amortexa_Settings::OPTION_NAME, false ) ) {
				update_option( Amortexa_Settings::OPTION_NAME, $legacy );
			}

			delete_option( $legacy_name );
		}
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
			esc_html__( 'Unserializing Amortexa_Plugin is not allowed.', AMORTEXA_TEXT_DOMAIN ),
			'1.0.0'
		);
	}
}
