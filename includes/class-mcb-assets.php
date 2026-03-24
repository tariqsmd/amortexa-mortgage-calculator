<?php
/**
 * Asset registration and conditional enqueueing.
 *
 * @package MortgageCalculatorBlock
 */

// Abort if this file is called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers editor/front-end assets and enqueues them only where needed.
 *
 * Editor assets are registered with explicit handles so block.json can refer
 * to them deterministically and JS translations can be attached. Front-end
 * assets are NOT declared in block.json; they are enqueued manually only when
 * the block is actually present, avoiding site-wide CSS/JS weight.
 */
class MCB_Assets {

	const HANDLE_EDITOR_SCRIPT = 'mcb-editor-script';
	const HANDLE_EDITOR_STYLE  = 'mcb-editor-style';
	const HANDLE_STYLE         = 'mcb-style';
	const HANDLE_VIEW          = 'mcb-view';

	/**
	 * Tracks whether the front-end bundle has been enqueued on this request.
	 *
	 * @var bool
	 */
	private static $frontend_enqueued = false;

	/**
	 * Registers the hooks this component responds to.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register_assets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_frontend_assets' ) );
	}

	/**
	 * Registers all script/style handles.
	 *
	 * Versions use MCB_VERSION for cache busting. During local development,
	 * swapping the version argument for filemtime() gives per-save busting.
	 */
	public function register_assets() {
		wp_register_script(
			self::HANDLE_EDITOR_SCRIPT,
			MCB_PLUGIN_URL . 'build/index.js',
			array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n' ),
			MCB_VERSION,
			true
		);

		wp_register_style(
			self::HANDLE_EDITOR_STYLE,
			MCB_PLUGIN_URL . 'build/index.css',
			array( 'wp-edit-blocks' ),
			MCB_VERSION
		);

		if ( file_exists( MCB_PLUGIN_DIR . 'build/style-index.css' ) ) {
			wp_register_style(
				self::HANDLE_STYLE,
				MCB_PLUGIN_URL . 'build/style-index.css',
				array(),
				MCB_VERSION
			);
		}

		if ( file_exists( MCB_PLUGIN_DIR . 'build/view.js' ) ) {
			wp_register_script(
				self::HANDLE_VIEW,
				MCB_PLUGIN_URL . 'build/view.js',
				array(),
				MCB_VERSION,
				true
			);
		}

		wp_set_script_translations( self::HANDLE_EDITOR_SCRIPT, MCB_TEXT_DOMAIN );
	}

	/**
	 * Enqueues front-end assets when the current page contains the block.
	 */
	public function maybe_enqueue_frontend_assets() {
		if ( is_admin() || self::$frontend_enqueued || ! $this->should_enqueue_assets() ) {
			return;
		}

		if ( self::page_has_block() ) {
			self::enqueue_frontend_assets();
		}
	}

	/**
	 * Fallback enqueue invoked from the render callback.
	 *
	 * Covers contexts has_block() misses (widgets, archives, future shortcode
	 * reuse). Late-enqueued scripts/styles print in the footer, which remains
	 * valid output.
	 */
	public static function enqueue_on_render() {
		if ( is_admin() || self::$frontend_enqueued || ! self::should_enqueue_assets() ) {
			return;
		}

		self::enqueue_frontend_assets();
	}

	/**
	 * Whether third parties allow this plugin to load its own assets.
	 *
	 * @return bool True unless the `mcb_enqueue_assets` filter returns false.
	 */
	private static function should_enqueue_assets() {
		/**
		 * Filters whether the plugin should enqueue its front-end assets.
		 *
		 * Return false if a theme bundles its own styles/scripts for the block.
		 *
		 * @param bool $should_enqueue True by default.
		 */
		return apply_filters( 'mcb_enqueue_assets', true );
	}

	/**
	 * Detects the block in the main post content or any active block widget.
	 *
	 * @return bool True if the block appears somewhere on the page.
	 */
	private static function page_has_block() {
		$block_name = MCB_Block_Registration::BLOCK_NAME;

		if ( is_singular() && has_block( $block_name ) ) {
			return true;
		}

		$widget_instances = get_option( 'widget_block', array() );

		foreach ( $widget_instances as $instance ) {
			if ( is_array( $instance ) && isset( $instance['content'] ) && has_block( $block_name, (string) $instance['content'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Enqueues the registered front-end handles and localizes shared data.
	 */
	private static function enqueue_frontend_assets() {
		if ( ! wp_style_is( self::HANDLE_STYLE, 'registered' ) || ! wp_script_is( self::HANDLE_VIEW, 'registered' ) ) {
			return;
		}

		wp_enqueue_style( self::HANDLE_STYLE );
		wp_enqueue_script( self::HANDLE_VIEW );

		wp_localize_script(
			self::HANDLE_VIEW,
			'mcbGlobal',
			array(
				'restRoot'  => esc_url_raw( rest_url( 'mcb/v1' ) ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
			)
		);

		self::$frontend_enqueued = true;
	}
}
