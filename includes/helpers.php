<?php
/**
 * Pure helper functions for the Mortgage Calculator block.
 *
 * Everything in this file is a side-effect-free function so the mortgage math
 * stays unit-testable and shareable between the block render callback, the REST
 * endpoint, and any future shortcode. Nothing here registers hooks.
 *
 * The option-choice data (skins, chart types, fonts) is also defined here so the
 * settings screen, the block.json enum, and the editor inspector can never drift
 * apart: they all read from these functions.
 *
 * @package CalcForge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the list of valid skin slugs.
 *
 * Keep in sync with the `.calcforge-theme-*` rules in src/style.scss.
 *
 * @return array<int,string> Skin slugs.
 */
function calcforge_get_skin_slugs() {
	return array_keys( calcforge_get_skins() );
}

/**
 * Returns every selectable skin as a slug => translated label map.
 *
 * Single source of truth for the settings screen and, via the editor script
 * data, for the block inspector.
 *
 * @return array<string,string> Skin slug => label.
 */
function calcforge_get_skins() {
	$skins = array(
		'light'    => __( 'Classic Light', CALCFORGE_TEXT_DOMAIN ),
		'dark'     => __( 'Elegant Dark', CALCFORGE_TEXT_DOMAIN ),
		'ocean'    => __( 'Ocean Blue', CALCFORGE_TEXT_DOMAIN ),
		'sunset'   => __( 'Sunset Warm', CALCFORGE_TEXT_DOMAIN ),
		'forest'   => __( 'Forest Green', CALCFORGE_TEXT_DOMAIN ),
		'midnight' => __( 'Midnight Violet', CALCFORGE_TEXT_DOMAIN ),
		'rose'     => __( 'Rose Quartz', CALCFORGE_TEXT_DOMAIN ),
		'slate'    => __( 'Minimal Slate', CALCFORGE_TEXT_DOMAIN ),
		'grape'    => __( 'Royal Grape', CALCFORGE_TEXT_DOMAIN ),
		'aqua'     => __( 'Aqua Fresh', CALCFORGE_TEXT_DOMAIN ),
		'mocha'    => __( 'Mocha Cream', CALCFORGE_TEXT_DOMAIN ),
		'cyber'    => __( 'Cyber Neon', CALCFORGE_TEXT_DOMAIN ),
		'emerald'  => __( 'Emerald Nights', CALCFORGE_TEXT_DOMAIN ),
		'crimson'  => __( 'Crimson Dusk', CALCFORGE_TEXT_DOMAIN ),
		'charcoal' => __( 'Graphite', CALCFORGE_TEXT_DOMAIN ),
		'copper'   => __( 'Copper Forge', CALCFORGE_TEXT_DOMAIN ),
		'royal'    => __( 'Royal Sapphire', CALCFORGE_TEXT_DOMAIN ),
		'amber'    => __( 'Amber Gold', CALCFORGE_TEXT_DOMAIN ),
		'cobalt'   => __( 'Cobalt Blue', CALCFORGE_TEXT_DOMAIN ),
		'fuchsia'  => __( 'Fuchsia Bloom', CALCFORGE_TEXT_DOMAIN ),
		'mint'     => __( 'Mint Fresh', CALCFORGE_TEXT_DOMAIN ),
		'sand'     => __( 'Sandstone', CALCFORGE_TEXT_DOMAIN ),
		'lemon'    => __( 'Lemon Zest', CALCFORGE_TEXT_DOMAIN ),
		'steel'    => __( 'Steel Blue', CALCFORGE_TEXT_DOMAIN ),
	);

	/**
	 * Filters the selectable calculator skins.
	 *
	 * @param array<string,string> $skins Skin slug => translated label.
	 */
	return apply_filters( 'calcforge_skins', $skins );
}

/**
 * Returns the selectable chart types as value => translated label.
 *
 * @return array<string,string> Chart type => label.
 */
function calcforge_get_chart_types() {
	return array(
		'both'  => __( 'Both charts', CALCFORGE_TEXT_DOMAIN ),
		'donut' => __( 'Donut only', CALCFORGE_TEXT_DOMAIN ),
		'line'  => __( 'Line only', CALCFORGE_TEXT_DOMAIN ),
		'bar'   => __( 'Bar only', CALCFORGE_TEXT_DOMAIN ),
		'dots'  => __( 'Dot comparison', CALCFORGE_TEXT_DOMAIN ),
	);
}

/**
 * Returns the reorderable calculator panels, in their default vertical order.
 *
 * The form is part of the set: every panel can be dragged anywhere, including
 * below its own results.
 *
 * @return array<int,string> Panel keys.
 */
function calcforge_get_panel_keys() {
	return array( 'form', 'results', 'charts', 'schedule' );
}

/**
 * Returns the payment breakdown components, in the order they are shown.
 *
 * Principal and interest leads the list because it is the cost the borrower
 * always pays, but it is flagged as not being a percentage: it is the loan
 * itself rather than a figure the homeowner chose, and the form does not offer
 * an input for it because it already has its own headline. Every other entry
 * defaults to zero, so a calculator that never touches these inputs renders
 * exactly as it did before they existed, with an empty breakdown rather than a
 * row of zero-width chart slices.
 *
 * Each entry carries the attribute that holds the annual figure, whether that
 * figure is a percentage of the home price or a currency amount, and the design
 * token that colours it. Deriving all three from one list keeps the form, the
 * calculation, the results panel and the chart legend from disagreeing about
 * which component is which.
 *
 * @return array<string,array<string,mixed>> Component key => descriptor.
 */
function calcforge_get_cost_components() {
	return array(
		'pi'        => array(
			'label'    => __( 'Principal & Interest', CALCFORGE_TEXT_DOMAIN ),
			'token'    => 'costPi',
			'percent'  => false,
		),
		'tax'       => array(
			'label'    => __( 'Property Tax', CALCFORGE_TEXT_DOMAIN ),
			'token'    => 'costTax',
			'percent'  => true,
		),
		'insurance' => array(
			'label'    => __( 'Home Insurance', CALCFORGE_TEXT_DOMAIN ),
			'token'    => 'costInsurance',
			'percent'  => true,
		),
		'hoa'       => array(
			'label'    => __( 'HOA Fee', CALCFORGE_TEXT_DOMAIN ),
			'token'    => 'costHoa',
			'percent'  => true,
		),
		'pmi'       => array(
			'label'    => __( 'PMI', CALCFORGE_TEXT_DOMAIN ),
			'token'    => 'costPmi',
			'percent'  => true,
		),
		'other'     => array(
			'label'    => __( 'Other Costs', CALCFORGE_TEXT_DOMAIN ),
			'token'    => 'costOther',
			'percent'  => true,
		),
	);
}

/**
 * Returns the attribute name holding a cost component's annual figure.
 *
 * @param string $component Component key, e.g. 'tax'.
 * @return string Block attribute name.
 */
function calcforge_get_cost_attribute( $component ) {
	$map = array(
		'tax'       => 'propertyTax',
		'insurance' => 'homeInsurance',
		'hoa'       => 'hoaFee',
		'pmi'       => 'pmi',
		'other'     => 'otherCosts',
	);

	return isset( $map[ $component ] ) ? $map[ $component ] : '';
}

/**
 * Maps each percentage-capable cost attribute to its display unit.
 *
 * @return array<string,string> Attribute name => 'percent' or 'amount'.
 */
function calcforge_get_cost_units() {
	$units = array();

	foreach ( calcforge_get_cost_components() as $key => $component ) {
		if ( empty( $component['percent'] ) ) {
			continue;
		}

		$units[ calcforge_get_cost_attribute( $key ) ] = 'percent';
	}

	/**
	 * Filters whether each cost field is entered as a percentage or an amount.
	 *
	 * @param array<string,string> $units Attribute name => 'percent' or 'amount'.
	 */
	return apply_filters( 'calcforge_cost_units', $units );
}

