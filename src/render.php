<?php
/**
 * Server-side render template for the Mortgage Calculator block.
 *
 * Registered as the block's render callback via the `"render": "file:./render.php"`
 * entry in block.json, so WordPress passes `$attributes`, `$content`, and
 * `$block` into this file's scope. Everything it needs is prepared here before
 * any markup is emitted.
 *
 * @var array<string,mixed> $attributes Raw block attributes from the editor.
 * @var string              $content    Unused; the block is fully dynamic.
 * @var WP_Block            $block      Unused; present for the standard signature.
 *
 * Every dynamic value is escaped on output. Results, sliders, and the
 * amortization schedule are fully functional without JavaScript; the view
 * script adds slider syncing, live recalculation, and the SVG charts.
 *
 * @package Amortexa
 */

/*
 * WordPress.NamingConventions.PrefixAllGlobals
 *
 * Every variable below is a local of this render call, not a global: WordPress
 * includes a block render template from inside WP_Block::render(), so the file
 * body runs in that method's scope and nothing here can be overwritten by, or
 * overwrite, another plugin. The sniff reads a top-level assignment in a file
 * that is not a class or function as a global definition, which is right for an
 * included file and wrong for a render callback. Prefixing ~70 template locals
 * would rename render.php away from the render.php that src/block.json points
 * at, and would buy nothing.
 */
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Render callback locals, not globals; see above.

// Abort if this file is called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

unset( $content, $block );

/**
 * Fires immediately before the calculator block renders.
 *
 * @param array<string,mixed> $attributes Raw block attributes.
 */
do_action( 'amortexa_before_calculator_render', $attributes );

$attrs  = amortexa_sanitize_attributes( $attributes );
$result = amortexa_calculate( $attrs );
$symbol = amortexa_resolve_currency_symbol( $attrs );

$settings = amortexa_get_settings();
$decimals = absint( isset( $settings['decimal_precision'] ) ? $settings['decimal_precision'] : 2 );
$uid      = function_exists( 'wp_unique_id' ) ? wp_unique_id( 'amortexa-calc-' ) : uniqid( 'amortexa-calc-' );
$position = isset( $attrs['currencyPosition'] ) ? (string) $attrs['currencyPosition'] : 'prefix';

$config = array(
	'decimals'         => $decimals,
	'symbol'           => $symbol,
	'position'         => $position,
	'showAmortization' => ! empty( $attrs['showAmortization'] ),
	'showCharts'       => ! empty( $attrs['showCharts'] ),
	'chartType'        => (string) $attrs['chartType'],
	// Charts are drawn as SVG in JS, so a chart height override cannot travel
	// as a custom property the way colours do.
	'chartHeight'      => (int) amortexa_get_design_chart_metrics( $attrs )['height'],
	'labels'           => array(
		'monthly'     => __( 'Monthly Payment', 'amortexa-mortgage-calculator' ),
		'totalMonthly' => __( 'Total Monthly Cost', 'amortexa-mortgage-calculator' ),
		'principal'   => __( 'Financed Principal', 'amortexa-mortgage-calculator' ),
		'totalInt'    => __( 'Total Interest', 'amortexa-mortgage-calculator' ),
		'totalPaid'   => __( 'Total Paid', 'amortexa-mortgage-calculator' ),
		'totalCosts'  => __( 'Total Taxes & Costs', 'amortexa-mortgage-calculator' ),
		'outOfPocket' => __( 'Total Out-of-Pocket', 'amortexa-mortgage-calculator' ),
		'pi'          => __( 'Principal & Interest', 'amortexa-mortgage-calculator' ),
		'toggle'      => __( 'Collapse schedule', 'amortexa-mortgage-calculator' ),
		'toggleOpen'  => __( 'Expand schedule', 'amortexa-mortgage-calculator' ),
		'year'        => __( 'Year', 'amortexa-mortgage-calculator' ),
		'prinPaid'    => __( 'Principal Paid', 'amortexa-mortgage-calculator' ),
		'intPaid'     => __( 'Interest Paid', 'amortexa-mortgage-calculator' ),
		'balance'     => __( 'Remaining Balance', 'amortexa-mortgage-calculator' ),
		'balanceY1'   => __( 'Balance After Year 1', 'amortexa-mortgage-calculator' ),
		'cumInt'      => __( 'Cumulative Interest', 'amortexa-mortgage-calculator' ),
	),
);

