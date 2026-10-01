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
 * @package CalcForge
 */

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
do_action( 'calcforge_before_calculator_render', $attributes );

$attrs  = calcforge_sanitize_attributes( $attributes );
$result = calcforge_calculate( $attrs );
$symbol = calcforge_resolve_currency_symbol( $attrs );

$settings = calcforge_get_settings();
$decimals = absint( isset( $settings['decimal_precision'] ) ? $settings['decimal_precision'] : 2 );
$uid      = function_exists( 'wp_unique_id' ) ? wp_unique_id( 'calcforge-calc-' ) : uniqid( 'calcforge-calc-' );
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
	'chartHeight'      => (int) calcforge_get_design_chart_metrics( $attrs )['height'],
	'labels'           => array(
		'monthly'     => __( 'Monthly Payment', CALCFORGE_TEXT_DOMAIN ),
		'totalMonthly' => __( 'Total Monthly Cost', CALCFORGE_TEXT_DOMAIN ),
		'principal'   => __( 'Financed Principal', CALCFORGE_TEXT_DOMAIN ),
		'totalInt'    => __( 'Total Interest', CALCFORGE_TEXT_DOMAIN ),
		'totalPaid'   => __( 'Total Paid', CALCFORGE_TEXT_DOMAIN ),
		'totalCosts'  => __( 'Total Taxes & Costs', CALCFORGE_TEXT_DOMAIN ),
		'outOfPocket' => __( 'Total Out-of-Pocket', CALCFORGE_TEXT_DOMAIN ),
		'pi'          => __( 'Principal & Interest', CALCFORGE_TEXT_DOMAIN ),
		'toggle'      => __( 'Collapse schedule', CALCFORGE_TEXT_DOMAIN ),
		'toggleOpen'  => __( 'Expand schedule', CALCFORGE_TEXT_DOMAIN ),
		'year'        => __( 'Year', CALCFORGE_TEXT_DOMAIN ),
		'prinPaid'    => __( 'Principal Paid', CALCFORGE_TEXT_DOMAIN ),
		'intPaid'     => __( 'Interest Paid', CALCFORGE_TEXT_DOMAIN ),
		'balance'     => __( 'Remaining Balance', CALCFORGE_TEXT_DOMAIN ),
		'balanceY1'   => __( 'Balance After Year 1', CALCFORGE_TEXT_DOMAIN ),
		'cumInt'      => __( 'Cumulative Interest', CALCFORGE_TEXT_DOMAIN ),
	),
);

/*
 * Legend labels are taken from the component descriptors rather than repeated
 * here, so the chart legend and the results rows cannot drift apart, and both
 * pick up translations from one place.
 */