/**
 * Resolves the display unit for each cost attribute, honouring the saved choice.
 *
 * calcforge_get_cost_units() reports which fields can be a percentage at all,
 * which is a property of the schema. This adds the per-block answer: the author
 * can enter tax as a rate or as the cash amount off a bill, and that decision
 * lives in the saved `homeInsuranceUnit` style attributes. Reading them here is
 * what stops a field labelled in dollars being multiplied by the home price.
 *
 * @param array<string,mixed> $attributes Sanitized or raw block attributes.
 * @return array<string,string> Attribute name => 'percent' or 'amount'.
 */
function calcforge_resolve_cost_units( $attributes ) {
	$attributes = is_array( $attributes ) ? $attributes : array();
	$units      = calcforge_get_cost_units();
	$resolved   = array();

	foreach ( $units as $attribute => $default ) {
		$raw = isset( $attributes[ $attribute . 'Unit' ] ) ? (string) $attributes[ $attribute . 'Unit' ] : '';

		$resolved[ $attribute ] = in_array( $raw, array( 'percent', 'amount' ), true ) ? $raw : $default;
	}

	return $resolved;
}

/**
 * Resolves the panel order to render, given the saved order and which panels
 * are visible.
 *
 * Unknown keys are dropped, duplicates collapse, and any visible panel missing
 * from the saved order is appended, so a hand-edited post or a block saved by
 * an older version still renders every visible panel.
 *
 * A block saved before the form became reorderable has no 'form' key at all.
 * Those keep the form first, so upgrading never drops the inputs to the
 * bottom of existing content. Once a form key is present the saved order is
 * honoured exactly, wherever the author dragged it.
 *
 * The grid places panels in DOM order, so in the two column split a full width
 * panel ahead of the form takes the first row on its own and the form and
 * results share the next one.
 *
 * @param array<int,string> $order   Saved panel order.
 * @param array<int,string> $visible Panel keys that should render.
 * @return array<int,string> Ordered, de-duplicated panel keys.
 */
function calcforge_resolve_panel_order( $order, $visible ) {
	$allowed = array_flip( calcforge_get_panel_keys() );
	$resolved = array();

	foreach ( (array) $order as $key ) {
		$key = is_string( $key ) ? $key : '';

		if ( isset( $allowed[ $key ] ) && in_array( $key, $visible, true ) && ! in_array( $key, $resolved, true ) ) {
			$resolved[] = $key;
		}
	}

	if ( ! in_array( 'form', $resolved, true ) && in_array( 'form', $visible, true ) ) {
		array_unshift( $resolved, 'form' );
	}

	foreach ( $visible as $key ) {
		if ( ! in_array( $key, $resolved, true ) ) {
			$resolved[] = $key;
		}
	}

	return $resolved;
}

/**
 * Returns the selectable calculator layouts.
 *
 * Layouts are purely presentational: the markup order never changes, only the
 * CSS grid on `.calcforge-calc__grid`, so a layout can be switched on an
 * existing block without invalidating anything.
 *
 * @return array<string,string> Layout key => label.
 */
function calcforge_get_layouts() {
	return array(
		'stacked' => __( 'Stacked (single column)', CALCFORGE_TEXT_DOMAIN ),
		'split'   => __( 'Two column split', CALCFORGE_TEXT_DOMAIN ),
	);
}

/**
 * Returns the selectable form column treatments.
 *
 * Each control is a slider paired with a number input, so the two have to share
 * a row of a given width. `wide` gives every control the full width of the form
 * and splits that row two to one in the slider's favour. `compact` lets the
 * form pack more controls per row and moves each input underneath its slider,
 * which keeps a single column calculator short but makes a two column split
 * taller, because every control then takes two lines.
 *
 * @return array<string,string> Form column key => label.
 */
function calcforge_get_form_columns() {
	return array(
		'wide'    => __( 'Full width controls', CALCFORGE_TEXT_DOMAIN ),
		'compact' => __( 'Compact rows, input below slider', CALCFORGE_TEXT_DOMAIN ),
	);
}

/**
 * Returns the selectable font families as value => label.
 *
 * Only system stacks are offered so no external font files are loaded, which
 * keeps the plugin fast and compliant with the WordPress.org font guidelines.
 *
 * @return array<string,string> Font key => label.
 */
function calcforge_get_font_families() {
	return array(
		'inherit' => __( 'Theme default', CALCFORGE_TEXT_DOMAIN ),
		'sans'    => __( 'Modern Sans (system)', CALCFORGE_TEXT_DOMAIN ),
		'serif'   => __( 'Classic Serif (system)', CALCFORGE_TEXT_DOMAIN ),
		'mono'    => __( 'Monospace (system)', CALCFORGE_TEXT_DOMAIN ),
	);
}

/**
 * Returns the selectable font weights as value => label.
 *
 * @return array<string,string> Weight => label.
 */
function calcforge_get_font_weights() {
	return array(
		''     => __( 'Theme default', CALCFORGE_TEXT_DOMAIN ),
		'300'  => __( 'Light', CALCFORGE_TEXT_DOMAIN ),
		'400'  => __( 'Normal', CALCFORGE_TEXT_DOMAIN ),
		'500'  => __( 'Medium', CALCFORGE_TEXT_DOMAIN ),
		'600'  => __( 'Semi Bold', CALCFORGE_TEXT_DOMAIN ),
		'700'  => __( 'Bold', CALCFORGE_TEXT_DOMAIN ),
		'800'  => __( 'Extra Bold', CALCFORGE_TEXT_DOMAIN ),
	);
}

/**
 * Returns the currency symbol placement as value => label.
 *
 * @return array<string,string> Position => label.
 */
function calcforge_get_currency_positions() {
	return array(
		'prefix' => __( 'Before amount ($99)', CALCFORGE_TEXT_DOMAIN ),
		'suffix' => __( 'After amount (99 EUR)', CALCFORGE_TEXT_DOMAIN ),
	);
}

/**
 * Returns the CSS font-family stack for a font-family attribute key.
 *
 * @param string $key Font family key: inherit|sans|serif|mono.
 * @return string CSS font-family value ('' means inherit from the theme).
 */
function calcforge_get_font_stack( $key ) {
	$stacks = array(
		'inherit' => '',
		'sans'    => 'system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif',
		'serif'   => 'Georgia, "Times New Roman", Times, serif',
		'mono'    => 'ui-monospace, "SF Mono", "Cascadia Code", Consolas, Menlo, monospace',
	);

	return isset( $stacks[ $key ] ) ? $stacks[ $key ] : '';
}

/**
 * Returns the unfiltered default admin settings.
 *
 * @return array<string,mixed> Default settings keyed by option name.
 */
function calcforge_get_default_settings() {
	$defaults = array(
		'currency_symbol'       => '$',
		'currency_position'     => 'prefix',
		'default_interest_rate' => 6.5,
		'decimal_precision'     => 2,
		'enable_amortization'   => true,
		'default_loan_amount'   => 300000.0,
		'default_down_payment'  => 0.0,
		'default_loan_term'     => 30,
		'default_theme'         => 'light',
		'default_chart_type'    => 'both',
		'default_layout'        => 'split',
	);

	/*
	 * Guard against accidental recursion: calcforge_sanitize_settings() (and thus
	 * calcforge_get_settings()/calcforge_get_default_attributes()) calls this
	 * function, so a filter on `calcforge_default_settings` that calls back into
	 * any settings getter would otherwise loop forever.
	 */
	global $calcforge_resolving_defaults;

	if ( ! empty( $calcforge_resolving_defaults ) ) {
		return $defaults;
	}

	$calcforge_resolving_defaults = true;

	try {
		$defaults = apply_filters( 'calcforge_default_settings', $defaults );
	} finally {
		$calcforge_resolving_defaults = false;
	}

	return $defaults;
}