/*
 * Legend labels are taken from the component descriptors rather than repeated
 * here, so the chart legend and the results rows cannot drift apart, and both
 * pick up translations from one place.
 */
foreach ( amortexa_get_cost_components() as $component_key => $component ) {
	$config['labels'][ $component_key ] = $component['label'];
}

/*
 * Field metadata drives both the number inputs and their paired range
 * sliders. `smax` values are display bounds for the slider track only — the
 * number inputs keep accepting any valid value.
 */
$fields = array(
	array(
		'id'    => $uid . '-amount',
		'name'  => 'loanAmount',
		'label' => __( 'Loan Amount', 'amortexa-mortgage-calculator' ),
		'value' => (string) $attrs['loanAmount'],
		'step'  => 'any',
		'min'   => '0',
		'max'   => '',
		'smin'  => '10000',
		'smax'  => '2000000',
		'sstep' => '5000',
	),
	array(
		'id'    => $uid . '-down',
		'name'  => 'downPayment',
		'label' => __( 'Down Payment', 'amortexa-mortgage-calculator' ),
		'value' => (string) $attrs['downPayment'],
		'step'  => 'any',
		'min'   => '0',
		'max'   => '',
		'smin'  => '0',
		'smax'  => (string) max( $attrs['loanAmount'], 1 ),
		'sstep' => '2500',
	),
	array(
		'id'    => $uid . '-rate',
		'name'  => 'interestRate',
		'label' => __( 'Interest Rate (%)', 'amortexa-mortgage-calculator' ),
		'value' => (string) $attrs['interestRate'],
		'step'  => '0.01',
		'min'   => '0',
		'max'   => '100',
		'smin'  => '0',
		'smax'  => '20',
		'sstep' => '0.05',
	),
	array(
		'id'    => $uid . '-term',
		'name'  => 'loanTerm',
		'label' => __( 'Term (Years)', 'amortexa-mortgage-calculator' ),
		'value' => (string) $attrs['loanTerm'],
		'step'  => '1',
		'min'   => '1',
		'max'   => '60',
		'smin'  => '1',
		'smax'  => '40',
		'sstep' => '1',
	),
);

/*
 * Recurring ownership costs. These only render when the author turns them on, so
 * an existing calculator keeps the exact field list it shipped with. A component
 * that can be a percentage carries its unit toggle; one that is always a cash
 * figure does not.
 */
$cost_fields = array();

if ( ! empty( $attrs['showCosts'] ) ) {
	foreach ( amortexa_get_cost_components() as $component_key => $component ) {
		if ( empty( $component['percent'] ) ) {
			continue;
		}

		$attribute = amortexa_get_cost_attribute( $component_key );
		$unit      = amortexa_resolve_cost_units( $attrs );
		$is_amount = isset( $unit[ $attribute ] ) && 'amount' === $unit[ $attribute ];

		$cost_fields[] = array(
			'id'      => $uid . '-cost-' . $component_key,
			'name'    => $attribute,
			'label'   => $component['label'],
			'value'   => (string) $attrs[ $attribute ],
			'step'    => $is_amount ? 'any' : '0.01',
			'min'     => '0',
			'max'     => '',
			'unit'    => $unit[ $attribute ],
			'unitAttr' => $attribute . 'Unit',
		);
	}
}

/*
 * Which cost components are worth showing. A component the author left at zero is
 * dropped rather than rendered as a $0.00 row, so a calculator that only has tax
 * does not show six lines with five of them empty. Principal and interest is
 * always present because it is the loan itself, and it anchors the chart legend.
 */
