<?php
/**
 * The design token schema.
 *
 * This file is the single source of truth for every appearance override the
 * block offers. The inspector, the server-side sanitizer, the inline CSS that
 * render.php emits, and the chart metrics handed to view.js are all derived from
 * the list below, so a token cannot mean one thing in the editor and another on
 * the frontend.
 *
 * That duplication used to exist: the editor colour list and render.php each
 * carried their own attribute => CSS variable map, and they disagreed about two
 * of the names, so those overrides silently did nothing in the editor while
 * working on the frontend. One map removes the whole class of bug.
 *
 * Every token value is stored as a string. An empty string means "inherit from
 * the active skin", which is what keeps the 24 skins working and makes clearing
 * a control a reset rather than a deletion. The four value types are:
 *
 * - color   a hex colour, or the keyword transparent.
 * - length  a number, emitted in pixels. May be negative (letter spacing).
 * - spacing one to four numbers, emitted as a CSS margin/padding shorthand, so
 *           the uniform and per-side cases share a single token and a single
 *           custom property.
 * - select  one of the token's declared options, emitted verbatim.
 *
 * @package CalcForge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the shared option lists that tokens refer to by name.
 *
 * Font weights and families already exist as helpers for the legacy single-font
 * controls. Tokens name them instead of repeating the lists, so adding a weight
 * in one place updates every element that offers it.
 *
 * @return array<string,array<string,string>> Option list name => value => label.
 */
function calcforge_get_design_option_lists() {
	$transforms = array(
		''             => __( 'As typed', CALCFORGE_TEXT_DOMAIN ),
		'uppercase'    => __( 'UPPERCASE', CALCFORGE_TEXT_DOMAIN ),
		'lowercase'    => __( 'lowercase', CALCFORGE_TEXT_DOMAIN ),
		'capitalize'   => __( 'Capitalize Each Word', CALCFORGE_TEXT_DOMAIN ),
	);

	$weights = calcforge_get_font_weights();

	// A leading empty option already means "inherit", so keep it.
	$weight_options = array( '' => __( 'Default', CALCFORGE_TEXT_DOMAIN ) );

	foreach ( $weights as $weight => $label ) {
		if ( '' === $weight ) {
			continue;
		}

		$weight_options[ $weight ] = $label;
	}

	$family_options = array( '' => __( 'Default', CALCFORGE_TEXT_DOMAIN ) );

	foreach ( calcforge_get_font_families() as $family => $label ) {
		if ( 'inherit' === $family ) {
			continue;
		}

		$family_options[ $family ] = $label;
	}

	/*
	 * Letter spacing is offered in em rather than as a pixel length. A pixel
	 * control would fight the relative default in the stylesheet, and a user
	 * entering "4" for tracking would get four whole pixels of extra space
	 * between every letter rather than four hundredths of an em.
	 */
	$tracking_options = array(
		''        => __( 'Default', CALCFORGE_TEXT_DOMAIN ),
		'-0.02em' => __( 'Tighter', CALCFORGE_TEXT_DOMAIN ),
		'0'       => __( 'Normal', CALCFORGE_TEXT_DOMAIN ),
		'0.02em'  => __( 'Slightly wide', CALCFORGE_TEXT_DOMAIN ),
		'0.04em'  => __( 'Wide', CALCFORGE_TEXT_DOMAIN ),
		'0.08em'  => __( 'Very wide', CALCFORGE_TEXT_DOMAIN ),
	);

	return array(
		'weights'    => $weight_options,
		'families'   => $family_options,
		'transforms' => $transforms,
		'tracking'   => $tracking_options,
	);
}

/**
 * Builds a length token.
 *
 * @param string $key       Design object key.
 * @param string $var       CSS custom property.
 * @param string $label     Translated control label.
 * @param int    $max       Largest accepted value.
 * @param int    $min       Smallest accepted value.
 * @return array<string,mixed> Token definition.
 */
function calcforge_length_token( $key, $var, $label, $max = 64, $min = 0 ) {
	return array(
		'key'     => $key,
		'var'     => $var,
		'label'   => $label,
		'type'    => 'length',
		'min'     => $min,
		'max'     => $max,
		'step'    => 1,
	);
}

/**
 * Builds a spacing token, which accepts a uniform value or a per-side shorthand.
 *
 * @param string $key   Design object key.
 * @param string $var   CSS custom property.
 * @param string $label Translated control label.
 * @param int    $max   Largest accepted value on any side.
 * @return array<string,mixed> Token definition.
 */