/**
 * Returns the saved settings merged over their defaults.
 *
 * @return array<string,mixed> Effective settings.
 */
function calcforge_get_settings() {
	$saved = get_option( 'calcforge_settings', array() );
	$saved = is_array( $saved ) ? $saved : array();

	return calcforge_sanitize_settings( wp_parse_args( $saved, calcforge_get_default_settings() ) );
}

/**
 * Normalizes a loosely typed boolean into a real boolean.
 *
 * Settings arrive from $_POST and block attributes arrive straight from post
 * meta, so a checkbox can show up as "1", "on", "true", "0", "false", or - if a
 * request is crafted - as an array. A bare ! empty() cannot be trusted here: a
 * non-empty array is truthy and the string "false" is a non-empty string, so
 * both would resolve to true and flip a switch an administrator turned off.
 *
 * @param mixed $value   Raw value.
 * @param bool  $fallback Value to use when $value carries no boolean meaning.
 * @return bool Normalized boolean.
 */
function calcforge_sanitize_bool( $value, $fallback = false ) {
	if ( is_bool( $value ) ) {
		return $value;
	}

	/*
	 * Nothing legitimately posts an array for a boolean - the settings form pairs
	 * its checkbox with a hidden scalar - and reading the first entry out of one
	 * would be guesswork. The caller's fallback wins instead, which matches the
	 * "only scalars are cast" contract the settings sanitizer documents for every
	 * other option.
	 */
	if ( is_array( $value ) || is_object( $value ) ) {
		return (bool) $fallback;
	}

	if ( null === $value || '' === $value ) {
		return (bool) $fallback;
	}

	$filtered = filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

	// An unrecognized string is not a boolean, so the caller's fallback wins.
	return null === $filtered ? (bool) $fallback : $filtered;
}

/**
 * Sanitizes a settings array against the known whitelist.
 *
 * Used both by the Settings API sanitize callback and defensively in
 * calcforge_get_settings(), so stored values can never be trusted blindly.
 *
 * @param mixed $settings Raw settings value.
 * @return array<string,mixed> Sanitized settings.
 */
function calcforge_sanitize_settings( $settings ) {
	$defaults = calcforge_get_default_settings();
	$raw      = is_array( $settings ) ? $settings : array();

	/*
	 * Both values arrive straight from $_POST, so they can be arrays or objects
	 * if the request is crafted. Only scalars are cast; anything else falls
	 * through to the default rather than emitting an "array to string" warning
	 * from inside a sanitizer.
	 */
	$symbol = isset( $raw['currency_symbol'] ) && is_scalar( $raw['currency_symbol'] )
		? sanitize_text_field( (string) $raw['currency_symbol'] )
		: '';
	if ( '' === $symbol ) {
		$symbol = $defaults['currency_symbol'];
	}

	$positions = calcforge_get_currency_positions();
	$position  = isset( $raw['currency_position'] ) && is_scalar( $raw['currency_position'] )
		? (string) $raw['currency_position']
		: (string) $defaults['currency_position'];
	if ( ! array_key_exists( $position, $positions ) ) {
		$position = array_key_exists( (string) $defaults['currency_position'], $positions )
			? (string) $defaults['currency_position']
			: 'prefix';
	}

	// A non-numeric string casts to 0.0 here, so the default is lost; test with
	// is_numeric() rather than is_scalar() so unusable input takes the default.
	$rate = isset( $raw['default_interest_rate'] ) && is_numeric( $raw['default_interest_rate'] )
		? (float) $raw['default_interest_rate']
		: (float) $defaults['default_interest_rate'];
	$rate = calcforge_clamp_float( $rate, 0, 100 );

	$precision = isset( $raw['decimal_precision'] ) && is_scalar( $raw['decimal_precision'] )
		? absint( $raw['decimal_precision'] )
		: (int) $defaults['decimal_precision'];
	$precision = min( max( $precision, 0 ), 4 );

	// is_scalar() keeps arrays and objects out, but still admits non-numeric
	// strings, which would clamp to 0.0. is_numeric() is the tighter test.
	$loan_amount = isset( $raw['default_loan_amount'] ) && is_numeric( $raw['default_loan_amount'] )
		? calcforge_clamp_float( $raw['default_loan_amount'], 0, 999999999999 )
		: (float) $defaults['default_loan_amount'];

	$down_payment = isset( $raw['default_down_payment'] ) && is_numeric( $raw['default_down_payment'] )
		? calcforge_clamp_float( $raw['default_down_payment'], 0, 999999999999 )
		: (float) $defaults['default_down_payment'];

	$loan_term = isset( $raw['default_loan_term'] ) && is_numeric( $raw['default_loan_term'] )
		? absint( $raw['default_loan_term'] )
		: (int) $defaults['default_loan_term'];
	$loan_term = min( max( $loan_term, 1 ), 60 );

	$theme = isset( $raw['default_theme'] ) && is_scalar( $raw['default_theme'] )
		? (string) $raw['default_theme']
		: (string) $defaults['default_theme'];
	if ( ! array_key_exists( $theme, calcforge_get_skins() ) ) {
		$theme = array_key_exists( (string) $defaults['default_theme'], calcforge_get_skins() )
			? (string) $defaults['default_theme']
			: 'light';
	}

	$chart_type = isset( $raw['default_chart_type'] ) && is_scalar( $raw['default_chart_type'] )
		? (string) $raw['default_chart_type']
		: (string) $defaults['default_chart_type'];
	if ( ! array_key_exists( $chart_type, calcforge_get_chart_types() ) ) {
		$chart_type = array_key_exists( (string) $defaults['default_chart_type'], calcforge_get_chart_types() )
			? (string) $defaults['default_chart_type']
			: 'both';
	}

	$layout = isset( $raw['default_layout'] ) && is_scalar( $raw['default_layout'] )
		? (string) $raw['default_layout']
		: (string) $defaults['default_layout'];
	if ( ! array_key_exists( $layout, calcforge_get_layouts() ) ) {
		$layout = array_key_exists( (string) $defaults['default_layout'], calcforge_get_layouts() )
			? (string) $defaults['default_layout']
			: 'split';
	}

	return array(
		'currency_symbol'       => wp_html_excerpt( $symbol, 8, '' ),
		'currency_position'     => $position,
		'default_interest_rate' => $rate,
		'decimal_precision'     => $precision,
		'enable_amortization'   => calcforge_sanitize_bool(
			isset( $raw['enable_amortization'] ) ? $raw['enable_amortization'] : $defaults['enable_amortization']
		),
		'default_loan_amount'   => $loan_amount,
		'default_down_payment'  => $down_payment,
		'default_loan_term'     => $loan_term,
		'default_theme'         => $theme,
		'default_chart_type'    => $chart_type,
		'default_layout'        => $layout,
	);
}

/**
 * Maps a site setting key to the block attribute it seeds.
 *
 * Used to push the site-wide defaults into the registered block attributes so a
 * newly inserted block starts from the administrator's choices.
 *
 * @return array<string,string> Setting key => block attribute name.
 */
function calcforge_get_settings_attribute_map() {
	return array(
		'default_loan_amount'   => 'loanAmount',
		'default_interest_rate' => 'interestRate',
		'default_loan_term'     => 'loanTerm',
		'default_down_payment'  => 'downPayment',
		'currency_symbol'       => 'currencySymbol',
		'currency_position'     => 'currencyPosition',
		'enable_amortization'   => 'showAmortization',
		'default_theme'         => 'theme',
		'default_chart_type'    => 'chartType',
		'default_layout'        => 'layout',
	);
}

/**
 * Returns the default block attributes, derived from the site-wide settings.
 *
 * Third parties may override everything via the `calcforge_default_attributes`
 * filter.
 *
 * @return array<string,mixed> Default block attributes.
 */
