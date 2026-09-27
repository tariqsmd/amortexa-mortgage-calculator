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
		'default_interest_rate' => 6.5,
		'decimal_precision'     => 2,
		'enable_amortization'   => true,
		'default_loan_amount'   => 300000.0,
		'default_down_payment'  => 0.0,
		'default_loan_term'     => 30,
		'default_theme'         => 'light',
		'default_chart_type'    => 'both',
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

	$symbol = isset( $raw['currency_symbol'] ) ? sanitize_text_field( (string) $raw['currency_symbol'] ) : '';
	if ( '' === $symbol ) {
		$symbol = $defaults['currency_symbol'];
	}

	$rate = isset( $raw['default_interest_rate'] ) ? (float) $raw['default_interest_rate'] : $defaults['default_interest_rate'];
	$rate = calcforge_clamp_float( $rate, 0, 100 );

	$precision = isset( $raw['decimal_precision'] ) ? absint( $raw['decimal_precision'] ) : $defaults['decimal_precision'];
	$precision = min( max( $precision, 0 ), 4 );

	$loan_amount = isset( $raw['default_loan_amount'] )
		? calcforge_clamp_float( $raw['default_loan_amount'], 0, 999999999999 )
		: (float) $defaults['default_loan_amount'];

	$down_payment = isset( $raw['default_down_payment'] )
		? calcforge_clamp_float( $raw['default_down_payment'], 0, 999999999999 )
		: (float) $defaults['default_down_payment'];

	$loan_term = isset( $raw['default_loan_term'] ) ? absint( $raw['default_loan_term'] ) : (int) $defaults['default_loan_term'];
	$loan_term = min( max( $loan_term, 1 ), 60 );

	$theme = isset( $raw['default_theme'] ) ? (string) $raw['default_theme'] : (string) $defaults['default_theme'];
	if ( ! array_key_exists( $theme, calcforge_get_skins() ) ) {
		$theme = array_key_exists( (string) $defaults['default_theme'], calcforge_get_skins() )
			? (string) $defaults['default_theme']
			: 'light';
	}

	$chart_type = isset( $raw['default_chart_type'] ) ? (string) $raw['default_chart_type'] : (string) $defaults['default_chart_type'];
	if ( ! array_key_exists( $chart_type, calcforge_get_chart_types() ) ) {
		$chart_type = array_key_exists( (string) $defaults['default_chart_type'], calcforge_get_chart_types() )
			? (string) $defaults['default_chart_type']
			: 'both';
	}

	return array(
		'currency_symbol'       => wp_html_excerpt( $symbol, 8, '' ),
		'default_interest_rate' => $rate,
		'decimal_precision'     => $precision,
		'enable_amortization'   => ! empty( $raw['enable_amortization'] ),
		'default_loan_amount'   => $loan_amount,
		'default_down_payment'  => $down_payment,
		'default_loan_term'     => $loan_term,
		'default_theme'         => $theme,
		'default_chart_type'    => $chart_type,
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
		'enable_amortization'   => 'showAmortization',
		'default_theme'         => 'theme',
		'default_chart_type'    => 'chartType',
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
		'currencyPosition'     => 'prefix',
		'showAmortization'     => (bool) $settings['enable_amortization'],
		'showCharts'           => true,
		'chartType'            => (string) $settings['default_chart_type'],
		'theme'                => (string) $settings['default_theme'],
		'showSliders'          => true,
		'showResults'          => true,
		'paymentFontSize'      => 0,
		'paymentFontWeight'    => '',
		'fontFamily'           => 'inherit',
		'accentColor'          => '',
		'accentAltColor'       => '',
		'labelColor'           => '',
		'fieldTextColor'       => '',
		'fieldBackgroundColor' => '',
		'fieldBorderColor'     => '',
	);

	/**
	 * Filters the default block attributes.
	 *
	 * @param array<string,mixed> $defaults Default attributes for the calculator block.
	 */
	return apply_filters( 'calcforge_default_attributes', $defaults );
}

/**
 * Returns the per-block color attribute keys and the CSS variable each feeds.
 *
 * The editor inspector builds its color pickers from this so the attribute
 * names, the labels, and the inline custom properties cannot drift apart.
 *
 * @return array<int,array<string,string>> Color control descriptors.
 */