function calcforge_spacing_token( $key, $var, $label, $max = 120 ) {
	return array(
		'key'   => $key,
		'var'   => $var,
		'label' => $label,
		'type'  => 'spacing',
		'min'   => 0,
		'max'   => $max,
		'step'  => 1,
		'stack' => true,
	);
}

/**
 * Builds a colour token.
 *
 * @param string $key    Design object key.
 * @param string $var    CSS custom property.
 * @param string $label  Translated control label.
 * @param string $legacy Optional pre-existing attribute to fall back to.
 * @return array<string,mixed> Token definition.
 */
function calcforge_color_token( $key, $var, $label, $legacy = '' ) {
	return array(
		'key'    => $key,
		'var'    => $var,
		'label'  => $label,
		'type'   => 'color',
		'legacy' => $legacy,
	);
}

/**
 * Builds a select token.
 *
 * @param string $key     Design object key.
 * @param string $var     CSS custom property.
 * @param string $label   Translated control label.
 * @param string $options Name of a shared option list.
 * @return array<string,mixed> Token definition.
 */
function calcforge_select_token( $key, $var, $label, $options ) {
	return array(
		'key'     => $key,
		'var'     => $var,
		'label'   => $label,
		'type'    => 'select',
		'options' => $options,
	);
}

/**
 * Returns every design token, in inspector order, tagged with its group.
 *
 * @return array<int,array<string,mixed>> Token definitions.
 */
