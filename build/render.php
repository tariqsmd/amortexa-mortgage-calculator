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
		'monthly'   => __( 'Monthly Payment', CALCFORGE_TEXT_DOMAIN ),
		'principal' => __( 'Financed Principal', CALCFORGE_TEXT_DOMAIN ),
		'totalInt'  => __( 'Total Interest', CALCFORGE_TEXT_DOMAIN ),
		'totalPaid' => __( 'Total Paid', CALCFORGE_TEXT_DOMAIN ),
		'toggle'    => __( 'Collapse schedule', CALCFORGE_TEXT_DOMAIN ),
		'year'      => __( 'Year', CALCFORGE_TEXT_DOMAIN ),
		'prinPaid'  => __( 'Principal Paid', CALCFORGE_TEXT_DOMAIN ),
		'intPaid'   => __( 'Interest Paid', CALCFORGE_TEXT_DOMAIN ),
		'balance'   => __( 'Remaining Balance', CALCFORGE_TEXT_DOMAIN ),
		'balanceY1' => __( 'Balance After Year 1', CALCFORGE_TEXT_DOMAIN ),
		'cumInt'    => __( 'Cumulative Interest', CALCFORGE_TEXT_DOMAIN ),
	),
);

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
 * Per-block palette overrides (accent, labels, fields) and font family.
 * Empty attribute values mean "use the active skin" and are skipped, so
 * skins keep working until a user explicitly overrides a color.
 */
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
				<button type="button" class="calcforge-calc__toggle" aria-expanded="true">
					<?php echo esc_html( $config['labels']['toggle'] ); ?>
				</button>
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
					</tbody>
				</table>
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