function calcforge_get_color_attributes() {
	return array(
		array(
			'key'     => 'accentColor',
			'label'   => __( 'Accent', CALCFORGE_TEXT_DOMAIN ),
			'cssVar'  => '--calcforge-accent',
		),
		array(
			'key'     => 'accentAltColor',
			'label'   => __( 'Secondary accent (charts)', CALCFORGE_TEXT_DOMAIN ),
			'cssVar'  => '--calcforge-accent-alt',
		),
		array(
			'key'     => 'labelColor',
			'label'   => __( 'Label text', CALCFORGE_TEXT_DOMAIN ),
			'cssVar'  => '--calcforge-label',
		),
		array(
			'key'     => 'fieldTextColor',
			'label'   => __( 'Field text', CALCFORGE_TEXT_DOMAIN ),
			'cssVar'  => '--calcforge-field-text',
		),
		array(
			'key'     => 'fieldBackgroundColor',
			'label'   => __( 'Field background', CALCFORGE_TEXT_DOMAIN ),
			'cssVar'  => '--calcforge-field-bg',
		),
		array(
			'key'     => 'fieldBorderColor',
			'label'   => __( 'Field border', CALCFORGE_TEXT_DOMAIN ),
			'cssVar'  => '--calcforge-field-border',
		),
	);
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

	$loan_amount = isset( $raw['loanAmount'] ) ? calcforge_clamp_float( $raw['loanAmount'], 0, 999999999999 ) : (float) $defaults['loanAmount'];

	$interest_rate = isset( $raw['interestRate'] ) ? calcforge_clamp_float( $raw['interestRate'], 0, 100 ) : (float) $defaults['interestRate'];

	$loan_term = isset( $raw['loanTerm'] ) ? absint( $raw['loanTerm'] ) : (int) $defaults['loanTerm'];
	$loan_term = min( max( $loan_term, 1 ), 60 );

	$down_payment = isset( $raw['downPayment'] ) ? calcforge_clamp_float( $raw['downPayment'], 0, 999999999999 ) : (float) $defaults['downPayment'];
	$down_payment = min( $down_payment, $loan_amount );

	$currency_symbol = isset( $raw['currencySymbol'] ) ? sanitize_text_field( (string) $raw['currencySymbol'] ) : '';
	if ( '' === $currency_symbol ) {
		$currency_symbol = (string) $defaults['currencySymbol'];
	}
	$currency_symbol = wp_html_excerpt( $currency_symbol, 8, '' );

	$skins = calcforge_get_skin_slugs();
	$theme = 'light';

	if ( isset( $raw['theme'] ) && in_array( $raw['theme'], $skins, true ) ) {
		$theme = (string) $raw['theme'];
	} elseif ( ! isset( $raw['theme'] ) && in_array( $defaults['theme'], $skins, true ) ) {
		$theme = (string) $defaults['theme'];
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

	$payment_font_size = array_key_exists( 'paymentFontSize', $raw )
		? calcforge_clamp_float( $raw['paymentFontSize'], 0, 120 )
		: (float) $defaults['paymentFontSize'];

	$requested_weight  = isset( $raw['paymentFontWeight'] ) ? (string) $raw['paymentFontWeight'] : (string) $defaults['paymentFontWeight'];
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
		$value              = isset( $raw[ $control['key'] ] ) ? sanitize_hex_color( (string) $raw[ $control['key'] ] ) : null;
		$colors[ $control['key'] ] = $value ? $value : '';
	}

	$font_families = array_keys( calcforge_get_font_families() );
	$requested_font = isset( $raw['fontFamily'] ) ? (string) $raw['fontFamily'] : (string) $defaults['fontFamily'];
	$font_family    = in_array( $requested_font, $font_families, true ) ? $requested_font : 'inherit';

	$sanitized = array(
		'loanAmount'       => $loan_amount,
		'interestRate'     => $interest_rate,
		'loanTerm'         => $loan_term,
		'downPayment'      => $down_payment,
		'currencySymbol'   => $currency_symbol,
		'currencyPosition' => $currency_position,
		'showAmortization' => array_key_exists( 'showAmortization', $raw )
			? (bool) $raw['showAmortization']
			: (bool) $defaults['showAmortization'],
		'showCharts'       => array_key_exists( 'showCharts', $raw )
			? (bool) $raw['showCharts']
			: (bool) $defaults['showCharts'],
		'chartType'        => $chart_type,
		'paymentFontSize'  => $payment_font_size,
		'paymentFontWeight' => $payment_font_weight,
		'theme'            => $theme,
		'showSliders'      => array_key_exists( 'showSliders', $raw )
			? (bool) $raw['showSliders']
			: (bool) $defaults['showSliders'],
		'showResults'      => array_key_exists( 'showResults', $raw )
			? (bool) $raw['showResults']
			: (bool) $defaults['showResults'],
		'fontFamily'       => $font_family,
	);

	foreach ( $colors as $key => $value ) {
		$sanitized[ $key ] = $value;
	}

	return $sanitized;
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

	return array(
		'skins'           => $skins,
		'chartTypes'      => calcforge_get_chart_types(),
		'fontFamilies'    => calcforge_get_font_families(),
		'fontWeights'     => calcforge_get_font_weights(),
		'currencyPosition' => calcforge_get_currency_positions(),
		'colors'          => $colors,
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