function calcforge_get_design_tokens() {
	$tokens = array();

	// The calculator card itself.
	$tokens[] = calcforge_color_token( 'rootBg', '--calcforge-bg', __( 'Background', CALCFORGE_TEXT_DOMAIN ) );
	$tokens[] = calcforge_color_token( 'rootText', '--calcforge-text', __( 'Text', CALCFORGE_TEXT_DOMAIN ) );
	$tokens[] = calcforge_color_token( 'rootBorder', '--calcforge-border', __( 'Border', CALCFORGE_TEXT_DOMAIN ) );
	$tokens[] = calcforge_length_token( 'rootRadius', '--calcforge-radius', __( 'Corner radius', CALCFORGE_TEXT_DOMAIN ), 64 );
	$tokens[] = calcforge_spacing_token( 'rootPadding', '--calcforge-padding', __( 'Padding', CALCFORGE_TEXT_DOMAIN ), 120 );

	// The panel boxes: inputs, results, charts, schedule.
	$tokens[] = calcforge_color_token( 'panelBg', '--calcforge-panel-bg', __( 'Background', CALCFORGE_TEXT_DOMAIN ) );
	$tokens[] = calcforge_length_token( 'panelRadius', '--calcforge-panel-radius', __( 'Corner radius', CALCFORGE_TEXT_DOMAIN ), 64 );
	$tokens[] = calcforge_spacing_token( 'panelPadding', '--calcforge-panel-padding', __( 'Padding', CALCFORGE_TEXT_DOMAIN ), 120 );
	$tokens[] = calcforge_length_token( 'panelGap', '--calcforge-panel-gap', __( 'Gap between panels', CALCFORGE_TEXT_DOMAIN ), 96 );

	// Field labels.
	$tokens[] = calcforge_color_token( 'labelColor', '--calcforge-label-color', __( 'Colour', CALCFORGE_TEXT_DOMAIN ), 'labelColor' );
	$tokens[] = calcforge_select_token( 'labelFamily', '--calcforge-label-family', __( 'Font', CALCFORGE_TEXT_DOMAIN ), 'families' );
	$tokens[] = calcforge_length_token( 'labelSize', '--calcforge-label-size', __( 'Size', CALCFORGE_TEXT_DOMAIN ), 32, 8 );
	$tokens[] = calcforge_select_token( 'labelWeight', '--calcforge-label-weight', __( 'Weight', CALCFORGE_TEXT_DOMAIN ), 'weights' );
	$tokens[] = calcforge_select_token( 'labelTransform', '--calcforge-label-transform', __( 'Case', CALCFORGE_TEXT_DOMAIN ), 'transforms' );
	$tokens[] = calcforge_select_token( 'labelTracking', '--calcforge-label-tracking', __( 'Letter spacing', CALCFORGE_TEXT_DOMAIN ), 'tracking' );
	$tokens[] = calcforge_spacing_token( 'labelMargin', '--calcforge-label-margin', __( 'Margin', CALCFORGE_TEXT_DOMAIN ), 48 );

	// Text inputs and selects.
	$tokens[] = calcforge_color_token( 'fieldText', '--calcforge-field-text', __( 'Text', CALCFORGE_TEXT_DOMAIN ), 'fieldTextColor' );
	$tokens[] = calcforge_color_token( 'fieldBg', '--calcforge-field-bg', __( 'Background', CALCFORGE_TEXT_DOMAIN ), 'fieldBackgroundColor' );
	$tokens[] = calcforge_color_token( 'fieldBorder', '--calcforge-field-border', __( 'Border', CALCFORGE_TEXT_DOMAIN ), 'fieldBorderColor' );
	$tokens[] = calcforge_length_token( 'fieldBorderWidth', '--calcforge-field-border-width', __( 'Border width', CALCFORGE_TEXT_DOMAIN ), 12 );
	$tokens[] = calcforge_length_token( 'fieldRadius', '--calcforge-field-radius', __( 'Corner radius', CALCFORGE_TEXT_DOMAIN ), 64 );
	$tokens[] = calcforge_spacing_token( 'fieldPadding', '--calcforge-field-padding', __( 'Padding', CALCFORGE_TEXT_DOMAIN ), 64 );
	$tokens[] = calcforge_length_token( 'fieldHeight', '--calcforge-field-height', __( 'Field height', CALCFORGE_TEXT_DOMAIN ), 120, 24 );
	$tokens[] = calcforge_select_token( 'fieldFamily', '--calcforge-field-family', __( 'Font', CALCFORGE_TEXT_DOMAIN ), 'families' );
	$tokens[] = calcforge_length_token( 'fieldSize', '--calcforge-field-size', __( 'Font size', CALCFORGE_TEXT_DOMAIN ), 32, 8 );
	$tokens[] = calcforge_select_token( 'fieldWeight', '--calcforge-field-weight', __( 'Weight', CALCFORGE_TEXT_DOMAIN ), 'weights' );

	// Range sliders.
	$tokens[] = calcforge_color_token( 'sliderTrack', '--calcforge-slider-track', __( 'Track', CALCFORGE_TEXT_DOMAIN ) );
	$tokens[] = calcforge_color_token( 'sliderAccent', '--calcforge-slider-accent', __( 'Filled track', CALCFORGE_TEXT_DOMAIN ) );
	$tokens[] = calcforge_length_token( 'sliderThumbSize', '--calcforge-slider-thumb-size', __( 'Thumb size', CALCFORGE_TEXT_DOMAIN ), 64, 8 );

	// The segmented control that switches sliders on and off.
	$tokens[] = calcforge_color_token( 'toggleBg', '--calcforge-toggle-bg', __( 'Background', CALCFORGE_TEXT_DOMAIN ) );
	$tokens[] = calcforge_color_token( 'toggleText', '--calcforge-toggle-text', __( 'Text', CALCFORGE_TEXT_DOMAIN ) );
	$tokens[] = calcforge_color_token( 'toggleBorder', '--calcforge-toggle-border', __( 'Border', CALCFORGE_TEXT_DOMAIN ) );
	$tokens[] = calcforge_length_token( 'toggleRadius', '--calcforge-toggle-radius', __( 'Corner radius', CALCFORGE_TEXT_DOMAIN ), 64 );
	$tokens[] = calcforge_spacing_token( 'togglePadding', '--calcforge-toggle-padding', __( 'Padding', CALCFORGE_TEXT_DOMAIN ), 48 );
	$tokens[] = calcforge_select_token( 'toggleFamily', '--calcforge-toggle-family', __( 'Font', CALCFORGE_TEXT_DOMAIN ), 'families' );
	$tokens[] = calcforge_length_token( 'toggleSize', '--calcforge-toggle-size', __( 'Font size', CALCFORGE_TEXT_DOMAIN ), 32, 8 );
	$tokens[] = calcforge_select_token( 'toggleWeight', '--calcforge-toggle-weight', __( 'Weight', CALCFORGE_TEXT_DOMAIN ), 'weights' );

	// Result values, and the headline monthly payment.
	$tokens[] = calcforge_color_token( 'resultPrimaryColor', '--calcforge-result-primary-color', __( 'Headline colour', CALCFORGE_TEXT_DOMAIN ) );
	$tokens[] = calcforge_select_token( 'resultPrimaryFamily', '--calcforge-result-primary-family', __( 'Headline font', CALCFORGE_TEXT_DOMAIN ), 'families' );
	$tokens[] = calcforge_length_token( 'resultPrimarySize', '--calcforge-result-primary-size', __( 'Headline size', CALCFORGE_TEXT_DOMAIN ), 96, 16 );
	$tokens[] = calcforge_select_token( 'resultPrimaryWeight', '--calcforge-result-primary-weight', __( 'Headline weight', CALCFORGE_TEXT_DOMAIN ), 'weights' );
	$tokens[] = calcforge_color_token( 'resultLabelColor', '--calcforge-result-label-color', __( 'Label text', CALCFORGE_TEXT_DOMAIN ) );
	$tokens[] = calcforge_color_token( 'resultValueColor', '--calcforge-result-value-color', __( 'Value text', CALCFORGE_TEXT_DOMAIN ) );
	$tokens[] = calcforge_length_token( 'resultSize', '--calcforge-result-size', __( 'Row font size', CALCFORGE_TEXT_DOMAIN ), 32, 8 );
	$tokens[] = calcforge_color_token( 'resultRowBorder', '--calcforge-result-row-border', __( 'Row divider', CALCFORGE_TEXT_DOMAIN ) );
	$tokens[] = calcforge_spacing_token( 'resultRowPadding', '--calcforge-result-row-padding', __( 'Row padding', CALCFORGE_TEXT_DOMAIN ), 48 );
	$tokens[] = calcforge_spacing_token( 'resultMargin', '--calcforge-result-margin', __( 'Panel padding', CALCFORGE_TEXT_DOMAIN ), 120 );

	// Charts. Series colours are the same accents the block already exposes, so
	// they keep honouring the older per-block accent attributes.
	$tokens[] = calcforge_color_token( 'accent', '--calcforge-accent', __( 'Series 1', CALCFORGE_TEXT_DOMAIN ), 'accentColor' );
	$tokens[] = calcforge_color_token( 'accentAlt', '--calcforge-accent-2', __( 'Series 2', CALCFORGE_TEXT_DOMAIN ), 'accentAltColor' );
	$tokens[] = calcforge_length_token( 'chartHeight', '--calcforge-chart-height', __( 'Chart height', CALCFORGE_TEXT_DOMAIN ), 560, 120 );
	$tokens[] = calcforge_color_token( 'chartAxis', '--calcforge-chart-axis', __( 'Axis and labels', CALCFORGE_TEXT_DOMAIN ) );
	$tokens[] = calcforge_color_token( 'chartTitleColor', '--calcforge-chart-title-color', __( 'Title text', CALCFORGE_TEXT_DOMAIN ) );
	$tokens[] = calcforge_length_token( 'chartTitleSize', '--calcforge-chart-title-size', __( 'Title size', CALCFORGE_TEXT_DOMAIN ), 32, 8 );
	$tokens[] = calcforge_select_token( 'chartTitleWeight', '--calcforge-chart-title-weight', __( 'Title weight', CALCFORGE_TEXT_DOMAIN ), 'weights' );
	$tokens[] = calcforge_spacing_token( 'chartTitleMargin', '--calcforge-chart-title-margin', __( 'Title margin', CALCFORGE_TEXT_DOMAIN ), 48 );

	// Chart legends.
	$tokens[] = calcforge_color_token( 'legendText', '--calcforge-legend-text', __( 'Text', CALCFORGE_TEXT_DOMAIN ) );
	$tokens[] = calcforge_select_token( 'legendFamily', '--calcforge-legend-family', __( 'Font', CALCFORGE_TEXT_DOMAIN ), 'families' );
	$tokens[] = calcforge_length_token( 'legendSize', '--calcforge-legend-size', __( 'Font size', CALCFORGE_TEXT_DOMAIN ), 24, 8 );
	$tokens[] = calcforge_select_token( 'legendWeight', '--calcforge-legend-weight', __( 'Weight', CALCFORGE_TEXT_DOMAIN ), 'weights' );
	$tokens[] = calcforge_length_token( 'legendSwatchSize', '--calcforge-legend-swatch-size', __( 'Swatch size', CALCFORGE_TEXT_DOMAIN ), 40, 6 );
	$tokens[] = calcforge_length_token( 'legendGap', '--calcforge-legend-gap', __( 'Gap between items', CALCFORGE_TEXT_DOMAIN ), 48 );

	// The amortization table.
	$tokens[] = calcforge_color_token( 'tableHeadBg', '--calcforge-table-head-bg', __( 'Header background', CALCFORGE_TEXT_DOMAIN ) );
	$tokens[] = calcforge_color_token( 'tableHeadText', '--calcforge-table-head-text', __( 'Header text', CALCFORGE_TEXT_DOMAIN ) );
	$tokens[] = calcforge_color_token( 'tableRowBg', '--calcforge-table-row-bg', __( 'Row background', CALCFORGE_TEXT_DOMAIN ) );
	$tokens[] = calcforge_color_token( 'tableZebraBg', '--calcforge-table-zebra-bg', __( 'Alternating row', CALCFORGE_TEXT_DOMAIN ) );
	$tokens[] = calcforge_color_token( 'tableBorder', '--calcforge-table-border', __( 'Borders', CALCFORGE_TEXT_DOMAIN ) );
	$tokens[] = calcforge_length_token( 'tableBorderWidth', '--calcforge-table-border-width', __( 'Border width', CALCFORGE_TEXT_DOMAIN ), 12 );
	$tokens[] = calcforge_spacing_token( 'tableCellPadding', '--calcforge-table-cell-padding', __( 'Cell padding', CALCFORGE_TEXT_DOMAIN ), 48 );
	$tokens[] = calcforge_length_token( 'tableSize', '--calcforge-table-size', __( 'Font size', CALCFORGE_TEXT_DOMAIN ), 24, 8 );
	$tokens[] = calcforge_length_token( 'tableRadius', '--calcforge-table-radius', __( 'Corner radius', CALCFORGE_TEXT_DOMAIN ), 64 );

	return $tokens;
}