function calcforge_get_default_attributes() {
	$settings = calcforge_get_settings();

	$defaults = array(
		'loanAmount'           => (float) $settings['default_loan_amount'],
		'interestRate'         => (float) $settings['default_interest_rate'],
		'loanTerm'             => (int) $settings['default_loan_term'],
		'downPayment'          => (float) $settings['default_down_payment'],
		'currencySymbol'       => (string) $settings['currency_symbol'],
		'currencyPosition'     => (string) $settings['currency_position'],
		'showAmortization'     => (bool) $settings['enable_amortization'],
		'showCharts'           => true,
		'chartType'            => (string) $settings['default_chart_type'],
		'layout'               => (string) $settings['default_layout'],
		'formColumns'          => 'wide',
		'panelOrder'           => calcforge_get_panel_keys(),
		'theme'                => (string) $settings['default_theme'],
		'showSliders'          => true,
		'showResults'          => true,
		'showCosts'            => false,
		'propertyTax'          => 0.0,
		'homeInsurance'        => 0.0,
		'hoaFee'               => 0.0,
		'pmi'                  => 0.0,
		'otherCosts'           => 0.0,
		// Tax is conventionally quoted as a rate; the rest are usually a bill amount.
		'propertyTaxUnit'      => 'percent',
		'homeInsuranceUnit'    => 'amount',
		'hoaFeeUnit'           => 'amount',
		'pmiUnit'              => 'amount',
		'otherCostsUnit'       => 'amount',
		'paymentFontSize'      => 0,
		'paymentFontWeight'    => '',
		'fontFamily'           => 'inherit',
		'accentColor'          => '',
		'accentAltColor'       => '',
		'labelColor'           => '',
		'fieldTextColor'       => '',
		'fieldBackgroundColor' => '',
		'fieldBorderColor'     => '',
		'design'                => array(),
	);

	/**
	 * Filters the default block attributes.
	 *
	 * @param array<string,mixed> $defaults Default attributes for the calculator block.
	 */
	return apply_filters( 'calcforge_default_attributes', $defaults );
}

/**
 * Returns the legacy per-block colour attributes, derived from the token schema.
 *
 * These six top-level attributes predate the design tab and are still honoured
 * for blocks saved before it existed. They used to carry their own attribute =>
 * CSS variable map, separate from the one in render.php, and the two disagreed
 * about two variable names: the editor wrote names the stylesheet never read, so
 * those overrides did nothing in the editor while working on the frontend.
 * Deriving the list from the token schema removes the second map entirely.
 *
 * @return array<int,array<string,string>> Colour control descriptors.
 */
function calcforge_get_color_attributes() {
	$controls = array();

	foreach ( calcforge_get_design_token_map() as $token ) {
		if ( empty( $token['legacy'] ) ) {
			continue;
		}

		$controls[] = array(
			'key'    => $token['legacy'],
			'label'  => $token['label'],
			'cssVar' => $token['var'],
		);
	}

	return $controls;
}

/**
 * Returns the swatches offered by the inspector colour pickers.
 *
 * ColorPalette needs a list of {name, color} entries. Without this it renders
 * an empty popover, so the preset swatches live here alongside the rest of the
 * editor option data. Users can still type an arbitrary colour, because the
 * pickers are not marked disableCustomColors.
 *
 * @return array<int,array{name:string,color:string}> Swatch list.
 */
function calcforge_get_color_swatches() {
	$swatches = array(
		array( '#1a6f4b', __( 'Forest', CALCFORGE_TEXT_DOMAIN ) ),
		array( '#0f766e', __( 'Teal', CALCFORGE_TEXT_DOMAIN ) ),
		array( '#d97706', __( 'Amber', CALCFORGE_TEXT_DOMAIN ) ),
		array( '#b45309', __( 'Bronze', CALCFORGE_TEXT_DOMAIN ) ),
		array( '#b91c1c', __( 'Red', CALCFORGE_TEXT_DOMAIN ) ),
		array( '#be123c', __( 'Crimson', CALCFORGE_TEXT_DOMAIN ) ),
		array( '#7c3aed', __( 'Violet', CALCFORGE_TEXT_DOMAIN ) ),
		array( '#4f46e5', __( 'Indigo', CALCFORGE_TEXT_DOMAIN ) ),
		array( '#1d4ed8', __( 'Blue', CALCFORGE_TEXT_DOMAIN ) ),
		array( '#0369a1', __( 'Sky', CALCFORGE_TEXT_DOMAIN ) ),
		array( '#0e7490', __( 'Cyan', CALCFORGE_TEXT_DOMAIN ) ),
		array( '#15803d', __( 'Green', CALCFORGE_TEXT_DOMAIN ) ),
		array( '#4d7c0f', __( 'Lime', CALCFORGE_TEXT_DOMAIN ) ),
		array( '#111827', __( 'Ink', CALCFORGE_TEXT_DOMAIN ) ),
		array( '#374151', __( 'Slate', CALCFORGE_TEXT_DOMAIN ) ),
		array( '#6b7280', __( 'Gray', CALCFORGE_TEXT_DOMAIN ) ),
		array( '#d1d5db', __( 'Silver', CALCFORGE_TEXT_DOMAIN ) ),
		array( '#f3f4f6', __( 'Mist', CALCFORGE_TEXT_DOMAIN ) ),
		array( '#ffffff', __( 'White', CALCFORGE_TEXT_DOMAIN ) ),
	);

	$list = array();
	foreach ( $swatches as $swatch ) {
		$list[] = array(
			'name'  => $swatch[1],
			'color' => $swatch[0],
		);
	}

	return $list;
}

/**
 * Clamps a numeric value between a minimum and maximum bound.
 *
 * @param mixed $value   Value to clamp. Non-numeric input becomes 0.0.
 * @param float $minimum Lower bound.
 * @param float $maximum Upper bound.
 * @return float Clamped float value.
 */
function calcforge_clamp_float( $value, $minimum, $maximum ) {
	$number = is_numeric( $value ) ? (float) $value : 0.0;

	return min( max( $number, $minimum ), $maximum );
}

/**
 * Sanitizes and validates raw block attributes server-side.
 *
 * The editor validates attributes client-side too, but client input must never
 * be trusted: every dynamic value passes through this function before use in
 * render.php or the REST endpoint.
 *
 * @param mixed $attributes Raw attributes from the block or REST request.
 * @return array<string,mixed> Sanitized attributes with all keys present.
 */