foreach ( calcforge_get_cost_components() as $component_key => $component ) {
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
		'label' => __( 'Loan Amount', CALCFORGE_TEXT_DOMAIN ),
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
		'label' => __( 'Down Payment', CALCFORGE_TEXT_DOMAIN ),
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
		'label' => __( 'Interest Rate (%)', CALCFORGE_TEXT_DOMAIN ),
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
		'label' => __( 'Term (Years)', CALCFORGE_TEXT_DOMAIN ),
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
	foreach ( calcforge_get_cost_components() as $component_key => $component ) {
		if ( empty( $component['percent'] ) ) {
			continue;
		}

		$attribute = calcforge_get_cost_attribute( $component_key );
		$unit      = calcforge_resolve_cost_units( $attrs );
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

foreach ( calcforge_get_cost_components() as $component_key => $component ) {
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
		'color'  => calcforge_get_cost_component_color( $attrs, $component_key ),
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
$style_vars = calcforge_get_design_css( $attrs );

$font_stack = calcforge_get_font_stack( isset( $attrs['fontFamily'] ) ? (string) $attrs['fontFamily'] : 'inherit' );

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

$panel_order = calcforge_resolve_panel_order( $attrs['panelOrder'], $visible_panels );

$wrapper_args = array(
	'class' => 'calcforge-calc calcforge-theme-' . esc_attr( $attrs['theme'] ) . ' calcforge-calc--layout-' . esc_attr( $attrs['layout'] ) . ' calcforge-calc--form-columns-' . esc_attr( $attrs['formColumns'] ),
);

if ( ! empty( $style_vars ) ) {
	$wrapper_args['style'] = implode( ';', $style_vars );
}
?>
<div
	<?php echo get_block_wrapper_attributes( $wrapper_args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() escapes internally. ?>
	data-calcforge-config="<?php echo esc_attr( wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) ); ?>"
>
	<div class="calcforge-calc__grid calcforge-calc__grid--<?php echo esc_attr( $attrs['layout'] ); ?><?php echo in_array( 'results', $visible_panels, true ) ? '' : ' calcforge-calc__grid--form-only'; ?>">
		<?php foreach ( $panel_order as $panel ) : ?>
			<?php if ( 'form' === $panel ) : ?>
		<form class="calcforge-calc__form" autocomplete="off">
			<?php foreach ( $fields as $field ) : ?>
				<div class="calcforge-calc__control">
					<label class="calcforge-calc__label" for="<?php echo esc_attr( $field['id'] ); ?>">
						<?php echo esc_html( $field['label'] ); ?>
					</label>
					<div class="calcforge-calc__control-row">
						<?php if ( ! empty( $attrs['showSliders'] ) ) : ?>
							<input
								type="range"
								class="calcforge-calc__slider"
								data-calcforge-slider="<?php echo esc_attr( $field['name'] ); ?>"
								value="<?php echo esc_attr( $field['value'] ); ?>"
								min="<?php echo esc_attr( $field['smin'] ); ?>"
								max="<?php echo esc_attr( $field['smax'] ); ?>"
								step="<?php echo esc_attr( $field['sstep'] ); ?>"
								aria-label="<?php echo esc_attr( $field['label'] ); ?>"
							/>
						<?php endif; ?>
						<input
							type="number"
							class="calcforge-calc__field"
							id="<?php echo esc_attr( $field['id'] ); ?>"
							data-calcforge-field="<?php echo esc_attr( $field['name'] ); ?>"
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
		</form>
			<?php elseif ( 'results' === $panel ) : ?>
			<div class="calcforge-calc__results">
				<p class="calcforge-calc__result-label" data-calcforge-label="monthly">
					<?php echo esc_html( $config['labels']['monthly'] ); ?>
				</p>
				<p
					class="calcforge-calc__result-primary"
					data-calcforge-bind="monthlyPayment"
					aria-live="polite"
					aria-atomic="true"
					<?php if ( '' !== $typography_attr ) : ?>
						style="<?php echo esc_attr( $typography_attr ); ?>"
					<?php endif; ?>
				>
					<?php echo esc_html( calcforge_format_amount( $result['monthly_payment'], $symbol, $decimals, $position ) ); ?>
				</p>
				<dl class="calcforge-calc__result-list">
					<div class="calcforge-calc__result-row">
						<dt><?php echo esc_html( $config['labels']['principal'] ); ?></dt>
						<dd data-calcforge-bind="principal">
							<?php echo esc_html( calcforge_format_amount( $result['principal'], $symbol, $decimals, $position ) ); ?>
						</dd>
					</div>
					<div class="calcforge-calc__result-row">
						<dt><?php echo esc_html( $config['labels']['totalInt'] ); ?></dt>
						<dd data-calcforge-bind="totalInterest">
							<?php echo esc_html( calcforge_format_amount( $result['total_interest'], $symbol, $decimals, $position ) ); ?>
						</dd>
					</div>
					<div class="calcforge-calc__result-row">
						<dt><?php echo esc_html( $config['labels']['totalPaid'] ); ?></dt>
						<dd data-calcforge-bind="totalPaid">
							<?php echo esc_html( calcforge_format_amount( $result['total_paid'], $symbol, $decimals, $position ) ); ?>
						</dd>
					</div>
				</dl>

				<?php if ( $show_costs ) : ?>
				<dl class="calcforge-calc__result-list calcforge-calc__result-list--costs">
					<?php foreach ( $breakdown as $breakdown_row ) : ?>
						<div
							class="calcforge-calc__result-row calcforge-calc__result-row--cost"
							data-calcforge-cost="<?php echo esc_attr( $breakdown_row['key'] ); ?>"
						>
							<dt>
								<span
									class="calcforge-calc__cost-swatch"
									style="background-color:<?php echo esc_attr( $breakdown_row['color'] ); ?>"
									aria-hidden="true"
								></span>
								<?php echo esc_html( $breakdown_row['label'] ); ?>
							</dt>
							<dd data-calcforge-bind="<?php echo esc_attr( $breakdown_row['bind'] ); ?>">
								<?php echo esc_html( calcforge_format_amount( $breakdown_row['amount'], $symbol, $decimals, $position ) ); ?>
							</dd>
						</div>
					<?php endforeach; ?>

						<div class="calcforge-calc__result-row calcforge-calc__result-row--total">
							<dt><?php echo esc_html( $config['labels']['totalMonthly'] ); ?></dt>
							<dd data-calcforge-bind="totalMonthlyCost">
								<?php echo esc_html( calcforge_format_amount( $result['total_monthly_cost'], $symbol, $decimals, $position ) ); ?>
							</dd>
						</div>
				</dl>

					<dl class="calcforge-calc__result-list calcforge-calc__result-list--totals">
						<div class="calcforge-calc__result-row">
							<dt><?php echo esc_html( $config['labels']['totalCosts'] ); ?></dt>
							<dd data-calcforge-bind="totalCosts">
								<?php echo esc_html( calcforge_format_amount( $result['total_costs'], $symbol, $decimals, $position ) ); ?>
							</dd>
						</div>
						<div class="calcforge-calc__result-row">
							<dt><?php echo esc_html( $config['labels']['outOfPocket'] ); ?></dt>
							<dd data-calcforge-bind="totalOutOfPocket">
								<?php echo esc_html( calcforge_format_amount( $result['total_out_of_pocket'], $symbol, $decimals, $position ) ); ?>
							</dd>
						</div>
					</dl>
				<?php endif; ?>
			</div>
			<?php endif; ?>

			<?php if ( 'charts' === $panel ) : ?>
			<div class="calcforge-calc__charts">
				<?php if ( in_array( $attrs['chartType'], array( 'donut', 'both' ), true ) ) : ?>
					<figure class="calcforge-calc__chart">
						<figcaption class="calcforge-calc__chart-title">
							<?php echo esc_html__( 'Payment Composition', CALCFORGE_TEXT_DOMAIN ); ?>
						</figcaption>
						<div class="calcforge-calc__chart-body" data-calcforge-chart="donut"></div>
						<figcaption class="calcforge-calc__legend" data-calcforge-legend="donut"></figcaption>
					</figure>
				<?php endif; ?>

				<?php if ( in_array( $attrs['chartType'], array( 'line', 'both' ), true ) ) : ?>
					<figure class="calcforge-calc__chart">
						<figcaption class="calcforge-calc__chart-title">
							<?php echo esc_html__( 'Balance Over Time', CALCFORGE_TEXT_DOMAIN ); ?>
						</figcaption>
						<div class="calcforge-calc__chart-body" data-calcforge-chart="line"></div>
						<figcaption class="calcforge-calc__legend" data-calcforge-legend="line"></figcaption>
					</figure>
				<?php endif; ?>

				<?php if ( 'bar' === $attrs['chartType'] ) : ?>
					<figure class="calcforge-calc__chart">
						<figcaption class="calcforge-calc__chart-title">
							<?php echo esc_html__( 'Principal vs Interest by Year', CALCFORGE_TEXT_DOMAIN ); ?>
						</figcaption>
						<div class="calcforge-calc__chart-body" data-calcforge-chart="bar"></div>
						<figcaption class="calcforge-calc__legend" data-calcforge-legend="bar"></figcaption>
					</figure>
				<?php endif; ?>

				<?php if ( 'dots' === $attrs['chartType'] ) : ?>
					<figure class="calcforge-calc__chart">
						<figcaption class="calcforge-calc__chart-title">
							<?php echo esc_html__( 'Parameter Comparison', CALCFORGE_TEXT_DOMAIN ); ?>
						</figcaption>
						<div class="calcforge-calc__chart-body" data-calcforge-chart="dots"></div>
						<figcaption class="calcforge-calc__legend" data-calcforge-legend="dots"></figcaption>
					</figure>
				<?php endif; ?>
			</div>
			<?php endif; ?>

			<?php if ( 'schedule' === $panel ) : ?>
			<div class="calcforge-calc__schedule">
				<button
					type="button"
					class="calcforge-calc__toggle"
					aria-expanded="true"
					aria-controls="<?php echo esc_attr( $uid . '-schedule' ); ?>"
					data-calcforge-label-collapse="<?php echo esc_attr( $config['labels']['toggle'] ); ?>"
					data-calcforge-label-expand="<?php echo esc_attr( $config['labels']['toggleOpen'] ); ?>"
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
					class="calcforge-calc__schedule-body"
					id="<?php echo esc_attr( $uid . '-schedule' ); ?>"
					data-calcforge-schedule-body
				>
					<table class="calcforge-calc__table">
					<caption class="screen-reader-text">
						<?php echo esc_html__( 'Amortization Schedule', CALCFORGE_TEXT_DOMAIN ); ?>
					</caption>
					<thead>
						<tr>
							<th scope="col"><?php echo esc_html( $config['labels']['year'] ); ?></th>
							<th scope="col"><?php echo esc_html( $config['labels']['prinPaid'] ); ?></th>
							<th scope="col"><?php echo esc_html( $config['labels']['intPaid'] ); ?></th>
							<th scope="col"><?php echo esc_html( $config['labels']['balance'] ); ?></th>
						</tr>
					</thead>
					<tbody data-calcforge-schedule>
						<?php foreach ( $result['schedule'] as $row ) : ?>
							<tr>
								<td><?php echo esc_html( (string) $row['year'] ); ?></td>
								<td><?php echo esc_html( calcforge_format_amount( $row['principal'], $symbol, $decimals, $position ) ); ?></td>
								<td><?php echo esc_html( calcforge_format_amount( $row['interest'], $symbol, $decimals, $position ) ); ?></td>
								<td><?php echo esc_html( calcforge_format_amount( $row['balance'], $symbol, $decimals, $position ) ); ?></td>
							</tr>
			<?php endforeach; ?>

				<?php if ( $cost_fields ) : ?>
					<fieldset class="calcforge-calc__costs">
						<legend class="calcforge-calc__costs-legend">
							<?php esc_html_e( 'Taxes & Costs (annual)', CALCFORGE_TEXT_DOMAIN ); ?>
						</legend>
						<?php foreach ( $cost_fields as $cost_field ) : ?>
							<div class="calcforge-calc__field-group calcforge-calc__field-group--cost">
								<label class="calcforge-calc__label" for="<?php echo esc_attr( $cost_field['id'] ); ?>">
									<?php echo esc_html( $cost_field['label'] ); ?>
								</label>
								<div class="calcforge-calc__field-row">
									<input
										type="number"
										class="calcforge-calc__field"
										id="<?php echo esc_attr( $cost_field['id'] ); ?>"
										data-calcforge-field="<?php echo esc_attr( $cost_field['name'] ); ?>"
										value="<?php echo esc_attr( $cost_field['value'] ); ?>"
										step="<?php echo esc_attr( $cost_field['step'] ); ?>"
										min="<?php echo esc_attr( $cost_field['min'] ); ?>"
										inputmode="decimal"
									/>
									<button
										type="button"
										class="calcforge-calc__unit"
										data-calcforge-unit="<?php echo esc_attr( $cost_field['unitAttr'] ); ?>"
										aria-label="<?php
											/* translators: %s: cost component name. */
											printf( esc_attr__( 'Toggle %s between a percentage and an amount', 'calcforge' ), esc_attr( $cost_field['label'] ) );
										?>"
									>
										<?php echo esc_html( 'percent' === $cost_field['unit'] ? '%' : __( 'Amount', 'calcforge' ) ); ?>
									</button>
								</div>
							</div>
						<?php endforeach; ?>
					</fieldset>
				<?php endif; ?>

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
do_action( 'calcforge_after_calculator_render', $attributes );
