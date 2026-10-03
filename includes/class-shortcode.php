<?php
/**
 * [amortexa-mortgage-calculator] shortcode.
 *
 * @package Amortexa
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exposes the calculator block as a shortcode for themes and page builders.
 *
 * The shortcode deliberately renders through render_block() rather than calling
 * the render callback directly. That reuses the block's own attribute
 * sanitization, defaults, stylesheet, and view script, so a calculator placed
 * with [amortexa-mortgage-calculator] behaves identically to one placed as a block and cannot
 * drift from it when attributes are added later.
 */
final class Amortexa_Shortcode {

	/**
	 * Shortcode tag.
	 */
	const TAG = 'amortexa-mortgage-calculator';

	/**
	 * Attributes the shortcode accepts, keyed by the name an author types.
	 *
	 * WordPress lowercases every shortcode attribute name in
	 * shortcode_parse_atts(), so the keys here are lowercase even though the
	 * block attributes they map to are camelCase. Each entry records the block
	 * attribute it feeds, the value type to cast to, and a description used by
	 * the reference in the settings screen.
	 *
	 * The colour and font attributes are deliberately omitted: they are styling
	 * escape hatches rather than something an author is expected to type.
	 *
	 * @return array<string,array<string,string>> Shortcode name => descriptor.
	 */
	public static function get_documented_attributes() {
		return array(
			'loanamount'       => array(
				'attribute'   => 'loanAmount',
				'type'        => 'number',
				'description' => __( 'Financed amount before the down payment, for example 300000.', 'amortexa-mortgage-calculator' ),
			),
			'downpayment'      => array(
				'attribute'   => 'downPayment',
				'type'        => 'number',
				'description' => __( 'Up-front amount deducted from the loan amount.', 'amortexa-mortgage-calculator' ),
			),
			'interestrate'     => array(
				'attribute'   => 'interestRate',
				'type'        => 'number',
				'description' => __( 'Annual interest rate as a percentage, for example 5.5.', 'amortexa-mortgage-calculator' ),
			),
			'loanterm'         => array(
				'attribute'   => 'loanTerm',
				'type'        => 'number',
				'description' => __( 'Term in years, from 1 to 60.', 'amortexa-mortgage-calculator' ),
			),
			'currencysymbol'   => array(
				'attribute'   => 'currencySymbol',
				'type'        => 'text',
				'description' => __( 'Symbol shown next to every amount, for example $ or EUR.', 'amortexa-mortgage-calculator' ),
			),
			'currencyposition' => array(
				'attribute'   => 'currencyPosition',
				'type'        => 'text',
				'description' => __( 'prefix or suffix, deciding which side of the number the symbol sits on.', 'amortexa-mortgage-calculator' ),
			),
			'showcharts'       => array(
				'attribute'   => 'showCharts',
				'type'        => 'boolean',
				'description' => __( 'false hides the charts.', 'amortexa-mortgage-calculator' ),
			),
			'charttype'        => array(
				'attribute'   => 'chartType',
				'type'        => 'text',
				'description' => __( 'donut, line, bar, dots, or both.', 'amortexa-mortgage-calculator' ),
			),
			'formcolumns'      => array(
				'attribute'   => 'formColumns',
				'type'        => 'text',
				'description' => __( 'wide or compact, deciding how the form fields are laid out.', 'amortexa-mortgage-calculator' ),
			),
			'panelorder'       => array(
				'attribute'   => 'panelOrder',
				'type'        => 'list',
				'description' => __( 'Comma separated panel order, for example form,results,charts,schedule.', 'amortexa-mortgage-calculator' ),
			),
			'layout'           => array(
				'attribute'   => 'layout',
				'type'        => 'text',
				'description' => __( 'stacked, split, aside or chart-aside.', 'amortexa-mortgage-calculator' ),
			),
			'theme'            => array(
				'attribute'   => 'theme',
				'type'        => 'text',
				'description' => __( 'Skin slug for the calculator, for example light or dark.', 'amortexa-mortgage-calculator' ),
			),
			'showamortization' => array(
				'attribute'   => 'showAmortization',
				'type'        => 'boolean',
				'description' => __( 'false hides the year-by-year schedule.', 'amortexa-mortgage-calculator' ),
			),
			'showsliders'      => array(
				'attribute'   => 'showSliders',
				'type'        => 'boolean',
				'description' => __( 'false replaces the sliders with plain inputs.', 'amortexa-mortgage-calculator' ),
			),
			'showresults'      => array(
				'attribute'   => 'showResults',
				'type'        => 'boolean',
				'description' => __( 'false hides the results summary.', 'amortexa-mortgage-calculator' ),
			),
			'showcosts'       => array(
				'attribute'   => 'showCosts',
				'type'        => 'boolean',
				'description' => __( 'true adds the recurring cost inputs and the total monthly cost.', 'amortexa-mortgage-calculator' ),
			),
			'propertytax'     => array(
				'attribute'   => 'propertyTax',
				'type'        => 'number',
				'description' => __( 'Annual property tax, as a rate or an amount, depending on propertytaxunit.', 'amortexa-mortgage-calculator' ),
			),
			'propertytaxunit' => array(
				'attribute'   => 'propertyTaxUnit',
				'type'        => 'text',
				'description' => __( 'percent or amount, deciding whether propertytax is read as a rate of the purchase price.', 'amortexa-mortgage-calculator' ),
			),
			'homeinsurance'     => array(
				'attribute'   => 'homeInsurance',
				'type'        => 'number',
				'description' => __( 'Annual home insurance, as a rate or an amount, depending on homeinsuranceunit.', 'amortexa-mortgage-calculator' ),
			),
			'homeinsuranceunit' => array(
				'attribute'   => 'homeInsuranceUnit',
				'type'        => 'text',
				'description' => __( 'percent or amount, deciding whether homeinsurance is read as a rate of the purchase price.', 'amortexa-mortgage-calculator' ),
			),
			'hoafee'     => array(
				'attribute'   => 'hoaFee',
				'type'        => 'number',
				'description' => __( 'Annual HOA fee, as a rate or an amount, depending on hoafeeunit.', 'amortexa-mortgage-calculator' ),
			),
			'hoafeeunit' => array(
				'attribute'   => 'hoaFeeUnit',
				'type'        => 'text',
				'description' => __( 'percent or amount, deciding whether hoafee is read as a rate of the purchase price.', 'amortexa-mortgage-calculator' ),
			),
			'pmi'     => array(
				'attribute'   => 'pmi',
				'type'        => 'number',
				'description' => __( 'Annual mortgage insurance premium, as a rate or an amount, depending on pmiunit. It stops once the balance reaches 80% of the purchase price.', 'amortexa-mortgage-calculator' ),
			),
			'pmiunit' => array(
				'attribute'   => 'pmiUnit',
				'type'        => 'text',
				'description' => __( 'percent or amount, deciding whether pmi is read as a rate of the purchase price.', 'amortexa-mortgage-calculator' ),
			),
			'othercosts'     => array(
				'attribute'   => 'otherCosts',
				'type'        => 'number',
				'description' => __( 'Any other annual cost, as a rate or an amount, depending on othercostsunit.', 'amortexa-mortgage-calculator' ),
			),
			'othercostsunit' => array(
				'attribute'   => 'otherCostsUnit',
				'type'        => 'text',
				'description' => __( 'percent or amount, deciding whether othercosts is read as a rate of the purchase price.', 'amortexa-mortgage-calculator' ),
			),
		);
	}

