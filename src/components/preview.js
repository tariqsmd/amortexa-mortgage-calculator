/**
 * Live preview for the Mortgage Calculator block.
 *
 * Renders the same structure/classes as the server-side template so editors
 * see a faithful representation, reusing the shared calculation and chart
 * modules. The preview is read-mostly: sliders re-bind attributes, but the
 * authoritative markup is always produced by render.php.
 */

import { __ } from '@wordpress/i18n';
import { useEffect, useRef } from '@wordpress/element';
import { getChartHeight } from '../utils/design';
import { getLayouts } from '../utils/editor-data';
import {
	createBarChart,
	createDotsChart,
	createDonutChart,
	createLineChart,
	withAlpha,
} from '../utils/charts';
import { round2 } from '../utils/calculator';
import {
	COST_FIELDS,
	NUMERIC_FIELDS,
	sliderBoundsFor,
} from '../utils/field-definitions';
import { resolvePanelOrder } from '../utils/panel-order';

/**
 * Formats an amount with a currency symbol placed before or after it.
 *
 * @param {number} amount   Amount to format.
 * @param {string} symbol   Currency symbol.
 * @param {string} position Symbol placement: 'prefix' or 'suffix'.
 * @return {string} Formatted amount such as "$1,234.56" or "1.234,56 €".
 */
export function formatAmount( amount, symbol, position = 'prefix' ) {
	const formatted = new Intl.NumberFormat( undefined, {
		minimumFractionDigits: 2,
		maximumFractionDigits: 2,
	} ).format( Number.isFinite( amount ) ? amount : 0 );

	return 'suffix' === position
		? `${ formatted }${ symbol }`
		: `${ symbol }${ formatted }`;
}

/**
 * Picks roughly six evenly spaced year labels for the line chart's X axis.
 *
 * @param {Array<Object>} schedule Annual schedule rows.
 * @return {Array<{at: number, text: string}>} Tick positions and labels.
 */
export function buildXTicks( schedule ) {
	const length = schedule.length;

	if ( ! length ) {
		return [];
	}

	const step = Math.max( 1, Math.ceil( length / 6 ) );
	const ticks = [];

	for ( let index = 0; index < length; index += step ) {
		ticks.push( { at: index, text: String( schedule[ index ].year ) } );
	}

	if ( ( length - 1 ) % step !== 0 ) {
		ticks.push( {
			at: length - 1,
			text: String( schedule[ length - 1 ].year ),
		} );
	}

	return ticks;
}

/**
 * Builds the legend items as plain DOM nodes (no markup injection).
 *
 * @param {HTMLElement} host  Target element to populate.
 * @param {Array}       items Legend items with { label, color }.
 */
function renderLegendInto( host, items ) {
	if ( ! host ) {
		return;
	}
	host.replaceChildren(
		...items.map( ( item ) => {
			const span = document.createElement( 'span' );
			const dot = document.createElement( 'span' );
			dot.className = 'amortexa-calc__legend-dot';
			dot.style.backgroundColor = item.color;
			span.className = 'amortexa-calc__legend-item';
			span.appendChild( dot );
			span.appendChild( document.createTextNode( item.label ) );
			return span;
		} )
	);
}

/**
 * Renders the live preview area of the block.
 *
 * @param {Object}   props                   Component props.
 * @param {Object}   props.attributes        Current attribute values.
 * @param {Function} props.setAttributes     Attribute updater.
 * @param {Object}   props.result            Full calculation result.
 * @param {Array}    props.chartSchedule     Annual schedule rows for charts.
 * @param {Object}   props.rootRef           Ref for the root preview node.
 * @param {Object}   props.paymentTypography Inline typography for the payment amount.
 * @param {string}   props.paletteKey        Serialised palette overrides; changes repaint charts.
 * @return {JSX.Element} Preview markup.
 */