$breakdown   = array();
$cost_values = isset( $result['monthly_costs'] ) ? (array) $result['monthly_costs'] : array();

foreach ( amortexa_get_cost_components() as $component_key => $component ) {
	if ( 'pi' === $component_key ) {
		$amount = (float) $result['monthly_payment'];
	} elseif ( isset( $cost_values[ $component_key ] ) ) {
		$amount = (float) $cost_values[ $component_key ];
	} else {
		$amount = 0.0;
	}

	// Only ever hide a component the author did not fill in.
	if ( $amount <= 0 && 'pi' !== $component_key ) {
		continue;
	}

	/*
	 * The bind key has to match the one view.js builds from the component key,
	 * since it is the only handle connecting a row to a live recalculation.
	 */
	$bind_key = 'pi' === $component_key ? 'monthlyPayment' : 'cost' . ucfirst( $component_key );

	$breakdown[] = array(
		'key'    => $component_key,
		'label'  => $component['label'],
		'bind'   => $bind_key,
		'amount' => round( $amount, 2 ),
		'color'  => amortexa_get_cost_component_color( $attrs, $component_key ),
	);
}

$show_costs = ! empty( $attrs['showCosts'] ) && count( $breakdown ) > 0;

/*
 * Inline typography for the primary result. Only emitted when the user set a
 * custom size or weight; otherwise the stylesheet's responsive default wins.
 */
$typography = array();

if ( (float) $attrs['paymentFontSize'] > 0 ) {
	$typography[] = sprintf( 'font-size:%dpx', (int) $attrs['paymentFontSize'] );
}

if ( '' !== $attrs['paymentFontWeight'] ) {
	$typography[] = sprintf( 'font-weight:%s', (string) $attrs['paymentFontWeight'] );
}

$typography_attr = implode( ';', $typography );

/*
 * Every appearance override, derived from the design token schema, so the
 * frontend and the editor resolve identical values. Empty values mean "use the
 * active skin" and are skipped, which is what keeps skins working until a value
 * is explicitly set.
 */
$style_vars = amortexa_get_design_css( $attrs );

$font_stack = amortexa_get_font_stack( isset( $attrs['fontFamily'] ) ? (string) $attrs['fontFamily'] : 'inherit' );

if ( '' !== $font_stack ) {
	$style_vars[] = 'font-family:' . $font_stack;
}

/*
 * Which panels are on, and in what order. Every panel including the form is
 * reorderable, so the form is rendered by the loop below rather than pinned
 * ahead of it.
 */
$visible_panels = array( 'form' );

if ( ! empty( $attrs['showResults'] ) ) {
	$visible_panels[] = 'results';
}

if ( ! empty( $attrs['showCharts'] ) ) {
	$visible_panels[] = 'charts';
}

if ( ! empty( $attrs['showAmortization'] ) && ! empty( $result['schedule'] ) ) {
	$visible_panels[] = 'schedule';
}

$panel_order = amortexa_resolve_panel_order( $attrs['panelOrder'], $visible_panels );

/*
 * Two of the layouts group the panels into columns. `aside` puts the inputs
 * alone in the first column and the results with the charts in the second;
 * `chart-aside` puts the inputs with the results in the first and the charts in
 * the second. Saved panel order decides the sequence inside each column.
 *
 * The amortization table is never grouped. It stays a direct child of the grid
 * and renders last, so it keeps the full width of the calculator in every
 * layout instead of being squeezed into a column where a four column table is
 * unreadable.
 */
$layout_columns = array();
$body_panels    = array_values( array_diff( $panel_order, array( 'form', 'schedule' ) ) );

if ( 'aside' === $attrs['layout'] ) {
	$layout_columns = array(
		array( 'form' ),
		$body_panels,
	);
} elseif ( 'chart-aside' === $attrs['layout'] ) {
	$layout_columns = array(
		array_merge( array( 'form' ), array_values( array_intersect( $body_panels, array( 'results' ) ) ) ),
		array_values( array_intersect( $body_panels, array( 'charts' ) ) ),
	);
}

$grouped_panels = array();