	/**
	 * Registers the hooks this component responds to.
	 */
	public function register_hooks() {
		add_shortcode( self::TAG, array( $this, 'render' ) );
	}

	/**
	 * Renders the shortcode.
	 *
	 * @param array<string,string>|string $atts Shortcode attributes.
	 * @return string Rendered calculator markup, or an empty string when the
	 *                block type is not registered.
	 */
	public function render( $atts ) {
		if ( ! function_exists( 'render_block' ) ) {
			return '';
		}

		$map      = self::get_documented_attributes();
		$defaults = amortexa_get_default_attributes();
		$pairs    = array();

		/*
		 * shortcode_atts( $pairs, $atts, $shortcode ) only returns keys that
		 * appear in $pairs, so seeding it with the block defaults both filters out
		 * unrecognized attributes and makes an omitted one fall back to the
		 * site-wide default, exactly as an unset block attribute would.
		 */
		foreach ( $map as $shortcode_name => $spec ) {
			$pairs[ $shortcode_name ] = array_key_exists( $spec['attribute'], $defaults )
				? $defaults[ $spec['attribute'] ]
				: null;
		}

		$atts = shortcode_atts( $pairs, (array) $atts, self::TAG );

		$block = array(
			'blockName' => 'amortexa-mortgage-calculator/mortgage-calculator',
			'attrs'     => $this->to_block_attributes( $atts, $map ),
		);

		/**
		 * Filters the block array a [amortexa-mortgage-calculator] shortcode renders.
		 *
		 * @param array<string,mixed> $block Block name and attributes.
		 * @param array<string,string> $atts Merged shortcode attributes.
		 */
		$block = apply_filters( 'amortexa_shortcode_block', $block, $atts );

		if ( ! is_array( $block ) || empty( $block['blockName'] ) ) {
			return '';
		}

		$markup = render_block( $block );

		return is_string( $markup ) ? $markup : '';
	}