/**
 * Returns the design groups, in inspector order.
 *
 * @return array<int,array<string,mixed>> Group definitions with their tokens.
 */
function calcforge_get_design_groups() {
	$groups = array(
		'root'    => array(
			'label'  => __( 'Calculator', CALCFORGE_TEXT_DOMAIN ),
			'summary' => __( 'The card that wraps the whole block.', CALCFORGE_TEXT_DOMAIN ),
			'tokens' => array( 'rootBg', 'rootText', 'rootBorder', 'rootRadius', 'rootPadding' ),
		),
		'panel'   => array(
			'label'  => __( 'Panels', CALCFORGE_TEXT_DOMAIN ),
			'summary' => __( 'The boxes around inputs, results, charts and schedule.', CALCFORGE_TEXT_DOMAIN ),
			'tokens' => array( 'panelBg', 'panelRadius', 'panelPadding', 'panelGap' ),
		),
		'label'   => array(
			'label'  => __( 'Field Labels', CALCFORGE_TEXT_DOMAIN ),
			'summary' => __( 'The text above each input.', CALCFORGE_TEXT_DOMAIN ),
			'tokens' => array( 'labelColor', 'labelFamily', 'labelSize', 'labelWeight', 'labelTransform', 'labelTracking', 'labelMargin' ),
		),
		'field'   => array(
			'label'  => __( 'Inputs', CALCFORGE_TEXT_DOMAIN ),
			'summary' => __( 'Text inputs and dropdowns.', CALCFORGE_TEXT_DOMAIN ),
			'tokens' => array( 'fieldText', 'fieldBg', 'fieldBorder', 'fieldBorderWidth', 'fieldRadius', 'fieldPadding', 'fieldHeight', 'fieldFamily', 'fieldSize', 'fieldWeight' ),
		),
		'slider'  => array(
			'label'  => __( 'Sliders', CALCFORGE_TEXT_DOMAIN ),
			'summary' => __( 'The draggable range controls.', CALCFORGE_TEXT_DOMAIN ),
			'tokens' => array( 'sliderTrack', 'sliderAccent', 'sliderThumbSize' ),
		),
		'toggle'  => array(
			'label'  => __( 'Sliders Toggle', CALCFORGE_TEXT_DOMAIN ),
			'summary' => __( 'The button that shows and hides the sliders.', CALCFORGE_TEXT_DOMAIN ),
			'tokens' => array( 'toggleBg', 'toggleText', 'toggleBorder', 'toggleRadius', 'togglePadding', 'toggleFamily', 'toggleSize', 'toggleWeight' ),
		),
		'result'  => array(
			'label'  => __( 'Results', CALCFORGE_TEXT_DOMAIN ),
			'summary' => __( 'The monthly payment and the rows beneath it.', CALCFORGE_TEXT_DOMAIN ),
			'tokens' => array( 'resultPrimaryColor', 'resultPrimaryFamily', 'resultPrimarySize', 'resultPrimaryWeight', 'resultLabelColor', 'resultValueColor', 'resultSize', 'resultRowBorder', 'resultRowPadding', 'resultMargin' ),
		),
		'chart'   => array(
			'label'  => __( 'Charts', CALCFORGE_TEXT_DOMAIN ),
			'summary' => __( 'Series colours, size and titles.', CALCFORGE_TEXT_DOMAIN ),
			'tokens' => array( 'accent', 'accentAlt', 'chartHeight', 'chartAxis', 'chartTitleColor', 'chartTitleSize', 'chartTitleWeight', 'chartTitleMargin' ),
		),
		'legend'  => array(
			'label'  => __( 'Chart Legends', CALCFORGE_TEXT_DOMAIN ),
			'summary' => __( 'The colour key under each chart.', CALCFORGE_TEXT_DOMAIN ),
			'tokens' => array( 'legendText', 'legendFamily', 'legendSize', 'legendWeight', 'legendSwatchSize', 'legendGap' ),
		),
		'table'   => array(
			'label'  => __( 'Schedule Table', CALCFORGE_TEXT_DOMAIN ),
			'summary' => __( 'The amortization table header, rows and borders.', CALCFORGE_TEXT_DOMAIN ),
			'tokens' => array( 'tableHeadBg', 'tableHeadText', 'tableRowBg', 'tableZebraBg', 'tableBorder', 'tableBorderWidth', 'tableCellPadding', 'tableSize', 'tableRadius' ),
		),
	);

	$by_key = array();

	foreach ( calcforge_get_design_tokens() as $token ) {
		$by_key[ $token['key'] ] = $token;
	}

	$ordered = array();

	foreach ( $groups as $key => $group ) {
		$token_keys = $group['tokens'];
		$resolved    = array();

		foreach ( $token_keys as $token_key ) {
			if ( isset( $by_key[ $token_key ] ) ) {
				$resolved[] = $by_key[ $token_key ];
			} else {
				// A typo here would silently drop a control from the inspector.
				_doing_it_wrong(
					__FUNCTION__,
					sprintf(
						/* translators: 1: group key, 2: token key. */
						__( 'Design group "%1$s" lists unknown token "%2$s".', 'calcforge' ),
						$key,
						$token_key
					),
					'1.0.0'
				);
			}
		}

		if ( $resolved ) {
			$group['key']    = $key;
			$group['tokens'] = $resolved;
			$ordered[]       = $group;
		}
	}

	return $ordered;
}

