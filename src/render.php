<?php
/**
 * Server-side template for the Mortgage Calculator block.
 *
 * Rendered via MCB_Block_Registration::render(), which provides:
 *
 * @var array<string,mixed>  $attributes Raw block attributes (unused here; use $attrs).
 * @var array<string,mixed>  $attrs      Sanitized block attributes.
 * @var array<string,mixed>  $result     Calculation result from mcb_calculate().
 * @var string               $symbol     Resolved currency symbol.
 *
 * Every dynamic value is escaped on output. Results, sliders, and the
 * amortization schedule are fully functional without JavaScript; the view
 * script adds slider syncing, live recalculation, and the SVG charts.
 *
 * @package MortgageCalculatorBlock
 */

// Abort if this file is called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$decimals = absint( mcb_get_settings()['decimal_precision'] );
$uid      = function_exists( 'wp_unique_id' ) ? wp_unique_id( 'mcb-calc-' ) : uniqid( 'mcb-calc-' );
$position = isset( $attrs['currencyPosition'] ) ? (string) $attrs['currencyPosition'] : 'prefix';

$config = array(
	'decimals'         => $decimals,
	'symbol'           => $symbol,
	'position'         => $position,
	'showAmortization' => ! empty( $attrs['showAmortization'] ),
	'showCharts'       => ! empty( $attrs['showCharts'] ),
	'chartType'        => (string) $attrs['chartType'],
	'labels'           => array(
		'monthly'  => esc_html__( 'Monthly Payment', MCB_TEXT_DOMAIN ),
		'principal'=> esc_html__( 'Financed Principal', MCB_TEXT_DOMAIN ),
		'totalInt' => esc_html__( 'Total Interest', MCB_TEXT_DOMAIN ),
		'totalPaid'=> esc_html__( 'Total Paid', MCB_TEXT_DOMAIN ),
		'toggle'   => esc_html__( 'Collapse schedule', MCB_TEXT_DOMAIN ),
		'year'     => esc_html__( 'Year', MCB_TEXT_DOMAIN ),
		'prinPaid' => esc_html__( 'Principal Paid', MCB_TEXT_DOMAIN ),
		'intPaid'  => esc_html__( 'Interest Paid', MCB_TEXT_DOMAIN ),
		'balance'  => esc_html__( 'Remaining Balance', MCB_TEXT_DOMAIN ),
		'cumInt'   => esc_html__( 'Cumulative Interest', MCB_TEXT_DOMAIN ),
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
		'label' => esc_html__( 'Loan Amount', MCB_TEXT_DOMAIN ),
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
		'label' => esc_html__( 'Down Payment', MCB_TEXT_DOMAIN ),
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
		'label' => esc_html__( 'Interest Rate (%)', MCB_TEXT_DOMAIN ),
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
		'label' => esc_html__( 'Term (Years)', MCB_TEXT_DOMAIN ),
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
$style_vars = array();

$color_vars = array(
	'accentColor'          => '--mcb-accent',
	'accentAltColor'       => '--mcb-accent-2',
	'labelColor'           => '--mcb-label-color',
	'fieldTextColor'       => '--mcb-field-text',
	'fieldBackgroundColor' => '--mcb-field-bg',
	'fieldBorderColor'     => '--mcb-field-border',
);

foreach ( $color_vars as $attr_key => $css_var ) {
	if ( ! empty( $attrs[ $attr_key ] ) ) {
		$style_vars[] = $css_var . ':' . (string) $attrs[ $attr_key ];
	}
}

$font_stack = mcb_get_font_stack( isset( $attrs['fontFamily'] ) ? (string) $attrs['fontFamily'] : 'inherit' );

if ( '' !== $font_stack ) {
	$style_vars[] = 'font-family:' . $font_stack;
}

$wrapper_args = array(
	'class' => 'mcb-calc mcb-theme-' . esc_attr( $attrs['theme'] ),
);

if ( ! empty( $style_vars ) ) {
	$wrapper_args['style'] = implode( ';', $style_vars );
}
?>
<div
	<?php echo get_block_wrapper_attributes( $wrapper_args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() escapes internally. ?>
	data-mcb-config="<?php echo esc_attr( wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) ); ?>"
>
	<div class="mcb-calc__grid<?php echo empty( $attrs['showResults'] ) ? ' mcb-calc__grid--form-only' : ''; ?>">
		<form class="mcb-calc__form" autocomplete="off">
			<?php foreach ( $fields as $field ) : ?>
				<div class="mcb-calc__control">
					<label class="mcb-calc__label" for="<?php echo esc_attr( $field['id'] ); ?>">
						<?php echo esc_html( $field['label'] ); ?>
					</label>
					<div class="mcb-calc__control-row">
						<?php if ( ! empty( $attrs['showSliders'] ) ) : ?>
							<input
								type="range"
								class="mcb-calc__slider"
								data-mcb-slider="<?php echo esc_attr( $field['name'] ); ?>"
								value="<?php echo esc_attr( $field['value'] ); ?>"
								min="<?php echo esc_attr( $field['smin'] ); ?>"
								max="<?php echo esc_attr( $field['smax'] ); ?>"
								step="<?php echo esc_attr( $field['sstep'] ); ?>"
								aria-label="<?php echo esc_attr( $field['label'] ); ?>"
							/>
						<?php endif; ?>
						<input
							type="number"
							class="mcb-calc__field"
							id="<?php echo esc_attr( $field['id'] ); ?>"
							data-mcb-field="<?php echo esc_attr( $field['name'] ); ?>"
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

		<?php if ( ! empty( $attrs['showResults'] ) ) : ?>
			<div class="mcb-calc__results">
				<p class="mcb-calc__result-label" data-mcb-label="monthly">
					<?php echo esc_html( $config['labels']['monthly'] ); ?>
				</p>
				<p
					class="mcb-calc__result-primary"
					data-mcb-bind="monthlyPayment"
					<?php if ( '' !== $typography_attr ) : ?>
						style="<?php echo esc_attr( $typography_attr ); ?>"
					<?php endif; ?>
				>
					<?php echo esc_html( mcb_format_amount( $result['monthly_payment'], $symbol, $decimals, $position ) ); ?>
				</p>
				<dl class="mcb-calc__result-list">
					<div class="mcb-calc__result-row">
						<dt><?php echo esc_html( $config['labels']['principal'] ); ?></dt>
						<dd data-mcb-bind="principal">
							<?php echo esc_html( mcb_format_amount( $result['principal'], $symbol, $decimals, $position ) ); ?>
						</dd>
					</div>
					<div class="mcb-calc__result-row">
						<dt><?php echo esc_html( $config['labels']['totalInt'] ); ?></dt>
						<dd data-mcb-bind="totalInterest">
							<?php echo esc_html( mcb_format_amount( $result['total_interest'], $symbol, $decimals, $position ) ); ?>
						</dd>
					</div>
					<div class="mcb-calc__result-row">
						<dt><?php echo esc_html( $config['labels']['totalPaid'] ); ?></dt>
						<dd data-mcb-bind="totalPaid">
							<?php echo esc_html( mcb_format_amount( $result['total_paid'], $symbol, $decimals, $position ) ); ?>
						</dd>
					</div>
				</dl>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( ! empty( $attrs['showCharts'] ) ) : ?>
		<div class="mcb-calc__charts">
			<?php if ( in_array( $attrs['chartType'], array( 'donut', 'both' ), true ) ) : ?>
				<figure class="mcb-calc__chart">
					<figcaption class="mcb-calc__chart-title">
						<?php echo esc_html__( 'Payment Composition', MCB_TEXT_DOMAIN ); ?>
					</figcaption>
					<div class="mcb-calc__chart-body" data-mcb-chart="donut"></div>
					<figcaption class="mcb-calc__legend" data-mcb-legend="donut"></figcaption>
				</figure>
			<?php endif; ?>

			<?php if ( in_array( $attrs['chartType'], array( 'line', 'both' ), true ) ) : ?>
				<figure class="mcb-calc__chart">
					<figcaption class="mcb-calc__chart-title">
						<?php echo esc_html__( 'Balance Over Time', MCB_TEXT_DOMAIN ); ?>
					</figcaption>
					<div class="mcb-calc__chart-body" data-mcb-chart="line"></div>
					<figcaption class="mcb-calc__legend" data-mcb-legend="line"></figcaption>
				</figure>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $attrs['showAmortization'] ) && ! empty( $result['schedule'] ) ) : ?>
		<div class="mcb-calc__schedule">
			<button type="button" class="mcb-calc__toggle" aria-expanded="true">
				<?php echo esc_html( $config['labels']['toggle'] ); ?>
			</button>
			<table class="mcb-calc__table">
				<caption class="screen-reader-text">
					<?php echo esc_html__( 'Amortization Schedule', MCB_TEXT_DOMAIN ); ?>
				</caption>
				<thead>
					<tr>
						<th scope="col"><?php echo esc_html( $config['labels']['year'] ); ?></th>
						<th scope="col"><?php echo esc_html( $config['labels']['prinPaid'] ); ?></th>
						<th scope="col"><?php echo esc_html( $config['labels']['intPaid'] ); ?></th>
						<th scope="col"><?php echo esc_html( $config['labels']['balance'] ); ?></th>
					</tr>
				</thead>
				<tbody data-mcb-schedule>
					<?php foreach ( $result['schedule'] as $row ) : ?>
						<tr>
							<td><?php echo esc_html( (string) $row['year'] ); ?></td>
							<td><?php echo esc_html( mcb_format_amount( $row['principal'], $symbol, $decimals, $position ) ); ?></td>
							<td><?php echo esc_html( mcb_format_amount( $row['interest'], $symbol, $decimals, $position ) ); ?></td>
							<td><?php echo esc_html( mcb_format_amount( $row['balance'], $symbol, $decimals, $position ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>