function calcforge_sanitize_attributes( $attributes ) {
	$defaults = calcforge_get_default_attributes();
	$raw      = is_array( $attributes ) ? $attributes : array();

	/*
	 * isset() is true for a key that holds garbage, so a present-but-non-numeric
	 * value would reach calcforge_clamp_float() and clamp to 0.0, quietly
	 * replacing a configured default with a zero loan or a zero interest rate.
	 * Requiring is_numeric() sends unusable input down the default path instead.
	 */
	$loan_amount = isset( $raw['loanAmount'] ) && is_numeric( $raw['loanAmount'] )
		? calcforge_clamp_float( $raw['loanAmount'], 0, 999999999999 )
		: (float) $defaults['loanAmount'];

	$interest_rate = isset( $raw['interestRate'] ) && is_numeric( $raw['interestRate'] )
		? calcforge_clamp_float( $raw['interestRate'], 0, 100 )
		: (float) $defaults['interestRate'];

	$loan_term = isset( $raw['loanTerm'] ) && is_numeric( $raw['loanTerm'] )
		? absint( $raw['loanTerm'] )
		: (int) $defaults['loanTerm'];
	$loan_term = min( max( $loan_term, 1 ), 60 );

	$down_payment = isset( $raw['downPayment'] ) && is_numeric( $raw['downPayment'] )
		? calcforge_clamp_float( $raw['downPayment'], 0, 999999999999 )
		: (float) $defaults['downPayment'];
	$down_payment = min( $down_payment, $loan_amount );

	$currency_symbol = isset( $raw['currencySymbol'] ) && is_scalar( $raw['currencySymbol'] )
		? sanitize_text_field( (string) $raw['currencySymbol'] )
		: '';
	if ( '' === $currency_symbol ) {
		$currency_symbol = (string) $defaults['currencySymbol'];
	}
	$currency_symbol = wp_html_excerpt( $currency_symbol, 8, '' );

	$skins = calcforge_get_skin_slugs();

	/*
	 * A skin that is present but unknown - a slug retired in a later version, or
	 * a typo - falls back to the configured default like every other enum below,
	 * rather than to a hardcoded skin that would ignore the site's own choice.
	 */
	$theme = isset( $raw['theme'] ) && is_scalar( $raw['theme'] ) && in_array( (string) $raw['theme'], $skins, true )
		? (string) $raw['theme']
		: (string) $defaults['theme'];

	if ( ! in_array( $theme, $skins, true ) ) {
		$theme = 'light';
	}

	$chart_types = array_keys( calcforge_get_chart_types() );

	if ( isset( $raw['chartType'] ) && in_array( $raw['chartType'], $chart_types, true ) ) {
		$chart_type = (string) $raw['chartType'];
	} else {
		$chart_type = (string) $defaults['chartType'];
		if ( ! in_array( $chart_type, $chart_types, true ) ) {
			$chart_type = 'both';
		}
	}

	$layouts = array_keys( calcforge_get_layouts() );

	if ( isset( $raw['layout'] ) && in_array( $raw['layout'], $layouts, true ) ) {
		$layout = (string) $raw['layout'];
	} else {
		$layout = (string) $defaults['layout'];
		if ( ! in_array( $layout, $layouts, true ) ) {
			$layout = 'split';
		}
	}

	$form_columns = array_keys( calcforge_get_form_columns() );

	if ( isset( $raw['formColumns'] ) && in_array( $raw['formColumns'], $form_columns, true ) ) {
		$form_columns_key = (string) $raw['formColumns'];
	} else {
		$form_columns_key = (string) $defaults['formColumns'];
		if ( ! in_array( $form_columns_key, $form_columns, true ) ) {
			$form_columns_key = 'wide';
		}
	}

	/*
	 * Panel order is stored as an array of panel keys. It is normalized through
	 * calcforge_resolve_panel_order() with every panel treated as visible, so an
	 * unknown or duplicated key cannot survive into the rendered markup.
	 */
	$panel_order = calcforge_resolve_panel_order(
		isset( $raw['panelOrder'] ) ? (array) $raw['panelOrder'] : array(),
		calcforge_get_panel_keys()
	);

	$payment_font_size = array_key_exists( 'paymentFontSize', $raw ) && is_numeric( $raw['paymentFontSize'] )
		? calcforge_clamp_float( $raw['paymentFontSize'], 0, 120 )
		: (float) $defaults['paymentFontSize'];

	$requested_weight  = isset( $raw['paymentFontWeight'] ) && is_scalar( $raw['paymentFontWeight'] )
		? (string) $raw['paymentFontWeight']
		: (string) $defaults['paymentFontWeight'];
	$payment_font_weight = array_key_exists( $requested_weight, calcforge_get_font_weights() ) && '' !== $requested_weight
		? $requested_weight
		: '';

	$positions = array_keys( calcforge_get_currency_positions() );

	if ( isset( $raw['currencyPosition'] ) && in_array( $raw['currencyPosition'], $positions, true ) ) {
		$currency_position = (string) $raw['currencyPosition'];
	} else {
		$currency_position = (string) $defaults['currencyPosition'];
		if ( ! in_array( $currency_position, $positions, true ) ) {
			$currency_position = 'prefix';
		}
	}

	/*
	 * Per-block color overrides. Values are hex colors from the editor's
	 * ColorPalette; an empty string means "use the active skin's value".
	 */
	$colors = array();

	foreach ( calcforge_get_color_attributes() as $control ) {
		$key = $control['key'];

		if ( ! isset( $raw[ $key ] ) || ! is_scalar( $raw[ $key ] ) ) {
			$colors[ $key ] = '';
			continue;
		}

		$color          = sanitize_hex_color( (string) $raw[ $key ] );
		$colors[ $key ] = $color ? $color : '';
	}

	$font_families = array_keys( calcforge_get_font_families() );
	$requested_font = isset( $raw['fontFamily'] ) && is_scalar( $raw['fontFamily'] )
		? (string) $raw['fontFamily']
		: (string) $defaults['fontFamily'];
	$font_family    = in_array( $requested_font, $font_families, true ) ? $requested_font : 'inherit';

	/*
	 * Recurring ownership costs. Each is stored in whatever unit the author chose,
	 * so a tax bill entered as a cash amount and a tax rate entered as a percent
	 * both round-trip through the control unchanged. The upper bound is generous
	 * because a percentage is also allowed to carry an annual cash figure for
	 * fields that switch units.
	 */
	$cost_units = calcforge_get_cost_units();
	$costs      = array();

	foreach ( calcforge_get_cost_components() as $key => $component ) {
		if ( empty( $component['percent'] ) ) {
			continue;
		}

		$attribute = calcforge_get_cost_attribute( $key );

		/*
		 * The author's choice of unit is saved alongside the figure. Reading it
		 * here rather than assuming the schema default is what makes a field
		 * labelled in dollars behave as an amount instead of being multiplied by
		 * the home price.
		 */
		$requested_unit = isset( $raw[ $attribute . 'Unit' ] ) && is_scalar( $raw[ $attribute . 'Unit' ] )
			? (string) $raw[ $attribute . 'Unit' ]
			: '';

		/*
		 * A saved choice wins, then the attribute default, and only then the
		 * schema's own default. Reading the schema first would report every field
		 * as a percentage, because that is what a fresh block can express, and
		 * would defeat the amount defaults set below.
		 */
		$default_unit = isset( $defaults[ $attribute . 'Unit' ] ) ? (string) $defaults[ $attribute . 'Unit' ] : '';

		if ( in_array( $requested_unit, array( 'percent', 'amount' ), true ) ) {
			$unit = $requested_unit;
		} elseif ( in_array( $default_unit, array( 'percent', 'amount' ), true ) ) {
			$unit = $default_unit;
		} else {
			$unit = isset( $cost_units[ $attribute ] ) ? $cost_units[ $attribute ] : 'percent';
		}

		/*
		 * The upper bound has to follow the unit rather than be fixed at 100. A
		 * percentage field is capped there, but clamping an amount at 100 would
		 * silently turn a $1500 insurance premium into $100 before the conversion
		 * ever ran.
		 */
		$maximum = 'amount' === $unit ? 99999999.0 : 100.0;

		if ( isset( $raw[ $attribute ] ) && is_numeric( $raw[ $attribute ] ) ) {
			$costs[ $attribute ] = calcforge_clamp_float( $raw[ $attribute ], 0, $maximum );
		} else {
			$default_unit = isset( $defaults[ $attribute . 'Unit' ] ) ? (string) $defaults[ $attribute . 'Unit' ] : $unit;
			$default_max  = 'amount' === $default_unit ? 99999999.0 : 100.0;

			$costs[ $attribute ] = isset( $defaults[ $attribute ] )
				? calcforge_clamp_float( $defaults[ $attribute ], 0, $default_max )
				: 0.0;
		}

		$costs[ $attribute . 'Unit' ] = $unit;
	}

	$sanitized = array(
		'loanAmount'       => $loan_amount,
		'interestRate'     => $interest_rate,
		'loanTerm'         => $loan_term,
		'downPayment'      => $down_payment,
		'currencySymbol'   => $currency_symbol,
		'currencyPosition' => $currency_position,
		'showAmortization' => calcforge_sanitize_bool(
			isset( $raw['showAmortization'] ) ? $raw['showAmortization'] : $defaults['showAmortization']
		),
		'showCharts'       => calcforge_sanitize_bool(
			isset( $raw['showCharts'] ) ? $raw['showCharts'] : $defaults['showCharts']
		),
		'chartType'        => $chart_type,
		'layout'           => $layout,
		'formColumns'      => $form_columns_key,
		'panelOrder'       => $panel_order,
		'paymentFontSize'  => $payment_font_size,
		'paymentFontWeight' => $payment_font_weight,
		'theme'            => $theme,
		'showSliders'      => calcforge_sanitize_bool(
			isset( $raw['showSliders'] ) ? $raw['showSliders'] : $defaults['showSliders']
		),
		'showResults'      => calcforge_sanitize_bool(
			isset( $raw['showResults'] ) ? $raw['showResults'] : $defaults['showResults']
		),
		'showCosts'        => calcforge_sanitize_bool(
			isset( $raw['showCosts'] ) ? $raw['showCosts'] : $defaults['showCosts']
		),
		'fontFamily'       => $font_family,
	);

	foreach ( $costs as $cost_key => $cost_value ) {
		$sanitized[ $cost_key ] = $cost_value;
	}

	foreach ( $colors as $key => $value ) {
		$sanitized[ $key ] = $value;
	}

	/*
	 * The design object holds every appearance override. It is stored as a flat
	 * key => string map, validated against the token schema so only known keys
	 * with legal values survive, which keeps arbitrary CSS out of post content.
	 */
	$sanitized['design'] = calcforge_sanitize_design(
		isset( $raw['design'] ) ? $raw['design'] : array()
	);

	return $sanitized;
}