/**
 * Flattens the groups back into a key => definition map.
 *
 * @return array<string,array<string,mixed>> Tokens keyed by design key.
 */
function calcforge_get_design_token_map() {
	$map = array();

	foreach ( calcforge_get_design_tokens() as $token ) {
		$map[ $token['key'] ] = $token;
	}

	return $map;
}

/**
 * Normalises one raw token value into the string that gets stored and emitted.
 *
 * @param array<string,mixed> $token Token definition.
 * @param mixed              $raw   Untrusted value.
 * @return string Sanitized value, or an empty string to inherit.
 */
function calcforge_sanitize_design_value( $token, $raw ) {
	if ( is_array( $raw ) || is_object( $raw ) || null === $raw ) {
		return '';
	}

	$raw = trim( (string) $raw );

	if ( '' === $raw ) {
		return '';
	}

	$min = isset( $token['min'] ) ? (int) $token['min'] : 0;
	$max = isset( $token['max'] ) ? (int) $token['max'] : 9999;

	switch ( $token['type'] ) {
		case 'color':
			if ( 'transparent' === strtolower( $raw ) ) {
				return 'transparent';
			}

			$hex = sanitize_hex_color( $raw );

			return $hex ? $hex : '';

		case 'length':
			if ( ! is_numeric( $raw ) ) {
				return '';
			}

			return (string) (int) calcforge_clamp_float( $raw, $min, $max );

		case 'spacing':
			$parts = preg_split( '/\s+/', $raw );
			$kept  = array();

			foreach ( (array) $parts as $part ) {
				if ( ! is_numeric( $part ) ) {
					continue;
				}

				$kept[] = (int) calcforge_clamp_float( $part, 0, $max );
			}

			// Zero parts means the value was junk, so inherit instead of clearing.
			if ( ! $kept ) {
				return '';
			}

			return implode( ' ', array_slice( $kept, 0, 4 ) );

		case 'select':
			$lists = calcforge_get_design_option_lists();
			$name  = isset( $token['options'] ) ? $token['options'] : '';

			if ( ! isset( $lists[ $name ] ) ) {
				return '';
			}

			return array_key_exists( $raw, $lists[ $name ] ) ? $raw : '';
	}

	return '';
}

