<?php
/**
 * Gutenberg block registration and rendering.
 *
 * @package MortgageCalculatorBlock
 */

// Abort if this file is called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the dynamic mortgage calculator block via block.json metadata.
 *
 * The block is server-rendered through a render callback so all attribute
 * values are sanitized and escaped in PHP, and so third parties can hook into
 * the calculation and markup pipeline.
 */
class MCB_Block_Registration {

	/**
	 * Fully qualified block name.
	 */
	const BLOCK_NAME = 'mcb/mortgage-calculator';

	/**
	 * Registers the hooks this component responds to.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register_block_type' ), 10 );
		add_filter( 'block_categories_all', array( $this, 'register_category' ) );
	}

	/**
	 * Registers the custom "Mortgage Tools" block category.
	 *
	 * @param array<int,array<string,mixed>> $categories Existing block categories.
	 * @return array<int,array<string,mixed>> Categories including our own.
	 */
	public function register_category( $categories ) {
		$categories[] = array(
			'slug'  => 'mcb',
			'title' => esc_html__( 'Mortgage Tools', MCB_TEXT_DOMAIN ),
			'icon'  => 'calculator',
		);

		return $categories;
	}

	/**
	 * Registers the block type from build/block.json with a render callback.
	 */
	public function register_block_type() {
		$manifest = MCB_PLUGIN_DIR . 'build/block.json';

		if ( ! file_exists( $manifest ) ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'The block build is missing. Run `npm install` followed by `npm run build` inside the plugin folder.', MCB_TEXT_DOMAIN ),
				'1.0.0'
			);
			return;
		}

		register_block_type(
			MCB_PLUGIN_DIR . 'build',
			array(
				'render_callback' => array( $this, 'render' ),
			)
		);
	}

	/**
	 * Renders the calculator on the server.
	 *
	 * Fires the before/after render actions, sanitizes every attribute, runs
	 * the PHP calculation (the source of truth), and hands the prepared values
	 * to src/render.php for escaped markup output.
	 *
	 * @param array<string,mixed>              $attributes Block attributes.
	 * @param string                           $content    Block content (empty for dynamic blocks).
	 * @param WP_Block                         $block      Block instance.
	 * @return string Rendered block HTML.
	 */
	public function render( $attributes, $content, $block ) {
		unset( $content, $block );

		/**
		 * Fires immediately before the calculator block renders.
		 *
		 * @param array<string,mixed> $attributes Raw block attributes.
		 */
		do_action( 'mcb_before_calculator_render', $attributes );

		$attrs  = mcb_sanitize_attributes( $attributes );
		$result = mcb_calculate( $attrs );
		$symbol = mcb_resolve_currency_symbol( $attrs );

		ob_start();
		include MCB_PLUGIN_DIR . 'src/render.php';
		$html = (string) ob_get_clean();

		/**
		 * Fires immediately after the calculator block renders.
		 *
		 * @param array<string,mixed> $attributes Raw block attributes.
		 */
		do_action( 'mcb_after_calculator_render', $attributes );

		return $html;
	}
}
