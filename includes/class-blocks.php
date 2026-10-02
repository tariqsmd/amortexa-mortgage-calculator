<?php
/**
 * Block category and block type registration.
 *
 * @package Amortexa
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the mortgage calculator block from its build/block.json metadata.
 */
class Amortexa_Blocks {

	/**
	 * Fully qualified block name.
	 */
	const BLOCK_NAME = 'amortexa-mortgage-calculator/mortgage-calculator';

	/**
	 * Custom block category slug.
	 */
	const CATEGORY = 'amortexa-mortgage-calculator';

	/**
	 * Registers the hooks this component responds to.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register_block_type' ), 10 );
		add_filter( 'block_categories_all', array( $this, 'register_category' ) );
	}

	/**
	 * Adds the Amortexa block category.
	 *
	 * @param array<int,array<string,mixed>> $categories Existing block categories.
	 * @return array<int,array<string,mixed>> Categories including our own.
	 */
	public function register_category( $categories ) {
		foreach ( $categories as $category ) {
			if ( isset( $category['slug'] ) && self::CATEGORY === $category['slug'] ) {
				return $categories;
			}
		}

		$categories[] = array(
			'slug'  => self::CATEGORY,
			'title' => __( 'Amortexa', AMORTEXA_TEXT_DOMAIN ),
			'icon'  => 'calculator',
		);

		return $categories;
	}

	/**
	 * Registers the block, seeding attribute defaults from the site settings.
	 *
	 * block.json carries a static fallback default for every attribute so the
	 * file is meaningful on its own, but the administrator's global defaults
	 * must win for newly inserted blocks. Overriding the resolved attributes at
	 * registration time is what keeps the editor and the front end in agreement:
	 * both read the same injected values instead of two different sources.
	 */
	public function register_block_type() {
		$build_dir = AMORTEXA_PLUGIN_DIR . 'build';

		if ( ! file_exists( $build_dir . '/block.json' ) ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'The block build is missing. Run `npm install` then `npm run build` inside the plugin folder.', AMORTEXA_TEXT_DOMAIN ),
				'1.0.0'
			);
			return;
		}

		register_block_type_from_metadata(
			$build_dir,
			array( 'attributes' => $this->get_attributes_with_site_defaults() )
		);
	}

	/**
	 * Returns block.json's attributes with site defaults injected as defaults.
	 *
	 * @return array<string,array<string,mixed>> Attribute schemas.
	 */
	private function get_attributes_with_site_defaults() {
		$metadata = json_decode( (string) file_get_contents( AMORTEXA_PLUGIN_DIR . 'build/block.json' ), true );

		if ( ! is_array( $metadata ) || empty( $metadata['attributes'] ) || ! is_array( $metadata['attributes'] ) ) {
			return array();
		}

		$attributes = $metadata['attributes'];
		$defaults   = amortexa_get_default_attributes();

		foreach ( $attributes as $name => $schema ) {
			if ( array_key_exists( $name, $defaults ) ) {
				$attributes[ $name ]['default'] = $defaults[ $name ];
			}
		}

		return $attributes;
	}
}
