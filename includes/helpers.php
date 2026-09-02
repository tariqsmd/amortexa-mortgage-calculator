<?php
/**
 * Reusable helper functions for Mortgage Calculator Block.
 *
 * All mortgage math lives here as pure functions so it is unit-testable and
 * reusable by render.php, the REST endpoint, and any future shortcode.
 *
 * @package MortgageCalculatorBlock
 */

// Abort if this file is called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the list of valid skin slugs.
 *
 * Kept in one place so block.json, attribute sanitizing, and the settings
 * page can never drift apart.
 *
 * @return array<string> Skin slugs.
 */
function mcb_get_skin_slugs() {
	return array(
		'light',
		'dark',
		'ocean',
		'sunset',
		'forest',
		'midnight',
		'rose',
		'slate',
		'grape',
		'aqua',
		'mocha',
		'cyber',
	);
}

/**
 * Returns the CSS font-family stack for a font-family attribute key.
 *
 * Only system stacks are offered so no external font files are loaded —
 * keeps the plugin compliant with WP.org guidelines and fast by default.
 *
 * @param string $key Font family key: inherit|sans|serif|mono.
 * @return string CSS font-family value ('' means inherit from the theme).
 */
function mcb_get_font_stack( $key ) {
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
function mcb_get_default_settings() {
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

	// Guard against accidental recursion: mcb_sanitize_settings() (and thus
	// mcb_get_settings()/mcb_get_default_attributes()) calls this function, so a
	// filter on `mcb_default_settings` that calls back into any settings getter
	// would otherwise loop forever. Return the raw defaults in that case.
	global $mcb_resolving_defaults;

	if ( ! empty( $mcb_resolving_defaults ) ) {
		return $defaults;
	}

	$mcb_resolving_defaults = true;
	try {
		$defaults = apply_filters( 'mcb_default_settings', $defaults );
	} finally {
		$mcb_resolving_defaults = false;
	}

	return $defaults;
}

/**
 * Returns the saved settings merged over their defaults.
 *
 * @return array<string,mixed> Effective settings.
 */
function mcb_get_settings() {
	$saved    = get_option( 'mcb_settings', array() );
	$saved    = is_array( $saved ) ? $saved : array();
	$settings = wp_parse_args( $saved, mcb_get_default_settings() );

	return mcb_sanitize_settings( $settings );
}

/**
 * Sanitizes a settings array against the known whitelist.
 *
 * Used both by the Settings API sanitize callback and defensively in
 * mcb_get_settings(), so stored values can never be trusted blindly.
 *
 * @param mixed $settings Raw settings value.
 * @return array<string,mixed> Sanitized settings.
 */
function mcb_sanitize_settings( $settings ) {
	$defaults = mcb_get_default_settings();
	$raw      = is_array( $settings ) ? $settings : array();

	$symbol = isset( $raw['currency_symbol'] ) ? sanitize_text_field( (string) $raw['currency_symbol'] ) : '';
	if ( '' === $symbol ) {
		$symbol = $defaults['currency_symbol'];
	}

	$rate = isset( $raw['default_interest_rate'] ) ? (float) $raw['default_interest_rate'] : $defaults['default_interest_rate'];
	$rate = mcb_clamp_float( $rate, 0, 100 );

	$precision = isset( $raw['decimal_precision'] ) ? absint( $raw['decimal_precision'] ) : $defaults['decimal_precision'];
	$precision = min( max( $precision, 0 ), 4 );

	$loan_amount = isset( $raw['default_loan_amount'] )
		? mcb_clamp_float( $raw['default_loan_amount'], 0, 999999999999 )
		: (float) $defaults['default_loan_amount'];

	$down_payment = isset( $raw['default_down_payment'] )
		? mcb_clamp_float( $raw['default_down_payment'], 0, 999999999999 )
		: (float) $defaults['default_down_payment'];

	$loan_term = isset( $raw['default_loan_term'] ) ? absint( $raw['default_loan_term'] ) : (int) $defaults['default_loan_term'];
	$loan_term = min( max( $loan_term, 1 ), 60 );

	$skins       = mcb_get_skin_slugs();
	$theme       = isset( $raw['default_theme'] ) ? (string) $raw['default_theme'] : (string) $defaults['default_theme'];
	$theme_valid = in_array( $theme, $skins, true );
	if ( ! $theme_valid && ! in_array( $defaults['default_theme'], $skins, true ) ) {
		$theme = 'light';
	} elseif ( ! $theme_valid ) {
		$theme = (string) $defaults['default_theme'];
	}

	$chart_types = array( 'donut', 'line', 'both' );
	$chart_type  = isset( $raw['default_chart_type'] ) ? (string) $raw['default_chart_type'] : (string) $defaults['default_chart_type'];
	if ( ! in_array( $chart_type, $chart_types, true ) ) {
		$chart_type = in_array( $defaults['default_chart_type'], $chart_types, true )
			? (string) $defaults['default_chart_type']
			: 'both';
	}

	return array(
		'currency_symbol'       => mb_substr( $symbol, 0, 8 ),
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
 * Returns the default block attributes.
 *
 * Values fall back to the site-wide settings so admins can control what new
 * blocks start with. Third parties may override everything via the
 * `mcb_default_attributes` filter.
 *
 * @return array<string,mixed> Default block attributes.
 */
function mcb_get_default_attributes() {
	$settings = mcb_get_settings();

	$defaults = array(
		'loanAmount'       => (float) $settings['default_loan_amount'],
		'interestRate'     => (float) $settings['default_interest_rate'],
		'loanTerm'         => (int) $settings['default_loan_term'],
		'downPayment'      => (float) $settings['default_down_payment'],
		'currencySymbol'   => (string) $settings['currency_symbol'],
		'showAmortization' => (bool) $settings['enable_amortization'],
		'showCharts'       => true,
		'chartType'        => (string) $settings['default_chart_type'],
		'paymentFontSize'  => 0,
		'paymentFontWeight'=> '',
		'theme'            => (string) $settings['default_theme'],
		'showSliders'      => true,
		'showResults'      => true,
		'currencyPosition' => 'prefix',
		'accentColor'           => '',
		'accentAltColor'        => '',
		'labelColor'            => '',
		'fieldTextColor'        => '',
		'fieldBackgroundColor'  => '',
		'fieldBorderColor'      => '',
		'fontFamily'       => 'inherit',
	);

	/**
	 * Filters the default block attributes.
	 *
	 * @param array<string,mixed> $defaults Default attributes for the calculator block.
	 */
	return apply_filters( 'mcb_default_attributes', $defaults );
}

/**
 * Clamps a numeric value between a minimum and maximum bound.
 *
 * @param mixed $value   Value to clamp. Non-numeric input becomes 0.0.
 * @param float $minimum Lower bound.
 * @param float $maximum Upper bound.
 * @return float Clamped float value.
 */
function mcb_clamp_float( $value, $minimum, $maximum ) {
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
function mcb_sanitize_attributes( $attributes ) {
	$defaults = mcb_get_default_attributes();
	$raw      = is_array( $attributes ) ? $attributes : array();

	$loan_amount = isset( $raw['loanAmount'] ) ? mcb_clamp_float( $raw['loanAmount'], 0, 999999999999 ) : (float) $defaults['loanAmount'];

	$interest_rate = isset( $raw['interestRate'] ) ? mcb_clamp_float( $raw['interestRate'], 0, 100 ) : (float) $defaults['interestRate'];

	$loan_term = isset( $raw['loanTerm'] ) ? absint( $raw['loanTerm'] ) : (int) $defaults['loanTerm'];
	$loan_term = min( max( $loan_term, 1 ), 60 );

	$down_payment = isset( $raw['downPayment'] ) ? mcb_clamp_float( $raw['downPayment'], 0, 999999999999 ) : (float) $defaults['downPayment'];
	$down_payment = min( $down_payment, $loan_amount );

	$currency_symbol = isset( $raw['currencySymbol'] ) ? sanitize_text_field( (string) $raw['currencySymbol'] ) : '';
	if ( '' === $currency_symbol ) {
		$currency_symbol = (string) $defaults['currencySymbol'];
	}
	$currency_symbol = mb_substr( $currency_symbol, 0, 8 );

	$theme = 'light';
	$skins = mcb_get_skin_slugs();

	if ( isset( $raw['theme'] ) && in_array( $raw['theme'], $skins, true ) ) {
		$theme = (string) $raw['theme'];
	} elseif ( ! isset( $raw['theme'] ) && in_array( $defaults['theme'], $skins, true ) ) {
		$theme = (string) $defaults['theme'];
	}

	$show_amortization = array_key_exists( 'showAmortization', $raw )
		? (bool) $raw['showAmortization']
		: (bool) $defaults['showAmortization'];

	$chart_types = array( 'donut', 'line', 'both' );

	if ( isset( $raw['chartType'] ) && in_array( $raw['chartType'], $chart_types, true ) ) {
		$chart_type = (string) $raw['chartType'];
	} else {
		$chart_type = (string) $defaults['chartType'];
		if ( ! in_array( $chart_type, $chart_types, true ) ) {
			$chart_type = 'both';
		}
	}

	$payment_font_size = array_key_exists( 'paymentFontSize', $raw )
		? mcb_clamp_float( $raw['paymentFontSize'], 0, 120 )
		: (float) $defaults['paymentFontSize'];

	$font_weights  = array( '300', '400', '500', '600', '700', '800' );
	$requested_weight = isset( $raw['paymentFontWeight'] ) ? (string) $raw['paymentFontWeight'] : (string) $defaults['paymentFontWeight'];
	$payment_font_weight = in_array( $requested_weight, $font_weights, true ) ? $requested_weight : '';

	$positions = array( 'prefix', 'suffix' );

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
	 * ColorPalette; empty string means "use the active skin's value".
	 */
	$color_keys = array(
		'accentColor',
		'accentAltColor',
		'labelColor',
		'fieldTextColor',
		'fieldBackgroundColor',
		'fieldBorderColor',
	);

	$colors = array();
	foreach ( $color_keys as $key ) {
		$value = isset( $raw[ $key ] ) ? sanitize_hex_color( (string) $raw[ $key ] ) : null;
		$colors[ $key ] = $value ? $value : '';
	}

	$font_families = array( 'inherit', 'sans', 'serif', 'mono' );
	$requested_font = isset( $raw['fontFamily'] ) ? (string) $raw['fontFamily'] : (string) $defaults['fontFamily'];
	$font_family    = in_array( $requested_font, $font_families, true ) ? $requested_font : 'inherit';

	return array(
		'loanAmount'       => $loan_amount,
		'interestRate'     => $interest_rate,
		'loanTerm'         => $loan_term,
		'downPayment'      => $down_payment,
		'currencySymbol'   => $currency_symbol,
		'showAmortization' => $show_amortization,
		'showCharts'       => array_key_exists( 'showCharts', $raw )
			? (bool) $raw['showCharts']
			: (bool) $defaults['showCharts'],
		'chartType'        => $chart_type,
		'paymentFontSize'  => $payment_font_size,
		'paymentFontWeight'=> $payment_font_weight,
		'theme'            => $theme,
		'showSliders'      => array_key_exists( 'showSliders', $raw )
			? (bool) $raw['showSliders']
			: (bool) $defaults['showSliders'],
		'showResults'      => array_key_exists( 'showResults', $raw )
			? (bool) $raw['showResults']
			: (bool) $defaults['showResults'],
		'currencyPosition' => $currency_position,
		'accentColor'          => $colors['accentColor'],
		'accentAltColor'       => $colors['accentAltColor'],
		'labelColor'           => $colors['labelColor'],
		'fieldTextColor'       => $colors['fieldTextColor'],
		'fieldBackgroundColor' => $colors['fieldBackgroundColor'],
		'fieldBorderColor'     => $colors['fieldBorderColor'],
		'fontFamily'           => $font_family,
	);
}

/**
 * Calculates the monthly payment for an amortizing loan.
 *
 * Uses the standard annuity formula M = P * r / (1 - (1 + r)^-n). A zero
 * interest rate degrades gracefully to straight-line division.
 *
 * @param float $principal    Financed principal (loan amount minus down payment).
 * @param float $annual_rate  Annual interest rate as a percentage (e.g., 6.5).
 * @param int   $term_years   Loan term in whole years.
 * @return float Monthly payment amount, rounded to 2 decimals.
 */
function mcb_calculate_monthly_payment( $principal, $annual_rate, $term_years ) {
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
 * Internally iterates month by month so the math matches real statements,
 * then aggregates the rows per year to keep rendered tables lightweight.
 *
 * @param float $principal   Financed principal.
 * @param float $annual_rate Annual interest rate as a percentage.
 * @param int   $term_years  Loan term in whole years.
 * @return array<int,array<string,float|int>> Yearly rows with principal, interest, and balance.
 */
function mcb_calculate_amortization_schedule( $principal, $annual_rate, $term_years ) {
	$principal    = (float) $principal;
	$monthly_rate = (float) $annual_rate / 100 / 12;
	$months       = absint( $term_years ) * 12;

	if ( $principal <= 0 || $months < 1 ) {
		return array();
	}

	$payment = mcb_calculate_monthly_payment( $principal, $annual_rate, $term_years );
	$balance = $principal;

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
 * The result is passed through the `mcb_calculation_result` filter, which is
 * the sanctioned extension point for currency conversion plugins and similar.
 *
 * @param mixed $attributes Raw or partial block attributes.
 * @return array<string,mixed> Calculation result including schedule when enabled.
 */
function mcb_calculate( $attributes ) {
	$attrs   = mcb_sanitize_attributes( $attributes );
	$months  = $attrs['loanTerm'] * 12;
	$result  = array(
		'principal'       => round( $attrs['loanAmount'] - $attrs['downPayment'], 2 ),
		'monthly_payment' => 0.0,
		'total_paid'      => 0.0,
		'total_interest'  => 0.0,
		'months'          => $months,
		'schedule'        => array(),
	);

	$result['principal']       = max( $result['principal'], 0 );
	$result['monthly_payment'] = mcb_calculate_monthly_payment( $result['principal'], $attrs['interestRate'], $attrs['loanTerm'] );
	$result['total_paid']      = round( $result['monthly_payment'] * $months, 2 );
	$result['total_interest']  = round( max( $result['total_paid'] - $result['principal'], 0 ), 2 );

	if ( ! empty( $attrs['showAmortization'] ) ) {
		$result['schedule'] = mcb_calculate_amortization_schedule( $result['principal'], $attrs['interestRate'], $attrs['loanTerm'] );
	}

	/**
	 * Filters the calculated mortgage result before output.
	 *
	 * @param array<string,mixed> $result     Computed payment data and schedule.
	 * @param array<string,mixed> $attributes Sanitized block attributes used for the calculation.
	 */
	return apply_filters( 'mcb_calculation_result', $result, $attrs );
}

/**
 * Resolves the currency symbol for a block instance.
 *
 * Falls back to the site-wide setting when the attribute is empty, then
 * defers to the `mcb_currency_symbol` filter for locale/currency plugins.
 *
 * @param mixed $attributes Block attributes (sanitized or raw).
 * @return string Currency symbol.
 */
function mcb_resolve_currency_symbol( $attributes ) {
	$attributes = is_array( $attributes ) ? $attributes : array();
	$settings   = mcb_get_settings();

	$symbol = '';
	if ( ! empty( $attributes['currencySymbol'] ) ) {
		$symbol = sanitize_text_field( (string) $attributes['currencySymbol'] );
	}

	if ( '' === $symbol ) {
		$symbol = (string) $settings['currency_symbol'];
	}

	/**
	 * Filters the currency symbol displayed by the calculator.
	 *
	 * @param string              $symbol     Resolved currency symbol.
	 * @param array<string,mixed> $attributes Block attributes for context.
	 */
	return apply_filters( 'mcb_currency_symbol', mb_substr( $symbol, 0, 8 ), $attributes );
}

/**
 * Formats an amount as a localized currency string.
 *
 * Callers remain responsible for escaping the returned value on output.
 *
 * @param float  $amount    Amount to format.
 * @param string $symbol    Currency symbol.
 * @param int    $decimals  Number of decimal digits (clamped to 0–4).
 * @param string $position  Symbol placement: 'prefix' (default) or 'suffix'.
 * @return string Formatted amount such as "$1,234.56" or "1.234,56 €".
 */
function mcb_format_amount( $amount, $symbol, $decimals = 2, $position = 'prefix' ) {
	$decimals  = min( max( absint( $decimals ), 0 ), 4 );
	$formatted = number_format_i18n( (float) $amount, $decimals );

	if ( 'suffix' === $position ) {
		return sprintf( '%s%s', $formatted, $symbol );
	}

	return sprintf( '%s%s', $symbol, $formatted );
}
