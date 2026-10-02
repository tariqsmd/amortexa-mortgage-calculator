<?php
/**
 * Editor script data and translations.
 *
 * Block scripts, styles, and the render template are all declared in
 * build/block.json using the `file:` prefix, so WordPress resolves and enqueues
 * them on its own. This class therefore only has to do the two things block.json
 * cannot express: attach translation catalogues, and hand the editor the option
 * lists that the block inspector builds its controls from.
 *
 * @package Amortexa
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Attaches script translations and editor data to the auto-registered block assets.
 */
class Amortexa_Assets {

	/**
	 * Global JS variable the block editor reads its option lists from.
	 */
	const EDITOR_DATA = 'amortexaData';

	/**
	 * Registers the hooks this component responds to.
	 */
	public function register_hooks() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'configure_editor_assets' ) );
	}

	/**
	 * Adds translations and the option data to the block's editor script.
	 *
	 * The handle names are read from the registered block type rather than
	 * reconstructed from the block name, so renaming the block or changing how
	 * WordPress generates handles cannot silently break translations.
	 */
	public function configure_editor_assets() {
		$block_type = WP_Block_Type_Registry::get_instance()->get_registered( Amortexa_Blocks::BLOCK_NAME );

		if ( ! $block_type ) {
			return;
		}

		/*
		 * The payload is the same for every handle, so it is built once here
		 * rather than per handle below.
		 *
		 * The payload carries filterable skin and currency labels, so it is
		 * encoded with the hex flags rather than plain: without them a
		 * "</script>" sequence inside a label would close the inline script
		 * block and turn the rest of the value into markup. This mirrors how
		 * the front end embeds the same data.
		 */
		$encoded = wp_json_encode(
			amortexa_get_editor_data(),
			JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
		);

		foreach ( (array) $block_type->editor_script_handles as $handle ) {
			wp_set_script_translations( $handle, AMORTEXA_TEXT_DOMAIN );

			wp_add_inline_script(
				$handle,
				'var ' . self::EDITOR_DATA . ' = ' . $encoded . ';',
				'before'
			);
		}
	}
}