foreach ( $layout_columns as $column_panels ) {
	$grouped_panels = array_merge( $grouped_panels, $column_panels );
}

if ( ! empty( $layout_columns ) ) {
	$panel_order = $grouped_panels;

	if ( in_array( 'schedule', $visible_panels, true ) ) {
		$panel_order[] = 'schedule';
	}
}

/*
 * Both columns collapse to one when there is nothing to put beside the form:
 * stacked and split when results are off, the column layouts when a single
 * column has anything in it at all.
 */
$single_column = ! empty( $layout_columns )
	? count( $grouped_panels ) <= 1
	: ! in_array( 'results', $visible_panels, true );

/*
 * The panels render from a slot list rather than straight from $panel_order, so
 * one pass can open a column, print a panel, and close that column again
 * without the panel markup having to be repeated for each layout. A slot is
 * either a column to open, a panel to print, or a column to close.
 *
 * Columns are only emitted when there is more than one of them: a layout that
 * has collapsed to a single column renders flat, exactly as stacked does.
 */
$slots          = array();
$filled_columns = array_filter( $layout_columns );

if ( 1 < count( $filled_columns ) ) {
	$column_index = 0;

	foreach ( $filled_columns as $column_panels ) {
		$slots[] = array( 'column', 0 === $column_index ? 'form' : 'details' );

		foreach ( $column_panels as $column_panel ) {
			$slots[] = array( 'panel', $column_panel );
		}

		$slots[] = array( 'column-end' );
		++$column_index;
	}

	/*
	 * The amortization table never joins a column, so it is picked back out of
	 * the order and printed on its own, after both columns have closed.
	 */
	foreach ( $panel_order as $trailing_panel ) {
		if ( 'schedule' === $trailing_panel ) {
			$slots[] = array( 'panel', 'schedule' );
		}
	}
} else {
	foreach ( $panel_order as $flat_panel ) {
		$slots[] = array( 'panel', $flat_panel );
	}
}

$wrapper_args = array(
	'class' => 'amortexa-calc amortexa-theme-' . esc_attr( $attrs['theme'] ) . ' amortexa-calc--layout-' . esc_attr( $attrs['layout'] ) . ' amortexa-calc--form-columns-' . esc_attr( $attrs['formColumns'] ),
);