	/**
	 * Renames shortcode attributes to block attributes and casts their values.
	 *
	 * Shortcodes can only carry strings, but the block schema declares booleans
	 * and numbers. Casting here means amortexa_sanitize_attributes() receives
	 * the same shapes it receives from the editor, so a shortcode and a block set
	 * to the same values render identically. A value that is not numeric falls
	 * back to the site default rather than becoming 0.
	 *
	 * @param array<string,mixed>                               $atts Merged shortcode attributes.
	 * @param array<string,array<string,string>>                $map  Documented attribute descriptors.
	 * @return array<string,mixed> Block attributes keyed by block attribute name.
	 */
	private function to_block_attributes( $atts, $map ) {
		$defaults = amortexa_get_default_attributes();
		$typed    = array();

		foreach ( $map as $shortcode_name => $spec ) {
			$block_key = $spec['attribute'];
			/*
			 * A third party can remove an attribute from the defaults through
			 * the amortexa_default_attributes filter, so a missing key is
			 * read as null and falls through to the cast below rather than
			 * raising an undefined-array-key warning on the front end.
			 */
			$fallback = array_key_exists( $block_key, $defaults ) ? $defaults[ $block_key ] : null;
			$value    = array_key_exists( $shortcode_name, $atts ) ? $atts[ $shortcode_name ] : $fallback;

			switch ( $spec['type'] ) {
				case 'boolean':
					$typed[ $block_key ] = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
					break;

				case 'number':
					$typed[ $block_key ] = is_numeric( $value )
						? $value + 0
						: $fallback;
					break;

				/*
				 * A shortcode carries a flat string, so a list arrives as
				 * "form,results". The seeded default is already an array, and a
				 * value that splits to nothing falls back to it, so neither
				 * panelorder="" nor an omitted attribute collapses the
				 * calculator to zero panels. amortexa_resolve_panel_order()
				 * drops any key that is not a real panel.
				 */
				case 'list':
					$parts = is_array( $value )
						? $value
						: explode( ',', (string) $value );
					$parts = array_values(
						array_filter(
							array_map(
								'sanitize_text_field',
								array_map( 'trim', $parts )
							),
							static function ( $part ) {
								return '' !== $part;
							}
						)
					);
					$typed[ $block_key ] = $parts
						? $parts
						: (array) $fallback;
					break;

				default:
					$typed[ $block_key ] = sanitize_text_field( (string) $value );
					break;
			}
		}

		return $typed;
	}
}