/**
 * Default number of calculation requests allowed per client per window.
 */
const CALCFORGE_RATE_LIMIT_MAX = 30;

/**
 * Default length of the calculation rate limit window, in seconds.
 */
const CALCFORGE_RATE_LIMIT_WINDOW = MINUTE_IN_SECONDS;

/**
 * Resolves the identifier used to bucket rate limited requests.
 *
 * Only REMOTE_ADDR is trusted. Forwarded headers such as X-Forwarded-For are
 * attacker controlled on a direct connection, so honouring them would let a
 * caller mint a fresh bucket per request and defeat the limit entirely. Sites
 * behind a proxy or CDN can supply the real client address through the filter,
 * which is the only supported way to opt in.
 *
 * @return string Opaque bucket key for the current caller.
 */
function calcforge_rate_limit_client_key() {
	$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

	/**
	 * Filters the identifier used to rate limit calculation requests.
	 *
	 * Return a trusted per-visitor value, such as a CDN supplied client IP, to
	 * keep separate visitors in separate buckets behind a proxy.
	 *
	 * @param string $remote The remote address reported by the server.
	 */
	$key = apply_filters( 'calcforge_rate_limit_client_key', $remote );

	// An empty or over-long key would collapse unrelated callers into one
	// shared bucket, so fall back to a per-request value that cannot be
	// shared when no address is available.
	if ( ! is_string( $key ) || '' === $key || strlen( $key ) > 45 ) {
		$key = 'unknown:' . wp_generate_uuid4();
	}

	return $key;
}

/**
 * Reads the current rate limit window for a bucket without recording anything.
 *
 * Kept side-effect free so it is safe to call from a permission callback, which
 * WordPress may invoke more than once for the same request.
 *
 * @param string $bucket Opaque bucket key, typically from calcforge_rate_limit_client_key().
 * @return array{count:int,retry_after:int} Requests recorded in the live window, and seconds left in it.
 */
function calcforge_rate_limit_peek( $bucket ) {
	$empty = array(
		'count'       => 0,
		'retry_after' => 0,
	);

	$stored = get_transient( 'calcforge_rl_' . md5( $bucket ) );

	if ( ! is_array( $stored ) || ! isset( $stored['count'], $stored['expires'] ) ) {
		return $empty;
	}

	$remaining = (int) $stored['expires'] - time();

	if ( $remaining <= 0 ) {
		return $empty;
	}

	return array(
		'count'       => (int) $stored['count'],
		'retry_after' => $remaining,
	);
}

/**
 * Records a hit against a fixed rate limit window and reports the outcome.
 *
 * The window is fixed rather than sliding: the expiry is stamped once when the
 * bucket is created and is never extended, so sustained low-rate traffic still
 * trips the limit. The remaining time is passed to set_transient() on every hit
 * because a bare update with no expiry means "never expires" to a persistent
 * object cache, which would lock out a caller permanently once tripped.
 *
 * This is a soft abuse guard, not a security boundary. The read-then-write is
 * not atomic, so concurrent requests can overshoot the limit slightly; that is
 * an acceptable trade for keeping the plugin free of direct database queries.
 *
 * @param string $bucket Opaque bucket key, typically from calcforge_rate_limit_client_key().
 * @param int    $limit  Requests permitted per window. Values below 1 disable limiting.
 * @param int    $window Window length in seconds.
 * @return array{count:int,limit:int,exceeded:bool,retry_after:int} Outcome for this hit.
 */
function calcforge_rate_limit_hit( $bucket, $limit, $window ) {
	$limit  = (int) $limit;
	$window = (int) $window;

	if ( $limit < 1 || $window < 1 ) {
		return array(
			'count'       => 0,
			'limit'       => 0,
			'exceeded'    => false,
			'retry_after' => 0,
		);
	}

	$now     = time();
	$current = calcforge_rate_limit_peek( $bucket );
	$live    = $current['retry_after'] > 0;

	// A live window keeps the expiry it was created with, so it never slides.
	$expires  = $live ? $now + $current['retry_after'] : $now + $window;
	$count    = $live ? $current['count'] + 1 : 1;
	$exceeded = $count > $limit;

	$retry_after = max( 1, $expires - $now );
	set_transient( 'calcforge_rl_' . md5( $bucket ), array( 'count' => $count, 'expires' => $expires ), $retry_after );

	return array(
		'count'       => $count,
		'limit'       => $limit,
		'exceeded'    => $exceeded,
		'retry_after' => $exceeded ? $retry_after : 0,
	);
}

/**
 * Calculates the monthly payment for an amortizing loan.
 *
 * Uses the standard annuity formula M = P * r / (1 - (1 + r)^-n). A zero
 * interest rate degrades gracefully to straight-line division.
 *
 * @param float $principal   Financed principal (loan amount minus down payment).
 * @param float $annual_rate Annual interest rate as a percentage (e.g., 6.5).
 * @param int   $term_years  Loan term in whole years.
 * @return float Monthly payment amount, rounded to 2 decimals.
 */
function calcforge_calculate_monthly_payment( $principal, $annual_rate, $term_years ) {
	$principal = (float) $principal;
	$months    = absint( $term_years ) * 12;

	if ( $principal <= 0 || $months < 1 ) {
		return 0.0;
	}

	$monthly_rate = (float) $annual_rate / 100 / 12;

	if ( $monthly_rate <= 0 ) {
		return round( $principal / $months, 2 );
	}

	$payment = $principal * $monthly_rate / ( 1 - pow( 1 + $monthly_rate, -$months ) );

	return round( $payment, 2 );
}

/**
 * Builds an annual amortization schedule for a loan.
 *
 * Iterates month by month so the math matches real statements, then aggregates
 * the rows per year to keep rendered tables lightweight.
 *
 * @param float $principal   Financed principal.
 * @param float $annual_rate Annual interest rate as a percentage.
 * @param int   $term_years  Loan term in whole years.
 * @return array<int,array<string,float|int>> Yearly rows with principal, interest, and balance.
 */
