<?php
/**
 * [calcforge] shortcode.
 *
 * @package CalcForge
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
 * with [calcforge] behaves identically to one placed as a block and cannot
 * drift from it when attributes are added later.
 */
final class CalcForge_Shortcode {

	/**
	 * Shortcode tag.
	 */
	const TAG = 'calcforge';

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
				'description' => __( 'Financed amount before the down payment, for example 300000.', CALCFORGE_TEXT_DOMAIN ),
			),
			'downpayment'      => array(
				'attribute'   => 'downPayment',
				'type'        => 'number',
				'description' => __( 'Up-front amount deducted from the loan amount.', CALCFORGE_TEXT_DOMAIN ),
			),
			'interestrate'     => array(
				'attribute'   => 'interestRate',
				'type'        => 'number',
				'description' => __( 'Annual interest rate as a percentage, for example 5.5.', CALCFORGE_TEXT_DOMAIN ),
			),
			'loanterm'         => array(
				'attribute'   => 'loanTerm',
				'type'        => 'number',
				'description' => __( 'Term in years, from 1 to 60.', CALCFORGE_TEXT_DOMAIN ),
			),
			'currencysymbol'   => array(
				'attribute'   => 'currencySymbol',
				'type'        => 'text',
				'description' => __( 'Symbol shown next to every amount, for example $ or EUR.', CALCFORGE_TEXT_DOMAIN ),
			),
			'currencyposition' => array(
				'attribute'   => 'currencyPosition',
				'type'        => 'text',
				'description' => __( 'prefix or suffix, deciding which side of the number the symbol sits on.', CALCFORGE_TEXT_DOMAIN ),
			),
			'showcharts'       => array(
				'attribute'   => 'showCharts',
				'type'        => 'boolean',
				'description' => __( 'false hides the charts.', CALCFORGE_TEXT_DOMAIN ),
			),
			'charttype'        => array(
				'attribute'   => 'chartType',
				'type'        => 'text',
				'description' => __( 'donut, line, bar, dots, or both.', CALCFORGE_TEXT_DOMAIN ),
			),
			'formcolumns'      => array(
				'attribute'   => 'formColumns',
				'type'        => 'text',
				'description' => __( 'wide or compact, deciding how the form fields are laid out.', CALCFORGE_TEXT_DOMAIN ),
			),
			'panelorder'       => array(
				'attribute'   => 'panelOrder',
				'type'        => 'list',
				'description' => __( 'Comma separated panel order, for example form,results,charts,schedule.', CALCFORGE_TEXT_DOMAIN ),
			),
			'layout'           => array(
				'attribute'   => 'layout',
				'type'        => 'text',
				'description' => __( 'split or stacked.', CALCFORGE_TEXT_DOMAIN ),
			),
			'theme'            => array(
				'attribute'   => 'theme',
				'type'        => 'text',
				'description' => __( 'Skin slug for the calculator, for example light or dark.', CALCFORGE_TEXT_DOMAIN ),
			),
			'showamortization' => array(
				'attribute'   => 'showAmortization',
				'type'        => 'boolean',
				'description' => __( 'false hides the year-by-year schedule.', CALCFORGE_TEXT_DOMAIN ),
			),
			'showsliders'      => array(
				'attribute'   => 'showSliders',
				'type'        => 'boolean',
				'description' => __( 'false replaces the sliders with plain inputs.', CALCFORGE_TEXT_DOMAIN ),
			),
			'showresults'      => array(
				'attribute'   => 'showResults',
				'type'        => 'boolean',
				'description' => __( 'false hides the results summary.', CALCFORGE_TEXT_DOMAIN ),
			),
			'showcosts'       => array(
				'attribute'   => 'showCosts',
				'type'        => 'boolean',
				'description' => __( 'true adds the recurring cost inputs and the total monthly cost.', CALCFORGE_TEXT_DOMAIN ),
			),
			'propertytax'     => array(
				'attribute'   => 'propertyTax',
				'type'        => 'number',
				'description' => __( 'Annual property tax, as a rate or an amount, depending on propertytaxunit.', CALCFORGE_TEXT_DOMAIN ),
			),
			'propertytaxunit' => array(
				'attribute'   => 'propertyTaxUnit',
				'type'        => 'text',
				'description' => __( 'percent or amount, deciding whether propertytax is read as a rate of the purchase price.', CALCFORGE_TEXT_DOMAIN ),
			),
			'homeinsurance'     => array(
				'attribute'   => 'homeInsurance',
				'type'        => 'number',
				'description' => __( 'Annual home insurance, as a rate or an amount, depending on homeinsuranceunit.', CALCFORGE_TEXT_DOMAIN ),
			),
			'homeinsuranceunit' => array(
				'attribute'   => 'homeInsuranceUnit',
				'type'        => 'text',
				'description' => __( 'percent or amount, deciding whether homeinsurance is read as a rate of the purchase price.', CALCFORGE_TEXT_DOMAIN ),
			),
			'hoafee'     => array(
				'attribute'   => 'hoaFee',
				'type'        => 'number',
				'description' => __( 'Annual HOA fee, as a rate or an amount, depending on hoafeeunit.', CALCFORGE_TEXT_DOMAIN ),
			),
			'hoafeeunit' => array(
				'attribute'   => 'hoaFeeUnit',
				'type'        => 'text',
				'description' => __( 'percent or amount, deciding whether hoafee is read as a rate of the purchase price.', CALCFORGE_TEXT_DOMAIN ),
			),
			'pmi'     => array(
				'attribute'   => 'pmi',
				'type'        => 'number',
				'description' => __( 'Annual mortgage insurance premium, as a rate or an amount, depending on pmiunit. It stops once the balance reaches 80% of the purchase price.', CALCFORGE_TEXT_DOMAIN ),
			),
			'pmiunit' => array(
				'attribute'   => 'pmiUnit',
				'type'        => 'text',
				'description' => __( 'percent or amount, deciding whether pmi is read as a rate of the purchase price.', CALCFORGE_TEXT_DOMAIN ),
			),
			'othercosts'     => array(
				'attribute'   => 'otherCosts',
				'type'        => 'number',
				'description' => __( 'Any other annual cost, as a rate or an amount, depending on othercostsunit.', CALCFORGE_TEXT_DOMAIN ),
			),
			'othercostsunit' => array(
				'attribute'   => 'otherCostsUnit',
				'type'        => 'text',
				'description' => __( 'percent or amount, deciding whether othercosts is read as a rate of the purchase price.', CALCFORGE_TEXT_DOMAIN ),
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
		$defaults = calcforge_get_default_attributes();
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
			'blockName' => 'calcforge/mortgage-calculator',
			'attrs'     => $this->to_block_attributes( $atts, $map ),
		);

		/**
		 * Filters the block array a [calcforge] shortcode renders.
		 *
		 * @param array<string,mixed> $block Block name and attributes.
		 * @param array<string,string> $atts Merged shortcode attributes.
		 */
		$block = apply_filters( 'calcforge_shortcode_block', $block, $atts );

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
	 * and numbers. Casting here means calcforge_sanitize_attributes() receives
	 * the same shapes it receives from the editor, so a shortcode and a block set
	 * to the same values render identically. A value that is not numeric falls
	 * back to the site default rather than becoming 0.
	 *
	 * @param array<string,mixed>                               $atts Merged shortcode attributes.
	 * @param array<string,array<string,string>>                $map  Documented attribute descriptors.
	 * @return array<string,mixed> Block attributes keyed by block attribute name.
	 */
	private function to_block_attributes( $atts, $map ) {
		$defaults = calcforge_get_default_attributes();
		$typed    = array();

		foreach ( $map as $shortcode_name => $spec ) {
			$block_key = $spec['attribute'];
			/*
			 * A third party can remove an attribute from the defaults through
			 * the calcforge_default_attributes filter, so a missing key is
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
				 * calculator to zero panels. calcforge_resolve_panel_order()
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