if ( ! empty( $style_vars ) ) {
	$wrapper_args['style'] = implode( ';', $style_vars );
}
?>
<div
	<?php echo get_block_wrapper_attributes( $wrapper_args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() escapes internally. ?>
	data-amortexa-config="<?php echo esc_attr( wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) ); ?>"
>
	<div class="amortexa-calc__grid amortexa-calc__grid--<?php echo esc_attr( $attrs['layout'] ); ?><?php echo $single_column ? ' amortexa-calc__grid--form-only' : ''; ?>">
		<?php foreach ( $slots as $slot ) : ?>
			<?php if ( 'column' === $slot[0] ) : ?>
		<div class="amortexa-calc__column amortexa-calc__column--<?php echo esc_html( $slot[1] ); ?>">
			<?php continue; ?>
			<?php endif; ?>
			<?php if ( 'column-end' === $slot[0] ) : ?>
		</div>
			<?php continue; ?>
			<?php endif; ?>
			<?php
			/*
			 * $panel_order no longer drives this loop, so the slot's panel name
			 * is what the panel branches below match on.
			 */
			$panel = $slot[1];
			?>
			<?php if ( 'form' === $panel ) : ?>
		<form class="amortexa-calc__form" autocomplete="off">
			<?php foreach ( $fields as $field ) : ?>
				<div class="amortexa-calc__control">
					<label class="amortexa-calc__label" for="<?php echo esc_attr( $field['id'] ); ?>">
						<?php echo esc_html( $field['label'] ); ?>
					</label>
					<div class="amortexa-calc__control-row">
						<?php if ( ! empty( $attrs['showSliders'] ) ) : ?>
							<input
								type="range"
								class="amortexa-calc__slider"
								data-amortexa-slider="<?php echo esc_attr( $field['name'] ); ?>"
								value="<?php echo esc_attr( $field['value'] ); ?>"
								min="<?php echo esc_attr( $field['smin'] ); ?>"
								max="<?php echo esc_attr( $field['smax'] ); ?>"
								step="<?php echo esc_attr( $field['sstep'] ); ?>"
								aria-label="<?php echo esc_attr( $field['label'] ); ?>"
							/>
						<?php endif; ?>
						<input
							type="number"
							class="amortexa-calc__field"
							id="<?php echo esc_attr( $field['id'] ); ?>"
							data-amortexa-field="<?php echo esc_attr( $field['name'] ); ?>"
							value="<?php echo esc_attr( $field['value'] ); ?>"
							step="<?php echo esc_attr( $field['step'] ); ?>"
							min="<?php echo esc_attr( $field['min'] ); ?>"
							<?php if ( '' !== $field['max'] ) : ?>
								max="<?php echo esc_attr( $field['max'] ); ?>"
							<?php endif; ?>
							inputmode="decimal"
						/>
					</div>
				</div>
			<?php endforeach; ?>
			<?php if ( $cost_fields ) : ?>
				<fieldset class="amortexa-calc__costs">
					<legend class="amortexa-calc__costs-legend">
						<?php esc_html_e( 'Taxes & Costs (annual)', 'amortexa-mortgage-calculator' ); ?>
					</legend>
					<?php foreach ( $cost_fields as $cost_field ) : ?>
						<div class="amortexa-calc__control">
							<label class="amortexa-calc__label" for="<?php echo esc_attr( $cost_field['id'] ); ?>">
								<?php echo esc_html( $cost_field['label'] ); ?>
							</label>
							<div class="amortexa-calc__control-row">
								<input
									type="number"
									class="amortexa-calc__field"
									id="<?php echo esc_attr( $cost_field['id'] ); ?>"
									data-amortexa-field="<?php echo esc_attr( $cost_field['name'] ); ?>"
									value="<?php echo esc_attr( $cost_field['value'] ); ?>"
									step="<?php echo esc_attr( $cost_field['step'] ); ?>"
									min="<?php echo esc_attr( $cost_field['min'] ); ?>"
									inputmode="decimal"
								/>
								<button
									type="button"
									class="amortexa-calc__unit"
									data-amortexa-unit="<?php echo esc_attr( $cost_field['unitAttr'] ); ?>"
									aria-label="<?php
										/* translators: %s: cost component name. */
										printf( esc_attr__( 'Toggle %s between a percentage and an amount', 'amortexa-mortgage-calculator' ), esc_attr( $cost_field['label'] ) );
									?>"
								>
									<?php echo esc_html( 'percent' === $cost_field['unit'] ? '%' : __( 'Amount', 'amortexa-mortgage-calculator' ) ); ?>
								</button>
							</div>
						</div>
					<?php endforeach; ?>
				</fieldset>
			<?php endif; ?>
		</form>
			<?php elseif ( 'results' === $panel ) : ?>
			<div class="amortexa-calc__results">
				<p class="amortexa-calc__result-label" data-amortexa-label="monthly">
					<?php echo esc_html( $config['labels']['monthly'] ); ?>
				</p>
				<p
					class="amortexa-calc__result-primary"
					data-amortexa-bind="monthlyPayment"
					aria-live="polite"
					aria-atomic="true"
					<?php if ( '' !== $typography_attr ) : ?>
						style="<?php echo esc_attr( $typography_attr ); ?>"
					<?php endif; ?>
				>
					<?php echo esc_html( amortexa_format_amount( $result['monthly_payment'], $symbol, $decimals, $position ) ); ?>
				</p>
				<dl class="amortexa-calc__result-list">
					<div class="amortexa-calc__result-row">
						<dt><?php echo esc_html( $config['labels']['principal'] ); ?></dt>
						<dd data-amortexa-bind="principal">
							<?php echo esc_html( amortexa_format_amount( $result['principal'], $symbol, $decimals, $position ) ); ?>
						</dd>
					</div>
					<div class="amortexa-calc__result-row">
						<dt><?php echo esc_html( $config['labels']['totalInt'] ); ?></dt>
						<dd data-amortexa-bind="totalInterest">
							<?php echo esc_html( amortexa_format_amount( $result['total_interest'], $symbol, $decimals, $position ) ); ?>
						</dd>
					</div>
					<div class="amortexa-calc__result-row">
						<dt><?php echo esc_html( $config['labels']['totalPaid'] ); ?></dt>
						<dd data-amortexa-bind="totalPaid">
							<?php echo esc_html( amortexa_format_amount( $result['total_paid'], $symbol, $decimals, $position ) ); ?>
						</dd>
					</div>
				</dl>

				<?php if ( $show_costs ) : ?>
				<dl class="amortexa-calc__result-list amortexa-calc__result-list--costs">
					<?php foreach ( $breakdown as $breakdown_row ) : ?>
						<div
							class="amortexa-calc__result-row amortexa-calc__result-row--cost"
							data-amortexa-cost="<?php echo esc_attr( $breakdown_row['key'] ); ?>"
						>
							<dt>
								<span
									class="amortexa-calc__cost-swatch"
									style="background-color:<?php echo esc_attr( $breakdown_row['color'] ); ?>"
									aria-hidden="true"
								></span>
								<?php echo esc_html( $breakdown_row['label'] ); ?>
							</dt>
							<dd data-amortexa-bind="<?php echo esc_attr( $breakdown_row['bind'] ); ?>">
								<?php echo esc_html( amortexa_format_amount( $breakdown_row['amount'], $symbol, $decimals, $position ) ); ?>
							</dd>
						</div>
					<?php endforeach; ?>

						<div class="amortexa-calc__result-row amortexa-calc__result-row--total">
							<dt><?php echo esc_html( $config['labels']['totalMonthly'] ); ?></dt>
							<dd data-amortexa-bind="totalMonthlyCost">
								<?php echo esc_html( amortexa_format_amount( $result['total_monthly_cost'], $symbol, $decimals, $position ) ); ?>
							</dd>
						</div>
				</dl>

					<dl class="amortexa-calc__result-list amortexa-calc__result-list--totals">
						<div class="amortexa-calc__result-row">
							<dt><?php echo esc_html( $config['labels']['totalCosts'] ); ?></dt>
							<dd data-amortexa-bind="totalCosts">
								<?php echo esc_html( amortexa_format_amount( $result['total_costs'], $symbol, $decimals, $position ) ); ?>
							</dd>
						</div>
						<div class="amortexa-calc__result-row">
							<dt><?php echo esc_html( $config['labels']['outOfPocket'] ); ?></dt>
							<dd data-amortexa-bind="totalOutOfPocket">
								<?php echo esc_html( amortexa_format_amount( $result['total_out_of_pocket'], $symbol, $decimals, $position ) ); ?>
							</dd>
						</div>
					</dl>
				<?php endif; ?>
			</div>
			<?php endif; ?>

			<?php if ( 'charts' === $panel ) : ?>
			<div class="amortexa-calc__charts">
				<?php if ( in_array( $attrs['chartType'], array( 'donut', 'both' ), true ) ) : ?>
					<figure class="amortexa-calc__chart">
						<figcaption class="amortexa-calc__chart-title">
							<?php echo esc_html__( 'Payment Composition', 'amortexa-mortgage-calculator' ); ?>
						</figcaption>
						<div class="amortexa-calc__chart-body" data-amortexa-chart="donut"></div>
						<figcaption class="amortexa-calc__legend" data-amortexa-legend="donut"></figcaption>
					</figure>
				<?php endif; ?>

				<?php if ( in_array( $attrs['chartType'], array( 'line', 'both' ), true ) ) : ?>
					<figure class="amortexa-calc__chart">
						<figcaption class="amortexa-calc__chart-title">
							<?php echo esc_html__( 'Balance Over Time', 'amortexa-mortgage-calculator' ); ?>
						</figcaption>
						<div class="amortexa-calc__chart-body" data-amortexa-chart="line"></div>
						<figcaption class="amortexa-calc__legend" data-amortexa-legend="line"></figcaption>
					</figure>
				<?php endif; ?>

				<?php if ( 'bar' === $attrs['chartType'] ) : ?>
					<figure class="amortexa-calc__chart">
						<figcaption class="amortexa-calc__chart-title">
							<?php echo esc_html__( 'Principal vs Interest by Year', 'amortexa-mortgage-calculator' ); ?>
						</figcaption>
						<div class="amortexa-calc__chart-body" data-amortexa-chart="bar"></div>
						<figcaption class="amortexa-calc__legend" data-amortexa-legend="bar"></figcaption>
					</figure>
				<?php endif; ?>

				<?php if ( 'dots' === $attrs['chartType'] ) : ?>
					<figure class="amortexa-calc__chart">
						<figcaption class="amortexa-calc__chart-title">
							<?php echo esc_html__( 'Parameter Comparison', 'amortexa-mortgage-calculator' ); ?>
						</figcaption>
						<div class="amortexa-calc__chart-body" data-amortexa-chart="dots"></div>
						<figcaption class="amortexa-calc__legend" data-amortexa-legend="dots"></figcaption>
					</figure>
				<?php endif; ?>
			</div>
			<?php endif; ?>

			<?php if ( 'schedule' === $panel ) : ?>
			<div class="amortexa-calc__schedule">
				<button
					type="button"
					class="amortexa-calc__toggle"
					aria-expanded="true"
					aria-controls="<?php echo esc_attr( $uid . '-schedule' ); ?>"
					data-amortexa-label-collapse="<?php echo esc_attr( $config['labels']['toggle'] ); ?>"
					data-amortexa-label-expand="<?php echo esc_attr( $config['labels']['toggleOpen'] ); ?>"
				>
					<?php echo esc_html( $config['labels']['toggle'] ); ?>
				</button>
				<?php
				/*
				 * The table sits in its own wrapper because the toggle hides only
				 * this element. Hiding the whole schedule block would take the
				 * button down with it and leave no way to expand the table again.
				 */
				?>
				<div
					class="amortexa-calc__schedule-body"
					id="<?php echo esc_attr( $uid . '-schedule' ); ?>"
					data-amortexa-schedule-body
				>
					<table class="amortexa-calc__table">
					<caption class="screen-reader-text">
						<?php echo esc_html__( 'Amortization Schedule', 'amortexa-mortgage-calculator' ); ?>
					</caption>
					<thead>
						<tr>
							<th scope="col"><?php echo esc_html( $config['labels']['year'] ); ?></th>
							<th scope="col"><?php echo esc_html( $config['labels']['prinPaid'] ); ?></th>
							<th scope="col"><?php echo esc_html( $config['labels']['intPaid'] ); ?></th>
							<th scope="col"><?php echo esc_html( $config['labels']['balance'] ); ?></th>
						</tr>
					</thead>
					<tbody data-amortexa-schedule>
						<?php foreach ( $result['schedule'] as $row ) : ?>
							<tr>
								<td><?php echo esc_html( (string) $row['year'] ); ?></td>
								<td><?php echo esc_html( amortexa_format_amount( $row['principal'], $symbol, $decimals, $position ) ); ?></td>
								<td><?php echo esc_html( amortexa_format_amount( $row['interest'], $symbol, $decimals, $position ) ); ?></td>
								<td><?php echo esc_html( amortexa_format_amount( $row['balance'], $symbol, $decimals, $position ) ); ?></td>
							</tr>
			<?php endforeach; ?>

					</tbody>
				</table>
				</div>
			</div>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>
</div>
<?php
/**
 * Fires immediately after the calculator block renders.
 *
 * @param array<string,mixed> $attributes Raw block attributes.
 */
do_action( 'amortexa_after_calculator_render', $attributes );