function calcforge_calculate_amortization_schedule( $principal, $annual_rate, $term_years ) {
	$principal    = (float) $principal;
	$monthly_rate = (float) $annual_rate / 100 / 12;
	$months       = absint( $term_years ) * 12;

	if ( $principal <= 0 || $months < 1 ) {
		return array();
	}

	$payment  = calcforge_calculate_monthly_payment( $principal, $annual_rate, $term_years );
	$balance  = $principal;
	$schedule = array();

	for ( $month = 1; $month <= $months; $month++ ) {
		$interest      = round( $balance * $monthly_rate, 2 );
		$principal_pay = min( round( $payment - $interest, 2 ), $balance );
		$balance       = round( $balance - $principal_pay, 2 );

		$year_index = (int) ceil( $month / 12 );

		if ( ! isset( $schedule[ $year_index ] ) ) {
			$schedule[ $year_index ] = array(
				'year'      => $year_index,
				'principal' => 0.0,
				'interest'  => 0.0,
				'balance'   => 0.0,
			);
		}

		$schedule[ $year_index ]['principal'] += $principal_pay;
		$schedule[ $year_index ]['interest']  += $interest;
		$schedule[ $year_index ]['balance']    = $balance;
	}

	return array_values( $schedule );
}

/**
 * Resolves the colour a cost component is drawn in.
 *
 * The design token wins when the author has overridden it, so the swatch beside
 * the row and the fill inside the chart come from the same place and cannot drift
 * apart. Falls back to the stylesheet's own default by returning an empty string,
 * which lets the skin decide.
 *
 * @param array<string,mixed> $attributes Sanitized block attributes.
 * @param string              $component  Component key.
 * @return string Hex colour, or '' to defer to the stylesheet.
 */
function calcforge_get_cost_component_color( $attributes, $component ) {
	$components = calcforge_get_cost_components();

	if ( ! isset( $components[ $component ] ) ) {
		return '';
	}

	$token = (string) $components[ $component ]['token'];

	// The swatch is painted inline, so it needs a concrete colour, not a var().
	$values = calcforge_get_design_values( $attributes );

	if ( isset( $values[ $token ] ) ) {
		return $values[ $token ];
	}

	return '';
}

/**
 * Returns the cost components that should be drawn, in order.
 *
 * Kept beside calcforge_get_cost_components() so the chart legend and the results
 * panel agree about what exists, and both agree that a zero component is simply
 * absent rather than a zero-width slice.
 *
 * @param array<string,mixed> $result Calculation result.
 * @return array<string,float> Component key => monthly amount, P&I first.
 */
function calcforge_get_active_costs( $result ) {
	$monthly = isset( $result['monthly_costs'] ) ? (array) $result['monthly_costs'] : array();
	$active  = array(
		'pi' => round( isset( $result['monthly_payment'] ) ? (float) $result['monthly_payment'] : 0.0, 2 ),
	);

	foreach ( calcforge_get_cost_components() as $key => $component ) {
		if ( 'pi' === $key ) {
			continue;
		}

		$amount = isset( $monthly[ $key ] ) ? (float) $monthly[ $key ] : 0.0;

		if ( $amount > 0 ) {
			$active[ $key ] = round( $amount, 2 );
		}
	}

	return $active;
}

/**
 * Converts the stored cost inputs into annual cash amounts.
 *
 * A percentage field multiplies the home price, an amount field is already cash.
 * The home price is the block's `loanAmount`, which is the purchase price rather
 * than the financed amount, because tax and insurance are levied on the property
 * the buyer ends up with and not on the part they borrowed against.
 *
 * @param array<string,mixed> $attrs Sanitized block attributes.
 * @return array<string,float> Component key => annual amount.
 */
function calcforge_calculate_annual_costs( $attrs ) {
	$home_price = (float) $attrs['loanAmount'];
	$units      = calcforge_resolve_cost_units( $attrs );
	$annual     = array();

	foreach ( calcforge_get_cost_components() as $key => $component ) {
		if ( empty( $component['percent'] ) ) {
			continue;
		}

		$attribute = calcforge_get_cost_attribute( $key );
		$value     = isset( $attrs[ $attribute ] ) ? (float) $attrs[ $attribute ] : 0.0;

		if ( $value <= 0 ) {
			$annual[ $key ] = 0.0;
			continue;
		}

		$is_percent = ! isset( $units[ $attribute ] ) || 'percent' === $units[ $attribute ];

		$annual[ $key ] = $is_percent
			? round( $home_price * $value / 100, 2 )
			: round( $value, 2 );
	}

	return $annual;
}

/**
 * Returns the month PMI stops being charged.
 *
 * Federal rules require the lender to cancel PMI once the balance reaches 78% of
 * the original value, and permit cancellation at the borrower's request from
 * 80%. Charging it to term end is wrong; cancelling it earlier than the statute
 * allows is not the borrower's entitlement either. So the balance is walked
 * forward month by month and the first month at or below the 80% threshold is
 * returned, which is the later of the two and therefore the safe one.
 *
 * Returned as a 1-based month index so it can be compared against the schedule
 * loop directly. Zero means PMI is never charged.
 *
 * @param float $principal   Original financed principal.
 * @param float $home_price  Original purchase price.
 * @param float $annual_rate Annual interest rate as a percentage.
 * @param int   $months      Loan term in months.
 * @return int Month PMI ends, or 0 when there is no PMI or no threshold to reach.
 */
function calcforge_get_pmi_end_month( $principal, $home_price, $annual_rate, $months ) {
	if ( $home_price <= 0 || $principal <= 0 || $months <= 0 ) {
		return 0;
	}

	// Below this there is no PMI to cancel, whatever the author entered.
	$original_ltv = $principal / $home_price;

	if ( $original_ltv <= 0.80 ) {
		return 0;
	}

	$monthly_rate = $annual_rate / 100 / 12;
	$payment      = calcforge_calculate_monthly_payment( $principal, $annual_rate, (int) ceil( $months / 12 ) );
	$balance      = $principal;
	$threshold    = $home_price * 0.80;

	for ( $month = 1; $month <= $months; $month++ ) {
		$interest = $balance * $monthly_rate;
		$paid     = min( $payment, $balance + $interest );

		$balance -= max( $paid - $interest, 0 );

		if ( $balance <= $threshold ) {
			return $month;
		}
	}

	// The balance never reaches the threshold inside the term.
	return 0;
}

/**
 * Runs the full calculation for a set of block attributes.
 *
 * The result passes through the `calcforge_calculation_result` filter, the
 * sanctioned extension point for currency conversion plugins and similar.
 *
 * @param mixed $attributes Raw or partial block attributes.
 * @return array<string,mixed> Calculation result including schedule when enabled.
 */