/**
 * Sanitizes the whole design object.
 *
 * @param mixed $raw Untrusted design object.
 * @return array<string,string> Sanitized key => value, only for set tokens.
 */
function calcforge_sanitize_design( $raw ) {
	if ( ! is_array( $raw ) ) {
		return array();
	}

	$sanitized = array();

	foreach ( calcforge_get_design_token_map() as $key => $token ) {
		if ( ! array_key_exists( $key, $raw ) ) {
			continue;
		}

		$value = calcforge_sanitize_design_value( $token, $raw[ $key ] );

		if ( '' !== $value ) {
			$sanitized[ $key ] = $value;
		}
	}

	return $sanitized;
}

/**
 * Resolves the design object against the legacy per-block attributes.
 *
 * A token wins when it is set. Otherwise the older top-level attribute it
 * replaced is honoured, so blocks saved before the design tab existed keep their
 * colours instead of silently reverting to the skin.
 *
 * @param array<string,mixed> $attributes Sanitized block attributes.
 * @return array<string,string> Resolved key => stored value.
 */
function calcforge_get_design_values( $attributes ) {
	$attributes = is_array( $attributes ) ? $attributes : array();
	$design     = isset( $attributes['design'] ) && is_array( $attributes['design'] )
		? calcforge_sanitize_design( $attributes['design'] )
		: array();

	foreach ( calcforge_get_design_token_map() as $key => $token ) {
		if ( isset( $design[ $key ] ) ) {
			continue;
		}

		if ( empty( $token['legacy'] ) || empty( $attributes[ $token['legacy'] ] ) ) {
			continue;
		}

		$legacy = calcforge_sanitize_design_value( $token, $attributes[ $token['legacy'] ] );

		if ( '' !== $legacy ) {
			$design[ $key ] = $legacy;
		}
	}

	return $design;
}

