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
 * @package Amortexa
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
function amortexa_get_design_option_lists() {
	$transforms = array(
		''           => __( 'As typed', 'amortexa-mortgage-calculator' ),
		'uppercase'  => __( 'UPPERCASE', 'amortexa-mortgage-calculator' ),
		'lowercase'  => __( 'lowercase', 'amortexa-mortgage-calculator' ),
		'capitalize' => __( 'Capitalize Each Word', 'amortexa-mortgage-calculator' ),
	);

	$weights = amortexa_get_font_weights();

	// A leading empty option already means "inherit", so keep it.
	$weight_options = array( '' => __( 'Default', 'amortexa-mortgage-calculator' ) );

	foreach ( $weights as $weight => $label ) {
		if ( '' === $weight ) {
			continue;
		}

		$weight_options[ $weight ] = $label;
	}

	$family_options = array( '' => __( 'Default', 'amortexa-mortgage-calculator' ) );

	foreach ( amortexa_get_font_families() as $family => $label ) {
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
		''        => __( 'Default', 'amortexa-mortgage-calculator' ),
		'-0.02em' => __( 'Tighter', 'amortexa-mortgage-calculator' ),
		'0'       => __( 'Normal', 'amortexa-mortgage-calculator' ),
		'0.02em'  => __( 'Slightly wide', 'amortexa-mortgage-calculator' ),
		'0.04em'  => __( 'Wide', 'amortexa-mortgage-calculator' ),
		'0.08em'  => __( 'Very wide', 'amortexa-mortgage-calculator' ),
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
 * @param string $css_var   CSS custom property.
 * @param string $label     Translated control label.
 * @param int    $max       Largest accepted value.
 * @param int    $min       Smallest accepted value.
 * @return array<string,mixed> Token definition.
 */
function amortexa_length_token( $key, $css_var, $label, $max = 64, $min = 0 ) {
	return array(
		'key'   => $key,
		'var'   => $css_var,
		'label' => $label,
		'type'  => 'length',
		'min'   => $min,
		'max'   => $max,
		'step'  => 1,
	);
}

/**
 * Builds a spacing token, which accepts a uniform value or a per-side shorthand.
 *
 * @param string $key   Design object key.
 * @param string $css_var CSS custom property.
 * @param string $label Translated control label.
 * @param int    $max   Largest accepted value on any side.
 * @return array<string,mixed> Token definition.
 */
function amortexa_spacing_token( $key, $css_var, $label, $max = 120 ) {
	return array(
		'key'   => $key,
		'var'   => $css_var,
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
 * @param string $css_var CSS custom property.
 * @param string $label  Translated control label.
 * @param string $legacy Optional pre-existing attribute to fall back to.
 * @return array<string,mixed> Token definition.
 */
function amortexa_color_token( $key, $css_var, $label, $legacy = '' ) {
	return array(
		'key'    => $key,
		'var'    => $css_var,
		'label'  => $label,
		'type'   => 'color',
		'legacy' => $legacy,
	);
}

/**
 * Builds a select token.
 *
 * @param string $key     Design object key.
 * @param string $css_var CSS custom property.
 * @param string $label   Translated control label.
 * @param string $options Name of a shared option list.
 * @return array<string,mixed> Token definition.
 */
function amortexa_select_token( $key, $css_var, $label, $options ) {
	return array(
		'key'     => $key,
		'var'     => $css_var,
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
function amortexa_get_design_tokens() {
	$tokens = array();

	// The calculator card itself.
	$tokens[] = amortexa_color_token( 'rootBg', '--amortexa-bg', __( 'Background', 'amortexa-mortgage-calculator' ) );
	$tokens[] = amortexa_color_token( 'rootText', '--amortexa-text', __( 'Text', 'amortexa-mortgage-calculator' ) );
	$tokens[] = amortexa_color_token( 'rootBorder', '--amortexa-border', __( 'Border', 'amortexa-mortgage-calculator' ) );
	$tokens[] = amortexa_select_token( 'rootFamily', '--amortexa-font-family', __( 'Font family', 'amortexa-mortgage-calculator' ), 'families' );
	$tokens[] = amortexa_length_token( 'rootRadius', '--amortexa-radius', __( 'Corner radius', 'amortexa-mortgage-calculator' ), 64 );
	$tokens[] = amortexa_spacing_token( 'rootPadding', '--amortexa-padding', __( 'Padding', 'amortexa-mortgage-calculator' ), 120 );

	// The panel boxes: inputs, results, charts, schedule.
	$tokens[] = amortexa_color_token( 'panelBg', '--amortexa-panel-bg', __( 'Background', 'amortexa-mortgage-calculator' ) );
	$tokens[] = amortexa_length_token( 'panelRadius', '--amortexa-panel-radius', __( 'Corner radius', 'amortexa-mortgage-calculator' ), 64 );
	$tokens[] = amortexa_spacing_token( 'panelPadding', '--amortexa-panel-padding', __( 'Padding', 'amortexa-mortgage-calculator' ), 120 );
	$tokens[] = amortexa_length_token( 'panelGap', '--amortexa-panel-gap', __( 'Gap between panels', 'amortexa-mortgage-calculator' ), 96 );

	// Field labels.
	$tokens[] = amortexa_color_token( 'labelColor', '--amortexa-label-color', __( 'Colour', 'amortexa-mortgage-calculator' ), 'labelColor' );
	$tokens[] = amortexa_select_token( 'labelFamily', '--amortexa-label-family', __( 'Font', 'amortexa-mortgage-calculator' ), 'families' );
	$tokens[] = amortexa_length_token( 'labelSize', '--amortexa-label-size', __( 'Size', 'amortexa-mortgage-calculator' ), 32, 8 );
	$tokens[] = amortexa_select_token( 'labelWeight', '--amortexa-label-weight', __( 'Weight', 'amortexa-mortgage-calculator' ), 'weights' );
	$tokens[] = amortexa_select_token( 'labelTransform', '--amortexa-label-transform', __( 'Case', 'amortexa-mortgage-calculator' ), 'transforms' );
	$tokens[] = amortexa_select_token( 'labelTracking', '--amortexa-label-tracking', __( 'Letter spacing', 'amortexa-mortgage-calculator' ), 'tracking' );
	$tokens[] = amortexa_spacing_token( 'labelMargin', '--amortexa-label-margin', __( 'Margin', 'amortexa-mortgage-calculator' ), 48 );

	// Text inputs and selects.
	$tokens[] = amortexa_color_token( 'fieldText', '--amortexa-field-text', __( 'Text', 'amortexa-mortgage-calculator' ), 'fieldTextColor' );
	$tokens[] = amortexa_color_token( 'fieldBg', '--amortexa-field-bg', __( 'Background', 'amortexa-mortgage-calculator' ), 'fieldBackgroundColor' );
	$tokens[] = amortexa_color_token( 'fieldBorder', '--amortexa-field-border', __( 'Border', 'amortexa-mortgage-calculator' ), 'fieldBorderColor' );
	$tokens[] = amortexa_length_token( 'fieldBorderWidth', '--amortexa-field-border-width', __( 'Border width', 'amortexa-mortgage-calculator' ), 12 );
	$tokens[] = amortexa_length_token( 'fieldRadius', '--amortexa-field-radius', __( 'Corner radius', 'amortexa-mortgage-calculator' ), 64 );
	$tokens[] = amortexa_spacing_token( 'fieldPadding', '--amortexa-field-padding', __( 'Padding', 'amortexa-mortgage-calculator' ), 64 );
	$tokens[] = amortexa_length_token( 'fieldHeight', '--amortexa-field-height', __( 'Field height', 'amortexa-mortgage-calculator' ), 120, 24 );
	$tokens[] = amortexa_select_token( 'fieldFamily', '--amortexa-field-family', __( 'Font', 'amortexa-mortgage-calculator' ), 'families' );
	$tokens[] = amortexa_length_token( 'fieldSize', '--amortexa-field-size', __( 'Font size', 'amortexa-mortgage-calculator' ), 32, 8 );
	$tokens[] = amortexa_select_token( 'fieldWeight', '--amortexa-field-weight', __( 'Weight', 'amortexa-mortgage-calculator' ), 'weights' );

	// Range sliders.
	$tokens[] = amortexa_color_token( 'sliderTrack', '--amortexa-slider-track', __( 'Track', 'amortexa-mortgage-calculator' ) );
	$tokens[] = amortexa_color_token( 'sliderAccent', '--amortexa-slider-accent', __( 'Filled track', 'amortexa-mortgage-calculator' ) );
	$tokens[] = amortexa_length_token( 'sliderThumbSize', '--amortexa-slider-thumb-size', __( 'Thumb size', 'amortexa-mortgage-calculator' ), 64, 8 );

	// The segmented control that switches sliders on and off.
	$tokens[] = amortexa_color_token( 'toggleBg', '--amortexa-toggle-bg', __( 'Background', 'amortexa-mortgage-calculator' ) );
	$tokens[] = amortexa_color_token( 'toggleText', '--amortexa-toggle-text', __( 'Text', 'amortexa-mortgage-calculator' ) );
	$tokens[] = amortexa_color_token( 'toggleBorder', '--amortexa-toggle-border', __( 'Border', 'amortexa-mortgage-calculator' ) );
	$tokens[] = amortexa_length_token( 'toggleRadius', '--amortexa-toggle-radius', __( 'Corner radius', 'amortexa-mortgage-calculator' ), 64 );
	$tokens[] = amortexa_spacing_token( 'togglePadding', '--amortexa-toggle-padding', __( 'Padding', 'amortexa-mortgage-calculator' ), 48 );
	$tokens[] = amortexa_select_token( 'toggleFamily', '--amortexa-toggle-family', __( 'Font', 'amortexa-mortgage-calculator' ), 'families' );
	$tokens[] = amortexa_length_token( 'toggleSize', '--amortexa-toggle-size', __( 'Font size', 'amortexa-mortgage-calculator' ), 32, 8 );
	$tokens[] = amortexa_select_token( 'toggleWeight', '--amortexa-toggle-weight', __( 'Weight', 'amortexa-mortgage-calculator' ), 'weights' );

	// Result values, and the headline monthly payment.
	$tokens[] = amortexa_color_token( 'resultPrimaryColor', '--amortexa-result-primary-color', __( 'Headline colour', 'amortexa-mortgage-calculator' ) );
	$tokens[] = amortexa_select_token( 'resultPrimaryFamily', '--amortexa-result-primary-family', __( 'Headline font', 'amortexa-mortgage-calculator' ), 'families' );
	$tokens[] = amortexa_length_token( 'resultPrimarySize', '--amortexa-result-primary-size', __( 'Headline size', 'amortexa-mortgage-calculator' ), 96, 16 );
	$tokens[] = amortexa_select_token( 'resultPrimaryWeight', '--amortexa-result-primary-weight', __( 'Headline weight', 'amortexa-mortgage-calculator' ), 'weights' );
	$tokens[] = amortexa_color_token( 'resultLabelColor', '--amortexa-result-label-color', __( 'Label text', 'amortexa-mortgage-calculator' ) );
	$tokens[] = amortexa_color_token( 'resultValueColor', '--amortexa-result-value-color', __( 'Value text', 'amortexa-mortgage-calculator' ) );
	$tokens[] = amortexa_length_token( 'resultSize', '--amortexa-result-size', __( 'Row font size', 'amortexa-mortgage-calculator' ), 32, 8 );
	$tokens[] = amortexa_color_token( 'resultRowBorder', '--amortexa-result-row-border', __( 'Row divider', 'amortexa-mortgage-calculator' ) );
	$tokens[] = amortexa_spacing_token( 'resultRowPadding', '--amortexa-result-row-padding', __( 'Row padding', 'amortexa-mortgage-calculator' ), 48 );
	$tokens[] = amortexa_spacing_token( 'resultMargin', '--amortexa-result-margin', __( 'Panel padding', 'amortexa-mortgage-calculator' ), 120 );

	// Charts. Series colours are the same accents the block already exposes, so
	// they keep honouring the older per-block accent attributes.
	$tokens[] = amortexa_color_token( 'accent', '--amortexa-accent', __( 'Series 1', 'amortexa-mortgage-calculator' ), 'accentColor' );
	$tokens[] = amortexa_color_token( 'accentAlt', '--amortexa-accent-2', __( 'Series 2', 'amortexa-mortgage-calculator' ), 'accentAltColor' );
	$tokens[] = amortexa_length_token( 'chartHeight', '--amortexa-chart-height', __( 'Chart height', 'amortexa-mortgage-calculator' ), 560, 120 );
	$tokens[] = amortexa_color_token( 'chartAxis', '--amortexa-chart-axis', __( 'Axis and labels', 'amortexa-mortgage-calculator' ) );
	$tokens[] = amortexa_color_token( 'chartTitleColor', '--amortexa-chart-title-color', __( 'Title text', 'amortexa-mortgage-calculator' ) );
	$tokens[] = amortexa_length_token( 'chartTitleSize', '--amortexa-chart-title-size', __( 'Title size', 'amortexa-mortgage-calculator' ), 32, 8 );
	$tokens[] = amortexa_select_token( 'chartTitleWeight', '--amortexa-chart-title-weight', __( 'Title weight', 'amortexa-mortgage-calculator' ), 'weights' );
	$tokens[] = amortexa_spacing_token( 'chartTitleMargin', '--amortexa-chart-title-margin', __( 'Title margin', 'amortexa-mortgage-calculator' ), 48 );

	/*
	 * One colour per payment component, so the donut, the legend and the results
	 * breakdown all read the same hue for the same component. A component that
	 * means "tax" should look like tax on every calculator, which is why the
	 * defaults are fixed, but like every other token each one is still overridable
	 * per block in the inspector.
	 *
	 * The shipped defaults clear 4.5:1 against white so a component can be used as
	 * text as well as a fill, and are far enough apart to be told apart side by
	 * side. The test suite re-checks both rather than trusting this comment.
	 */
	$tokens[] = amortexa_color_token( 'costPi', '--amortexa-cost-pi', __( 'Principal & Interest', 'amortexa-mortgage-calculator' ) );
	$tokens[] = amortexa_color_token( 'costTax', '--amortexa-cost-tax', __( 'Property Tax', 'amortexa-mortgage-calculator' ) );
	$tokens[] = amortexa_color_token( 'costInsurance', '--amortexa-cost-insurance', __( 'Home Insurance', 'amortexa-mortgage-calculator' ) );
	$tokens[] = amortexa_color_token( 'costHoa', '--amortexa-cost-hoa', __( 'HOA Fee', 'amortexa-mortgage-calculator' ) );
	$tokens[] = amortexa_color_token( 'costPmi', '--amortexa-cost-pmi', __( 'PMI', 'amortexa-mortgage-calculator' ) );
	$tokens[] = amortexa_color_token( 'costOther', '--amortexa-cost-other', __( 'Other Costs', 'amortexa-mortgage-calculator' ) );

	// Chart legends.
	$tokens[] = amortexa_color_token( 'legendText', '--amortexa-legend-text', __( 'Text', 'amortexa-mortgage-calculator' ) );
	$tokens[] = amortexa_select_token( 'legendFamily', '--amortexa-legend-family', __( 'Font', 'amortexa-mortgage-calculator' ), 'families' );
	$tokens[] = amortexa_length_token( 'legendSize', '--amortexa-legend-size', __( 'Font size', 'amortexa-mortgage-calculator' ), 24, 8 );
	$tokens[] = amortexa_select_token( 'legendWeight', '--amortexa-legend-weight', __( 'Weight', 'amortexa-mortgage-calculator' ), 'weights' );
	$tokens[] = amortexa_length_token( 'legendSwatchSize', '--amortexa-legend-swatch-size', __( 'Swatch size', 'amortexa-mortgage-calculator' ), 40, 6 );
	$tokens[] = amortexa_length_token( 'legendGap', '--amortexa-legend-gap', __( 'Gap between items', 'amortexa-mortgage-calculator' ), 48 );

	// The amortization table.
	$tokens[] = amortexa_color_token( 'tableHeadBg', '--amortexa-table-head-bg', __( 'Header background', 'amortexa-mortgage-calculator' ) );
	$tokens[] = amortexa_color_token( 'tableHeadText', '--amortexa-table-head-text', __( 'Header text', 'amortexa-mortgage-calculator' ) );
	$tokens[] = amortexa_color_token( 'tableRowBg', '--amortexa-table-row-bg', __( 'Row background', 'amortexa-mortgage-calculator' ) );
	$tokens[] = amortexa_color_token( 'tableZebraBg', '--amortexa-table-zebra-bg', __( 'Alternating row', 'amortexa-mortgage-calculator' ) );
	$tokens[] = amortexa_color_token( 'tableBorder', '--amortexa-table-border', __( 'Borders', 'amortexa-mortgage-calculator' ) );
	$tokens[] = amortexa_length_token( 'tableBorderWidth', '--amortexa-table-border-width', __( 'Border width', 'amortexa-mortgage-calculator' ), 12 );
	$tokens[] = amortexa_spacing_token( 'tableCellPadding', '--amortexa-table-cell-padding', __( 'Cell padding', 'amortexa-mortgage-calculator' ), 48 );
	$tokens[] = amortexa_length_token( 'tableSize', '--amortexa-table-size', __( 'Font size', 'amortexa-mortgage-calculator' ), 24, 8 );
	$tokens[] = amortexa_length_token( 'tableRadius', '--amortexa-table-radius', __( 'Corner radius', 'amortexa-mortgage-calculator' ), 64 );

	return $tokens;
}

/**
 * Returns the design groups, in inspector order.
 *
 * @return array<int,array<string,mixed>> Group definitions with their tokens.
 */
function amortexa_get_design_groups() {
	$groups = array(
		'root'   => array(
			'label'   => __( 'Calculator', 'amortexa-mortgage-calculator' ),
			'summary' => __( 'The card that wraps the whole block.', 'amortexa-mortgage-calculator' ),
			'tokens'  => array( 'rootBg', 'rootText', 'rootBorder', 'rootFamily', 'rootRadius', 'rootPadding' ),
		),
		'panel'  => array(
			'label'   => __( 'Panels', 'amortexa-mortgage-calculator' ),
			'summary' => __( 'The boxes around inputs, results, charts and schedule.', 'amortexa-mortgage-calculator' ),
			'tokens'  => array( 'panelBg', 'panelRadius', 'panelPadding', 'panelGap' ),
		),
		'label'  => array(
			'label'   => __( 'Field Labels', 'amortexa-mortgage-calculator' ),
			'summary' => __( 'The text above each input.', 'amortexa-mortgage-calculator' ),
			'tokens'  => array( 'labelColor', 'labelFamily', 'labelSize', 'labelWeight', 'labelTransform', 'labelTracking', 'labelMargin' ),
		),
		'field'  => array(
			'label'   => __( 'Inputs', 'amortexa-mortgage-calculator' ),
			'summary' => __( 'Text inputs and dropdowns.', 'amortexa-mortgage-calculator' ),
			'tokens'  => array( 'fieldText', 'fieldBg', 'fieldBorder', 'fieldBorderWidth', 'fieldRadius', 'fieldPadding', 'fieldHeight', 'fieldFamily', 'fieldSize', 'fieldWeight' ),
		),
		'slider' => array(
			'label'   => __( 'Sliders', 'amortexa-mortgage-calculator' ),
			'summary' => __( 'The draggable range controls.', 'amortexa-mortgage-calculator' ),
			'tokens'  => array( 'sliderTrack', 'sliderAccent', 'sliderThumbSize' ),
		),
		'toggle' => array(
			'label'   => __( 'Sliders Toggle', 'amortexa-mortgage-calculator' ),
			'summary' => __( 'The button that shows and hides the sliders.', 'amortexa-mortgage-calculator' ),
			'tokens'  => array( 'toggleBg', 'toggleText', 'toggleBorder', 'toggleRadius', 'togglePadding', 'toggleFamily', 'toggleSize', 'toggleWeight' ),
		),
		'result' => array(
			'label'   => __( 'Results', 'amortexa-mortgage-calculator' ),
			'summary' => __( 'The monthly payment and the rows beneath it.', 'amortexa-mortgage-calculator' ),
			'tokens'  => array( 'resultPrimaryColor', 'resultPrimaryFamily', 'resultPrimarySize', 'resultPrimaryWeight', 'resultLabelColor', 'resultValueColor', 'resultSize', 'resultRowBorder', 'resultRowPadding', 'resultMargin' ),
		),
		'chart'  => array(
			'label'   => __( 'Charts', 'amortexa-mortgage-calculator' ),
			'summary' => __( 'Series colours, size and titles.', 'amortexa-mortgage-calculator' ),
			'tokens'  => array( 'accent', 'accentAlt', 'costPi', 'costTax', 'costInsurance', 'costHoa', 'costPmi', 'costOther', 'chartHeight', 'chartAxis', 'chartTitleColor', 'chartTitleSize', 'chartTitleWeight', 'chartTitleMargin' ),
		),
		'legend' => array(
			'label'   => __( 'Chart Legends', 'amortexa-mortgage-calculator' ),
			'summary' => __( 'The colour key under each chart.', 'amortexa-mortgage-calculator' ),
			'tokens'  => array( 'legendText', 'legendFamily', 'legendSize', 'legendWeight', 'legendSwatchSize', 'legendGap' ),
		),
		'table'  => array(
			'label'   => __( 'Schedule Table', 'amortexa-mortgage-calculator' ),
			'summary' => __( 'The amortization table header, rows and borders.', 'amortexa-mortgage-calculator' ),
			'tokens'  => array( 'tableHeadBg', 'tableHeadText', 'tableRowBg', 'tableZebraBg', 'tableBorder', 'tableBorderWidth', 'tableCellPadding', 'tableSize', 'tableRadius' ),
		),
	);

	$by_key = array();

	foreach ( amortexa_get_design_tokens() as $token ) {
		$by_key[ $token['key'] ] = $token;
	}

	$ordered = array();

	foreach ( $groups as $key => $group ) {
		$token_keys = $group['tokens'];
		$resolved   = array();

		foreach ( $token_keys as $token_key ) {
			if ( isset( $by_key[ $token_key ] ) ) {
				$resolved[] = $by_key[ $token_key ];
			} else {
				// A typo here would silently drop a control from the inspector.
				_doing_it_wrong(
					__FUNCTION__,
					esc_html(
						sprintf(
							/* translators: 1: group key, 2: token key. */
							__( 'Design group "%1$s" lists unknown token "%2$s".', 'amortexa-mortgage-calculator' ),
							$key,
							$token_key
						)
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
function amortexa_get_design_token_map() {
	$map = array();

	foreach ( amortexa_get_design_tokens() as $token ) {
		$map[ $token['key'] ] = $token;
	}

	return $map;
}

/**
 * Normalises one raw token value into the string that gets stored and emitted.
 *
 * @param array<string,mixed> $token Token definition.
 * @param mixed               $raw   Untrusted value.
 * @return string Sanitized value, or an empty string to inherit.
 */
function amortexa_sanitize_design_value( $token, $raw ) {
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

			return (string) (int) amortexa_clamp_float( $raw, $min, $max );

		case 'spacing':
			$parts = preg_split( '/\s+/', $raw );
			$kept  = array();

			foreach ( (array) $parts as $part ) {
				if ( ! is_numeric( $part ) ) {
					continue;
				}

				$kept[] = (int) amortexa_clamp_float( $part, 0, $max );
			}

			// Zero parts means the value was junk, so inherit instead of clearing.
			if ( ! $kept ) {
				return '';
			}

			return implode( ' ', array_slice( $kept, 0, 4 ) );

		case 'select':
			$lists = amortexa_get_design_option_lists();
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
function amortexa_sanitize_design( $raw ) {
	if ( ! is_array( $raw ) ) {
		return array();
	}

	$sanitized = array();

	foreach ( amortexa_get_design_token_map() as $key => $token ) {
		if ( ! array_key_exists( $key, $raw ) ) {
			continue;
		}

		$value = amortexa_sanitize_design_value( $token, $raw[ $key ] );

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
function amortexa_get_design_values( $attributes ) {
	$attributes = is_array( $attributes ) ? $attributes : array();
	$design     = isset( $attributes['design'] ) && is_array( $attributes['design'] )
		? amortexa_sanitize_design( $attributes['design'] )
		: array();

	foreach ( amortexa_get_design_token_map() as $key => $token ) {
		if ( isset( $design[ $key ] ) ) {
			continue;
		}

		if ( empty( $token['legacy'] ) || empty( $attributes[ $token['legacy'] ] ) ) {
			continue;
		}

		$legacy = amortexa_sanitize_design_value( $token, $attributes[ $token['legacy'] ] );

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
function amortexa_design_css_value( $token, $value ) {
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
function amortexa_get_design_css( $attributes ) {
	$tokens = amortexa_get_design_token_map();
	$values = amortexa_get_design_values( $attributes );
	$css    = array();

	foreach ( $tokens as $key => $token ) {
		if ( ! isset( $values[ $key ] ) ) {
			continue;
		}

		$css[] = $token['var'] . ':' . amortexa_design_css_value( $token, $values[ $key ] );
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
function amortexa_get_design_chart_metrics( $attributes ) {
	$values = amortexa_get_design_values( $attributes );
	$height = isset( $values['chartHeight'] ) ? (int) $values['chartHeight'] : 0;

	return array(
		'height' => $height > 0 ? $height : 0,
	);
}