function calcforge_calculate( $attributes ) {
	$attrs  = calcforge_sanitize_attributes( $attributes );
	$months = $attrs['loanTerm'] * 12;

	$result = array(
		'principal'       => round( $attrs['loanAmount'] - $attrs['downPayment'], 2 ),
		'monthly_payment' => 0.0,
		'total_paid'      => 0.0,
		'total_interest'  => 0.0,
		'months'          => $months,
		'schedule'        => array(),
	);

	$result['principal']       = max( $result['principal'], 0 );
	$result['monthly_payment'] = calcforge_calculate_monthly_payment( $result['principal'], $attrs['interestRate'], $attrs['loanTerm'] );
	$result['total_paid']      = round( $result['monthly_payment'] * $months, 2 );
	$result['total_interest']  = round( max( $result['total_paid'] - $result['principal'], 0 ), 2 );

	/*
	 * loanAmount is the purchase price in this block, not the amount financed:
	 * principal is derived from it by subtracting the down payment. So the value
	 * of the property, which is what tax, insurance and the PMI threshold are all
	 * measured against, is loanAmount itself. Adding the down payment back on
	 * would inflate every percentage and put PMI on borrowers above 80% LTV.
	 */
	$home_price = (float) $attrs['loanAmount'];
	$annual     = calcforge_calculate_annual_costs( $attrs );

	/*
	 * PMI is the one component that stops part way through the loan, so it cannot
	 * be flattened into a single monthly figure alongside the others. Its
	 * lifetime cost is the premium paid up to the cancellation month only.
	 */
	$pmi_base = isset( $annual['pmi'] ) ? (float) $annual['pmi'] : 0.0;

	/*
	 * Only worth working out when a premium was actually entered. Otherwise the
	 * field would report a cancellation month for a loan that never carried PMI,
	 * which reads as though something was paid and then stopped.
	 */
	$pmi_end = $pmi_base > 0
		? calcforge_get_pmi_end_month( $result['principal'], $home_price, (float) $attrs['interestRate'], $months )
		: 0;

	$result['pmi_end_month'] = $pmi_end;

	$monthly_costs = array(
		'tax'       => isset( $annual['tax'] ) ? round( $annual['tax'] / 12, 2 ) : 0.0,
		'insurance' => isset( $annual['insurance'] ) ? round( $annual['insurance'] / 12, 2 ) : 0.0,
		'hoa'       => isset( $annual['hoa'] ) ? round( $annual['hoa'] / 12, 2 ) : 0.0,
		'other'     => isset( $annual['other'] ) ? round( $annual['other'] / 12, 2 ) : 0.0,
	);

	// Month one carries PMI whenever a premium was entered at all.
	$monthly_costs['pmi'] = $pmi_base > 0 ? round( $pmi_base / 12, 2 ) : 0.0;

	$result['monthly_costs'] = $monthly_costs;

	/*
	 * The first month's true outlay, which is what a buyer actually writes a
	 * cheque for. Later months drop PMI once the threshold is crossed.
	 */
	$result['total_monthly_cost'] = round( $result['monthly_payment'] + array_sum( $monthly_costs ), 2 );

	$pmi_months = $pmi_end > 0 ? $pmi_end : ( $pmi_base > 0 ? $months : 0 );

	$result['total_pmi']      = round( $pmi_months * ( $pmi_base / 12 ), 2 );
	$result['total_costs']    = round( $result['total_pmi'] + ( array_sum( $annual ) - $pmi_base ) * ( $months / 12 ), 2 );
	$result['total_out_of_pocket'] = round( $result['total_paid'] + $result['total_costs'], 2 );

	if ( $attrs['showAmortization'] ) {
		$result['schedule'] = calcforge_calculate_amortization_schedule( $result['principal'], $attrs['interestRate'], $attrs['loanTerm'] );
	}

	/**
	 * Filters the calculated mortgage result before output.
	 *
	 * @param array<string,mixed> $result     Computed payment data and schedule.
	 * @param array<string,mixed> $attributes Sanitized block attributes used for the calculation.
	 */
	return apply_filters( 'calcforge_calculation_result', $result, $attrs );
}

/**
 * Resolves the currency symbol for a block instance.
 *
 * Falls back to the site-wide setting when the attribute is empty, then defers
 * to the `calcforge_currency_symbol` filter for locale/currency plugins.
 *
 * @param mixed $attributes Block attributes (sanitized or raw).
 * @return string Currency symbol.
 */
function calcforge_resolve_currency_symbol( $attributes ) {
	$attributes = is_array( $attributes ) ? $attributes : array();
	$settings   = calcforge_get_settings();

	$symbol = ! empty( $attributes['currencySymbol'] )
		? sanitize_text_field( (string) $attributes['currencySymbol'] )
		: '';

	if ( '' === $symbol ) {
		$symbol = (string) $settings['currency_symbol'];
	}

	/**
	 * Filters the currency symbol displayed by the calculator.
	 *
	 * @param string              $symbol     Resolved currency symbol.
	 * @param array<string,mixed> $attributes Block attributes for context.
	 */
	return apply_filters( 'calcforge_currency_symbol', wp_html_excerpt( $symbol, 8, '' ), $attributes );
}

/**
 * Formats an amount as a localized currency string.
 *
 * Callers remain responsible for escaping the returned value on output.
 *
 * @param float  $amount   Amount to format.
 * @param string $symbol   Currency symbol.
 * @param int    $decimals Number of decimal digits (clamped to 0-4).
 * @param string $position Symbol placement: 'prefix' (default) or 'suffix'.
 * @return string Formatted amount such as "$1,234.56".
 */
function calcforge_format_amount( $amount, $symbol, $decimals = 2, $position = 'prefix' ) {
	$decimals  = min( max( absint( $decimals ), 0 ), 4 );
	$formatted = number_format_i18n( (float) $amount, $decimals );

	if ( 'suffix' === $position ) {
		return sprintf( '%s%s', $formatted, $symbol );
	}

	return sprintf( '%s%s', $symbol, $formatted );
}

/**
 * Returns the data the block editor needs from PHP.
 *
 * Shipping the option lists from the server means the inspector, the settings
 * screen, and the attribute sanitizer all read the same arrays, so a new skin or
 * chart type only has to be added in one place.
 *
 * @return array<string,mixed> Editor data payload.
 */
function calcforge_get_editor_data() {
	$settings = calcforge_get_settings();
	$defaults = calcforge_get_default_attributes();

	$skins = array();
	foreach ( calcforge_get_skins() as $value => $label ) {
		$skins[] = array(
			'value' => $value,
			'label' => $label,
		);
	}

	$colors = array();
	foreach ( calcforge_get_color_attributes() as $control ) {
		$colors[] = array(
			'key'    => $control['key'],
			'label'  => $control['label'],
			'cssVar' => $control['cssVar'],
		);
	}

	/*
	 * The design schema travels to the editor so the Design tab, the sanitizer
	 * and the render all read the same token list. Without it the inspector
	 * would need its own copy, which is exactly the duplication that let the
	 * editor and frontend disagree about two colour variables.
	 */
	$design_groups = array();

	foreach ( calcforge_get_design_groups() as $group ) {
		$tokens = array();

		foreach ( $group['tokens'] as $token ) {
			$entry = array(
				'key'   => $token['key'],
				'var'   => $token['var'],
				'label' => $token['label'],
				'type'  => $token['type'],
			);

			if ( 'length' === $token['type'] || 'spacing' === $token['type'] ) {
				$entry['min']  = $token['min'];
				$entry['max']  = $token['max'];
				$entry['step'] = $token['step'];
			}

			if ( ! empty( $token['stack'] ) ) {
				$entry['stack'] = true;
			}

			if ( 'select' === $token['type'] ) {
				$lists        = calcforge_get_design_option_lists();
				$name         = $token['options'];
				$list         = isset( $lists[ $name ] ) ? $lists[ $name ] : array();
				$entry['options'] = array();

				/*
				 * Shipped as ordered pairs, not a value => label object, because
				 * JavaScript enumerates integer-like keys ahead of the rest, so
				 * an object would silently reorder these lists in the editor and
				 * put "Default" last instead of first.
				 */
				foreach ( $list as $value => $label ) {
					$entry['options'][] = array(
						'value' => (string) $value,
						'label' => $label,
					);
				}
			}

			if ( ! empty( $token['legacy'] ) ) {
				$entry['legacy'] = $token['legacy'];
			}

			$tokens[] = $entry;
		}

		$design_groups[] = array(
			'key'     => $group['key'],
			'label'   => $group['label'],
			'summary' => $group['summary'],
			'tokens'  => $tokens,
		);
	}

	return array(
		'skins'           => $skins,
		'chartTypes'      => calcforge_get_chart_types(),
		'layouts'         => calcforge_get_layouts(),
		'formColumns'     => calcforge_get_form_columns(),
		'fontFamilies'    => calcforge_get_font_families(),
		'fontWeights'     => calcforge_get_font_weights(),
		'currencyPosition' => calcforge_get_currency_positions(),
		'colors'          => $colors,
		'designGroups'    => $design_groups,
		'colorSwatches'   => calcforge_get_color_swatches(),
		'fontStacks'      => array(
			'inherit' => '',
			'sans'    => calcforge_get_font_stack( 'sans' ),
			'serif'   => calcforge_get_font_stack( 'serif' ),
			'mono'    => calcforge_get_font_stack( 'mono' ),
		),
		'siteDefaults'    => $defaults,
		'siteSettings'    => $settings,
	);
}