export default function Preview( {
	attributes,
	setAttributes,
	result,
	chartSchedule,
	rootRef,
	paymentTypography,
	paletteKey,
} ) {
	const {
		currencySymbol,
		currencyPosition,
		showAmortization,
		showCharts,
		showSliders,
		showResults,
		showCosts,
		chartType,
		layout,
		panelOrder,
		theme,
	} = attributes;

	const donutRef = useRef( null );
	const legendRef = useRef( null );
	const lineRef = useRef( null );
	const lineLegendRef = useRef( null );
	const barRef = useRef( null );
	const barLegendRef = useRef( null );
	const dotsRef = useRef( null );
	const dotsLegendRef = useRef( null );

	// Charts are SVG, so the height control cannot arrive as a custom property.
	// Zero means the token is unset and the shared default applies, which keeps
	// the preview identical to the front end for a block with no overrides.
	const previewChartHeight = getChartHeight( attributes ) || 250;
	const previewDonutSize = Math.min(
		260,
		Math.round( previewChartHeight * 0.76 )
	);

	useEffect( () => {
		if ( ! rootRef.current ) {
			return;
		}

		const styles = window.getComputedStyle( rootRef.current );
		const palette = {
			accent:
				styles.getPropertyValue( '--amortexa-accent' ).trim() ||
				'#1a6f4b',
			accent2:
				styles.getPropertyValue( '--amortexa-accent-2' ).trim() ||
				'#d97706',
		};

		/*
		 * Mirrors buildCostSeries() in view.js so the editor shows the same donut
		 * the front end will: component slices once any recurring cost is entered,
		 * and the original principal/interest pair when none is.
		 */
		const costColors = {};
		for ( const key of [
			'pi',
			'tax',
			'insurance',
			'hoa',
			'pmi',
			'other',
		] ) {
			costColors[ key ] =
				styles.getPropertyValue( `--amortexa-cost-${ key }` ).trim() ||
				'#2563eb';
		}

		const previewCosts = result.monthlyCosts || {};
		const hasPreviewCosts = Object.values( previewCosts ).some(
			( value ) => ( Number( value ) || 0 ) > 0
		);

		const costSlices = hasPreviewCosts
			? [ 'pi', 'tax', 'insurance', 'hoa', 'pmi', 'other' ]
					.map( ( key ) => ( {
						key,
						value:
							'pi' === key
								? result.monthlyPayment
								: previewCosts[ key ] || 0,
					} ) )
					.filter( ( slice ) => slice.value > 0 )
					.map( ( slice ) => ( {
						value: slice.value,
						color: costColors[ slice.key ],
					} ) )
			: [
					{ value: result.principal, color: palette.accent },
					{ value: result.totalInterest, color: palette.accent2 },
				];

		if ( donutRef.current ) {
			donutRef.current.replaceChildren(
				createDonutChart( costSlices, {
					size: previewDonutSize,
					thickness: Math.max(
						12,
						Math.round( previewDonutSize * 0.135 )
					),
					centerTitle: hasPreviewCosts
						? __(
								'Total Monthly Cost',
								'amortexa-mortgage-calculator'
							)
						: __(
								'Monthly Payment',
								'amortexa-mortgage-calculator'
							),
					centerValue: formatAmount(
						hasPreviewCosts
							? result.totalMonthlyCost
							: result.monthlyPayment,
						currencySymbol,
						currencyPosition
					),
				} )
			);
		}

		let runningInterest = 0;
		const cumulativeInterest = chartSchedule.map( ( row ) => {
			runningInterest += row.interest;
			return Math.round( runningInterest );
		} );

		if ( lineRef.current ) {
			lineRef.current.replaceChildren(
				createLineChart(
					[
						{
							points: chartSchedule.map( ( row ) =>
								Math.round( row.balance )
							),
							color: palette.accent,
							area: true,
						},
						{ points: cumulativeInterest, color: palette.accent2 },
					],
					{
						width: 520,
						height: previewChartHeight,
						formatY: ( value ) =>
							formatAmount( value, '', currencyPosition ).replace(
								/\B(?=(\d{3})+(?!\d))/g,
								','
							),
						xLabels: buildXTicks( chartSchedule ),
					}
				)
			);
		}

		if ( barRef.current ) {
			barRef.current.replaceChildren(
				createBarChart(
					[
						{
							points: chartSchedule.map( ( row ) =>
								Math.round( row.principal )
							),
							color: palette.accent,
						},
						{
							points: chartSchedule.map( ( row ) =>
								Math.round( row.interest )
							),
							color: palette.accent2,
						},
					],
					{
						width: 520,
						height: previewChartHeight,
						formatY: ( value ) =>
							formatAmount( value, '', currencyPosition ).replace(
								/\B(?=(\d{3})+(?!\d))/g,
								','
							),
						xLabels: buildXTicks( chartSchedule ),
					}
				)
			);
		}

		renderLegendInto( legendRef.current, [
			{
				label: __(
					'Financed Principal',
					'amortexa-mortgage-calculator'
				),
				color: palette.accent,
			},
			{
				label: __( 'Total Interest', 'amortexa-mortgage-calculator' ),
				color: palette.accent2,
			},
		] );
		renderLegendInto( lineLegendRef.current, [
			{
				label: __(
					'Remaining Balance',
					'amortexa-mortgage-calculator'
				),
				color: palette.accent,
			},
			{
				label: __(
					'Cumulative Interest',
					'amortexa-mortgage-calculator'
				),
				color: palette.accent2,
			},
		] );
		renderLegendInto( barLegendRef.current, [
			{
				label: __( 'Principal Paid', 'amortexa-mortgage-calculator' ),
				color: palette.accent,
			},
			{
				label: __( 'Interest Paid', 'amortexa-mortgage-calculator' ),
				color: palette.accent2,
			},
		] );

		if ( dotsRef.current ) {
			/*
			 * Only loan level totals are plotted. The monthly payment is a rate
			 * rather than a total, so on a shared linear scale it would collapse
			 * onto zero and read as "nothing", which is misleading next to sums
			 * that are thousands of times larger. The balance is read after the
			 * first year, because at origination it would just repeat the
			 * principal and the terminal balance is always zero.
			 */
			const yearOneBalance = chartSchedule.length
				? chartSchedule[ 0 ].balance
				: result.principal;

			const series = [
				{
					value: result.principal,
					color: palette.accent,
					label: __(
						'Financed Principal',
						'amortexa-mortgage-calculator'
					),
				},
				{
					value: yearOneBalance,
					color: withAlpha( palette.accent, 0.5 ),
					label: __(
						'Balance After Year 1',
						'amortexa-mortgage-calculator'
					),
				},
				{
					value: result.totalInterest,
					color: palette.accent2,
					label: __(
						'Total Interest',
						'amortexa-mortgage-calculator'
					),
				},
				{
					value: result.totalPaid,
					color: withAlpha( palette.accent2, 0.5 ),
					label: __( 'Total Paid', 'amortexa-mortgage-calculator' ),
				},
			];

			dotsRef.current.replaceChildren(
				createDotsChart( series, {
					width: 520,
					height: Math.round( previewChartHeight * 0.76 ),
					formatY: ( value ) =>
						formatAmount( value, '', currencyPosition ).replace(
							/\B(?=(\d{3})+(?!\d))/g,
							','
						),
				} )
			);

			renderLegendInto(
				dotsLegendRef.current,
				series.map( ( item ) => ( {
					label: item.label,
					color: item.color,
				} ) )
			);
		}
		// chartType and showCharts are dependencies because they mount and
		// unmount the chart <figure> elements: changing the chart type, or
		// hiding the charts panel and bringing it back, attaches a fresh, empty
		// chart body that nothing repaints unless this effect re-runs. result
		// and chartSchedule are memoised on the loan inputs, so they hold their
		// identity across a chart type change and cannot stand in for it.
		// previewChartHeight likewise has to be listed, or resizing a chart
		// from the Design tab would leave the old drawing in place.
		// theme and paletteKey are what actually change the computed accent and
		// cost custom properties, so without them switching a skin (or editing a
		// colour token) in the editor would leave the old chart colours behind.
		// rootRef is a ref, so its identity is stable: listing it satisfies
		// react-hooks/exhaustive-deps without ever forcing a re-run.
	}, [
		result,
		chartSchedule,
		currencySymbol,
		currencyPosition,
		chartType,
		showCharts,
		previewChartHeight,
		previewDonutSize,
		theme,
		paletteKey,
		rootRef,
	] );

	const setNumericAttribute = ( key, raw ) => {
		setAttributes( {
			[ key ]: raw === '' ? 0 : parseFloat( raw ) || 0,
		} );
	};

	const sliderBounds = ( field ) => {
		/*
		 * A derived max is a constraint, not a display bound, so it is passed
		 * through untouched and the editor keeps enforcing the same rule the
		 * front end does in clampDownPayment(). Everything else gets the track
		 * widened to cover the current value, so the canvas thumb and the input
		 * cannot show two different numbers here either.
		 */
		const derived = field.sliderMaxFrom
			? Math.max( Number( attributes[ field.sliderMaxFrom ] ) || 0, 1 )
			: undefined;

		return sliderBoundsFor( field, attributes[ field.key ], derived );
	};

	/*
	 * Converts a cost value when its unit changes so the monthly figure stays
	 * the same, mirroring the front-end unit toggle. A percentage is a share of
	 * the purchase price (loanAmount here); a cash amount is the figure as is.
	 */
	const setCostUnit = ( attribute ) => {
		const unitKey = `${ attribute }Unit`;
		const current =
			'amount' === attributes[ unitKey ] ? 'amount' : 'percent';
		const next = 'amount' === current ? 'percent' : 'amount';
		const raw = Number( attributes[ attribute ] ) || 0;
		const price = Number( attributes.loanAmount ) || 0;
		let value = raw;

		if ( price > 0 ) {
			value =
				'amount' === next
					? round2( ( price * raw ) / 100 )
					: round2( ( raw / price ) * 100 );
		}

		setAttributes( { [ attribute ]: value, [ unitKey ]: next } );
	};

	const scheduleRows = chartSchedule.map( ( row ) => (
		<tr key={ row.year }>
			<td>{ row.year }</td>
			<td>
				{ formatAmount(
					row.principal,
					currencySymbol,
					currencyPosition
				) }
			</td>
			<td>
				{ formatAmount(
					row.interest,
					currencySymbol,
					currencyPosition
				) }
			</td>
			<td>
				{ formatAmount(
					row.balance,
					currencySymbol,
					currencyPosition
				) }
			</td>
		</tr>
	) );

	/*
	 * The cost breakdown mirrors the server render: principal and interest
	 * always leads, and a component the author left at zero is dropped rather
	 * than shown as a $0.00 row.
	 */
	const monthlyCosts = result.monthlyCosts || {};
	const costRows = [
		{
			key: 'pi',
			label: __( 'Principal & Interest', 'amortexa-mortgage-calculator' ),
			amount: result.monthlyPayment,
		},
	];

	COST_FIELDS.forEach( ( component ) => {
		const amount = Number( monthlyCosts[ component.key ] ) || 0;

		if ( amount > 0 ) {
			costRows.push( {
				key: component.key,
				label: component.label,
				amount,
			} );
		}
	} );

	// Which panels render, and in what order, so the preview matches the front
	// end exactly. The form is part of the reorderable set, so it appears in
	// the ordered list rather than being pinned first.
	const visiblePanels = [ 'form' ];

	if ( showResults ) {
		visiblePanels.push( 'results' );
	}

	if ( showCharts ) {
		visiblePanels.push( 'charts' );
	}

	if ( showAmortization ) {
		visiblePanels.push( 'schedule' );
	}

	const orderedPanels = resolvePanelOrder( panelOrder, visiblePanels );

	/*
	 * The grid always carries the layout as a modifier, stacked included,
	 * because the form styling hangs off it. An unknown value falls back to
	 * stacked, which is what the server side sanitizer does too.
	 */
	const layoutClass = getLayouts().some(
		( option ) => option.value === layout
	)
		? layout
		: 'stacked';

	/*
	 * Mirrors the column grouping the block renders on the front end: `aside`
	 * puts the inputs alone in the first column and the results with the charts
	 * in the second, `chart-aside` puts the inputs with the results in the first
	 * and the charts in the second, and the amortization table stays outside
	 * both columns in every layout. Saved panel order decides the sequence
	 * inside each column.
	 */
	const bodyPanels = orderedPanels.filter(
		( panel ) => 'form' !== panel && 'schedule' !== panel
	);
	let columns = [];

	if ( 'aside' === layoutClass ) {
		columns = [ [ 'form' ], bodyPanels ];
	} else if ( 'chart-aside' === layoutClass ) {
		columns = [
			[
				'form',
				...bodyPanels.filter( ( panel ) => 'results' === panel ),
			],
			bodyPanels.filter( ( panel ) => 'charts' === panel ),
		];
	}

	const filledColumns = columns.filter( ( column ) => 0 < column.length );
	const groupedPanels = filledColumns.flat();

	/*
	 * Columns are only printed when there is more than one of them: a layout
	 * that has collapsed to a single column renders flat, exactly as stacked
	 * does. The table is then appended after both columns, which is what keeps
	 * it full width instead of inside one of them.
	 */
	const multiColumn = 1 < filledColumns.length;

	let singleColumn = groupedPanels.length <= 1;

	if ( 0 === columns.length ) {
		singleColumn = ! showResults;
	}
	const gridClass = `amortexa-calc__grid amortexa-calc__grid--${ layoutClass }${
		singleColumn ? ' amortexa-calc__grid--form-only' : ''
	}`;

	/*
	 * In the two column layouts the panels are grouped into the columns the grid
	 * lays out. Every other layout renders the same panels flat, so the column
	 * wrappers stay out of the markup and only the grid class changes.
	 */
	const renderPanel = ( panel ) => {
		if ( 'form' === panel ) {
			return (
				<form
					key="form"
					className="amortexa-calc__form"
					onSubmit={ ( event ) => event.preventDefault() }
				>
					{ NUMERIC_FIELDS.map( ( field ) => (
						<div
							key={ field.key }
							className="amortexa-calc__control"
						>
							<label
								className="amortexa-calc__label"
								htmlFor={ `amortexa-edit-${ field.key }` }
							>
								{ field.label }
							</label>
							<div className="amortexa-calc__control-row">
								{ showSliders && (
									<input
										type="range"
										className="amortexa-calc__slider"
										value={ Number(
											attributes[ field.key ]
										) }
										min={ sliderBounds( field ).min }
										max={ sliderBounds( field ).max }
										step={ field.sliderStep }
										tabIndex={ -1 }
										aria-label={ field.label }
										onChange={ ( event ) =>
											setNumericAttribute(
												field.key,
												event.target.value
											)
										}
									/>
								) }
								<input
									type="number"
									id={ `amortexa-edit-${ field.key }` }
									className="amortexa-calc__field"
									value={ String(
										attributes[ field.key ] ?? ''
									) }
									min={ field.min }
									max={ field.max }
									step={ field.step }
									tabIndex={ -1 }
									onChange={ ( event ) =>
										setNumericAttribute(
											field.key,
											event.target.value
										)
									}
								/>
							</div>
						</div>
					) ) }

					{ showCosts && (
						<fieldset className="amortexa-calc__costs">
							<legend className="amortexa-calc__costs-legend">
								{ __(
									'Taxes & Costs (annual)',
									'amortexa-mortgage-calculator'
								) }
							</legend>
							{ COST_FIELDS.map( ( component ) => {
								const unit =
									'amount' ===
									attributes[ `${ component.attribute }Unit` ]
										? 'amount'
										: 'percent';

								return (
									<div
										key={ component.key }
										className="amortexa-calc__control"
									>
										<label
											className="amortexa-calc__label"
											htmlFor={ `amortexa-edit-${ component.attribute }` }
										>
											{ component.label }
										</label>
										<div className="amortexa-calc__control-row">
											<input
												type="number"
												id={ `amortexa-edit-${ component.attribute }` }
												className="amortexa-calc__field"
												value={ String(
													attributes[
														component.attribute
													] ?? ''
												) }
												step={
													'amount' === unit
														? 'any'
														: '0.01'
												}
												min="0"
												tabIndex={ -1 }
												onChange={ ( event ) =>
													setNumericAttribute(
														component.attribute,
														event.target.value
													)
												}
											/>
											<button
												type="button"
												className="amortexa-calc__unit"
												tabIndex={ -1 }
												onClick={ () =>
													setCostUnit(
														component.attribute
													)
												}
											>
												{ 'percent' === unit
													? '%'
													: __(
															'Amount',
															'amortexa-mortgage-calculator'
														) }
											</button>
										</div>
									</div>
								);
							} ) }
						</fieldset>
					) }
				</form>
			);
		}

		if ( 'results' === panel ) {
			return (
				<div key="results" className="amortexa-calc__results">
					<p className="amortexa-calc__result-label">
						{ __(
							'Monthly Payment',
							'amortexa-mortgage-calculator'
						) }
					</p>
					<p
						className="amortexa-calc__result-primary"
						style={ paymentTypography }
					>
						{ formatAmount(
							result.monthlyPayment,
							currencySymbol,
							currencyPosition
						) }
					</p>
					<dl className="amortexa-calc__result-list">
						<div className="amortexa-calc__result-row">
							<dt>
								{ __(
									'Financed Principal',
									'amortexa-mortgage-calculator'
								) }
							</dt>
							<dd>
								{ formatAmount(
									result.principal,
									currencySymbol,
									currencyPosition
								) }
							</dd>
						</div>
						<div className="amortexa-calc__result-row">
							<dt>
								{ __(
									'Total Interest',
									'amortexa-mortgage-calculator'
								) }
							</dt>
							<dd>
								{ formatAmount(
									result.totalInterest,
									currencySymbol,
									currencyPosition
								) }
							</dd>
						</div>
						<div className="amortexa-calc__result-row">
							<dt>
								{ __(
									'Total Paid',
									'amortexa-mortgage-calculator'
								) }
							</dt>
							<dd>
								{ formatAmount(
									result.totalPaid,
									currencySymbol,
									currencyPosition
								) }
							</dd>
						</div>
					</dl>

					{ showCosts && (
						<>
							<dl className="amortexa-calc__result-list amortexa-calc__result-list--costs">
								{ costRows.map( ( row ) => (
									<div
										key={ row.key }
										className="amortexa-calc__result-row amortexa-calc__result-row--cost"
										data-amortexa-cost={ row.key }
									>
										<dt>
											<span
												className="amortexa-calc__cost-swatch"
												aria-hidden="true"
											></span>
											{ row.label }
										</dt>
										<dd>
											{ formatAmount(
												row.amount,
												currencySymbol,
												currencyPosition
											) }
										</dd>
									</div>
								) ) }
								<div className="amortexa-calc__result-row amortexa-calc__result-row--total">
									<dt>
										{ __(
											'Total Monthly Cost',
											'amortexa-mortgage-calculator'
										) }
									</dt>
									<dd>
										{ formatAmount(
											result.totalMonthlyCost,
											currencySymbol,
											currencyPosition
										) }
									</dd>
								</div>
							</dl>

							<dl className="amortexa-calc__result-list amortexa-calc__result-list--totals">
								<div className="amortexa-calc__result-row">
									<dt>
										{ __(
											'Total Taxes & Costs',
											'amortexa-mortgage-calculator'
										) }
									</dt>
									<dd>
										{ formatAmount(
											result.totalCosts,
											currencySymbol,
											currencyPosition
										) }
									</dd>
								</div>
								<div className="amortexa-calc__result-row">
									<dt>
										{ __(
											'Total Out-of-Pocket',
											'amortexa-mortgage-calculator'
										) }
									</dt>
									<dd>
										{ formatAmount(
											result.totalOutOfPocket,
											currencySymbol,
											currencyPosition
										) }
									</dd>
								</div>
							</dl>
						</>
					) }
				</div>
			);
		}

		if ( 'charts' === panel ) {
			return (
				<div className="amortexa-calc__charts" key="charts">
					{ [ 'donut', 'both' ].includes( chartType ) && (
						<figure className="amortexa-calc__chart">
							<figcaption className="amortexa-calc__chart-title">
								{ __(
									'Payment Composition',
									'amortexa-mortgage-calculator'
								) }
							</figcaption>
							<div
								className="amortexa-calc__chart-body"
								ref={ donutRef }
							/>
							<figcaption
								className="amortexa-calc__legend"
								ref={ legendRef }
							/>
						</figure>
					) }

					{ [ 'line', 'both' ].includes( chartType ) && (
						<figure className="amortexa-calc__chart">
							<figcaption className="amortexa-calc__chart-title">
								{ __(
									'Balance Over Time',
									'amortexa-mortgage-calculator'
								) }
							</figcaption>
							<div
								className="amortexa-calc__chart-body"
								ref={ lineRef }
							/>
							<figcaption
								className="amortexa-calc__legend"
								ref={ lineLegendRef }
							/>
						</figure>
					) }

					{ chartType === 'bar' && (
						<figure className="amortexa-calc__chart">
							<figcaption className="amortexa-calc__chart-title">
								{ __(
									'Principal vs Interest by Year',
									'amortexa-mortgage-calculator'
								) }
							</figcaption>
							<div
								className="amortexa-calc__chart-body"
								ref={ barRef }
							/>
							<figcaption
								className="amortexa-calc__legend"
								ref={ barLegendRef }
							/>
						</figure>
					) }

					{ chartType === 'dots' && (
						<figure className="amortexa-calc__chart">
							<figcaption className="amortexa-calc__chart-title">
								{ __(
									'Parameter Comparison',
									'amortexa-mortgage-calculator'
								) }
							</figcaption>
							<div
								className="amortexa-calc__chart-body"
								ref={ dotsRef }
							/>
							<figcaption
								className="amortexa-calc__legend"
								ref={ dotsLegendRef }
							/>
						</figure>
					) }
				</div>
			);
		}

		return (
			<div key="schedule" className="amortexa-calc__schedule">
				{ /* Mirrors the schedule-body wrapper in render.php so
				 * the table scrolls horizontally in a narrow
				 * editor viewport, exactly as it does on the
				 * frontend. */ }
				<div className="amortexa-calc__schedule-body">
					<table className="amortexa-calc__table">
						<thead>
							<tr>
								<th scope="col">
									{ __(
										'Year',
										'amortexa-mortgage-calculator'
									) }
								</th>
								<th scope="col">
									{ __(
										'Principal Paid',
										'amortexa-mortgage-calculator'
									) }
								</th>
								<th scope="col">
									{ __(
										'Interest Paid',
										'amortexa-mortgage-calculator'
									) }
								</th>
								<th scope="col">
									{ __(
										'Remaining Balance',
										'amortexa-mortgage-calculator'
									) }
								</th>
							</tr>
						</thead>
						<tbody>{ scheduleRows }</tbody>
					</table>
				</div>
			</div>
		);
	};

	return (
		<>
			<p className="amortexa-calc__editor-note">
				{ __(
					'Use sidebar settings to change values.',
					'amortexa-mortgage-calculator'
				) }
			</p>
			<div className={ gridClass }>
				{ multiColumn
					? filledColumns.map( ( column, index ) => (
							<div
								key={ `column-${ index }` }
								className={ `amortexa-calc__column amortexa-calc__column--${
									0 === index ? 'form' : 'details'
								}` }
							>
								{ column.map( ( panel ) =>
									renderPanel( panel )
								) }
							</div>
						) )
					: orderedPanels.map( ( panel ) => renderPanel( panel ) ) }
				{ multiColumn &&
					orderedPanels.includes( 'schedule' ) &&
					renderPanel( 'schedule' ) }
			</div>
		</>
	);
}