/**
 * Converts a stored token value into the CSS declaration value.
 *
 * @param array<string,mixed> $token Token definition.
 * @param string              $value Stored value.
 * @return string CSS value with a unit.
 */
function calcforge_design_css_value( $token, $value ) {
	if ( 'length' === $token['type'] ) {
		return $value . 'px';
	}

	if ( 'spacing' === $token['type'] ) {
		$parts = array();

		foreach ( preg_split( '/\s+/', $value ) as $part ) {
			$parts[] = $part . 'px';
		}

		return implode( ' ', $parts );
	}

	return $value;
}

/**
 * Builds the custom property declarations for the design overrides.
 *
 * @param array<string,mixed> $attributes Sanitized block attributes.
 * @return array<int,string> CSS declarations.
 */
function calcforge_get_design_css( $attributes ) {
	$tokens = calcforge_get_design_token_map();
	$values = calcforge_get_design_values( $attributes );
	$css    = array();

	foreach ( $tokens as $key => $token ) {
		if ( ! isset( $values[ $key ] ) ) {
			continue;
		}

		$css[] = $token['var'] . ':' . calcforge_design_css_value( $token, $values[ $key ] );
	}

	return $css;
}

/**
 * Returns the chart metrics that view.js needs, which CSS cannot express.
 *
 * The charts are drawn as SVG in JavaScript, so a height token has to travel
 * through the config payload as a number rather than as a custom property.
 *
 * @param array<string,mixed> $attributes Sanitized block attributes.
 * @return array<string,mixed> Chart metrics.
 */
function calcforge_get_design_chart_metrics( $attributes ) {
	$values = calcforge_get_design_values( $attributes );
	$height = isset( $values['chartHeight'] ) ? (int) $values['chartHeight'] : 0;

	return array(
		'height' => $height > 0 ? $height : 0,
	);
}
