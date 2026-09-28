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
import {
	createBarChart,
	createDotsChart,
	createDonutChart,
	createLineChart,
	withAlpha,
} from '../utils/charts';
import { NUMERIC_FIELDS } from '../utils/field-definitions';
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
			dot.className = 'calcforge-calc__legend-dot';
			dot.style.backgroundColor = item.color;
			span.className = 'calcforge-calc__legend-item';
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
 * @return {JSX.Element} Preview markup.
 */
export default function Preview( {
	attributes,
	setAttributes,
	result,
	chartSchedule,
	rootRef,
	paymentTypography,
} ) {
	const {
		currencySymbol,
		currencyPosition,
		showAmortization,
		showCharts,
		showSliders,
		showResults,
		chartType,
		layout,
		panelOrder,
	} = attributes;

	const donutRef = useRef( null );
	const legendRef = useRef( null );
	const lineRef = useRef( null );
	const lineLegendRef = useRef( null );
	const barRef = useRef( null );
	const barLegendRef = useRef( null );
	const dotsRef = useRef( null );
	const dotsLegendRef = useRef( null );

	useEffect( () => {
		if ( ! rootRef.current ) {
			return;
		}

		const styles = window.getComputedStyle( rootRef.current );
		const palette = {
			accent:
				styles.getPropertyValue( '--calcforge-accent' ).trim() ||
				'#1a6f4b',
			accent2:
				styles.getPropertyValue( '--calcforge-accent-2' ).trim() ||
				'#d97706',
		};

		if ( donutRef.current ) {
			donutRef.current.replaceChildren(
				createDonutChart(
					[
						{ value: result.principal, color: palette.accent },
						{ value: result.totalInterest, color: palette.accent2 },
					],
					{
						size: 190,
						thickness: 26,
						centerTitle: __( 'Monthly Payment', 'calcforge' ),
						centerValue: formatAmount(
							result.monthlyPayment,
							currencySymbol,
							currencyPosition
						),
					}
				)
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
						height: 250,
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
						height: 250,
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
				label: __( 'Financed Principal', 'calcforge' ),
				color: palette.accent,
			},
			{
				label: __( 'Total Interest', 'calcforge' ),
				color: palette.accent2,
			},
		] );
		renderLegendInto( lineLegendRef.current, [
			{
				label: __( 'Remaining Balance', 'calcforge' ),
				color: palette.accent,
			},
			{
				label: __( 'Cumulative Interest', 'calcforge' ),
				color: palette.accent2,
			},
		] );
		renderLegendInto( barLegendRef.current, [
			{
				label: __( 'Principal Paid', 'calcforge' ),
				color: palette.accent,
			},
			{
				label: __( 'Interest Paid', 'calcforge' ),
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
					label: __( 'Financed Principal', 'calcforge' ),
				},
				{
					value: yearOneBalance,
					color: withAlpha( palette.accent, 0.5 ),
					label: __( 'Balance After Year 1', 'calcforge' ),
				},
				{
					value: result.totalInterest,
					color: palette.accent2,
					label: __( 'Total Interest', 'calcforge' ),
				},
				{
					value: result.totalPaid,
					color: withAlpha( palette.accent2, 0.5 ),
					label: __( 'Total Paid', 'calcforge' ),
				},
			];

			dotsRef.current.replaceChildren(
				createDotsChart( series, {
					width: 520,
					height: 190,
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
	}, [ result, chartSchedule, currencySymbol, currencyPosition, rootRef ] );

	const setNumericAttribute = ( key, raw ) => {
		setAttributes( {
			[ key ]: raw === '' ? 0 : parseFloat( raw ) || 0,
		} );
	};

	const sliderMaxFor = ( field ) => {
		if ( field.sliderMaxFrom ) {
			return Math.max(
				Number( attributes[ field.sliderMaxFrom ] ) || 0,
				1
			);
		}
		return field.sliderMax;
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

	return (
		<>
			<p className="calcforge-calc__editor-note">
				{ __(
					'Live preview — edit values in the Settings sidebar. Visitors get a fully interactive calculator.',
					'calcforge'
				) }
			</p>
			<div
				className={ `calcforge-calc__grid calcforge-calc__grid--${
					layout === 'split' ? 'split' : 'stacked'
				}${ showResults ? '' : ' calcforge-calc__grid--form-only' }` }
			>
				{ orderedPanels.map( ( panel ) => {
					if ( 'form' === panel ) {
						return (
							<form
								key="form"
								className="calcforge-calc__form"
								onSubmit={ ( event ) => event.preventDefault() }
							>
								{ NUMERIC_FIELDS.map( ( field ) => (
									<div
										key={ field.key }
										className="calcforge-calc__control"
									>
										<label
											className="calcforge-calc__label"
											htmlFor={ `calcforge-edit-${ field.key }` }
										>
											{ field.label }
										</label>
										<div className="calcforge-calc__control-row">
											{ showSliders && (
												<input
													type="range"
													className="calcforge-calc__slider"
													value={ Number(
														attributes[ field.key ]
													) }
													min={ field.sliderMin }
													max={ sliderMaxFor(
														field
													) }
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
												id={ `calcforge-edit-${ field.key }` }
												className="calcforge-calc__field"
												value={ String(
													attributes[ field.key ] ??
														''
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
							</form>
						);
					}

					if ( 'results' === panel ) {
						return (
							<div
								key="results"
								className="calcforge-calc__results"
							>
								<p className="calcforge-calc__result-label">
									{ __( 'Monthly Payment', 'calcforge' ) }
								</p>
								<p
									className="calcforge-calc__result-primary"
									style={ paymentTypography }
								>
									{ formatAmount(
										result.monthlyPayment,
										currencySymbol,
										currencyPosition
									) }
								</p>
								<dl className="calcforge-calc__result-list">
									<div className="calcforge-calc__result-row">
										<dt>
											{ __(
												'Financed Principal',
												'calcforge'
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
									<div className="calcforge-calc__result-row">
										<dt>
											{ __(
												'Total Interest',
												'calcforge'
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
									<div className="calcforge-calc__result-row">
										<dt>
											{ __( 'Total Paid', 'calcforge' ) }
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
							</div>
						);
					}

					if ( 'charts' === panel ) {
						return (
							<div
								className="calcforge-calc__charts"
								key="charts"
							>
								{ [ 'donut', 'both' ].includes( chartType ) && (
									<figure className="calcforge-calc__chart">
										<figcaption className="calcforge-calc__chart-title">
											{ __(
												'Payment Composition',
												'calcforge'
											) }
										</figcaption>
										<div
											className="calcforge-calc__chart-body"
											ref={ donutRef }
										/>
										<figcaption
											className="calcforge-calc__legend"
											ref={ legendRef }
										/>
									</figure>
								) }

								{ [ 'line', 'both' ].includes( chartType ) && (
									<figure className="calcforge-calc__chart">
										<figcaption className="calcforge-calc__chart-title">
											{ __(
												'Balance Over Time',
												'calcforge'
											) }
										</figcaption>
										<div
											className="calcforge-calc__chart-body"
											ref={ lineRef }
										/>
										<figcaption
											className="calcforge-calc__legend"
											ref={ lineLegendRef }
										/>
									</figure>
								) }

								{ chartType === 'bar' && (
									<figure className="calcforge-calc__chart">
										<figcaption className="calcforge-calc__chart-title">
											{ __(
												'Principal vs Interest by Year',
												'calcforge'
											) }
										</figcaption>
										<div
											className="calcforge-calc__chart-body"
											ref={ barRef }
										/>
										<figcaption
											className="calcforge-calc__legend"
											ref={ barLegendRef }
										/>
									</figure>
								) }

								{ chartType === 'dots' && (
									<figure className="calcforge-calc__chart">
										<figcaption className="calcforge-calc__chart-title">
											{ __(
												'Parameter Comparison',
												'calcforge'
											) }
										</figcaption>
										<div
											className="calcforge-calc__chart-body"
											ref={ dotsRef }
										/>
										<figcaption
											className="calcforge-calc__legend"
											ref={ dotsLegendRef }
										/>
									</figure>
								) }
							</div>
						);
					}

					return (
						<div
							key="schedule"
							className="calcforge-calc__schedule"
						>
							<table className="calcforge-calc__table">
								<thead>
									<tr>
										<th scope="col">
											{ __( 'Year', 'calcforge' ) }
										</th>
										<th scope="col">
											{ __(
												'Principal Paid',
												'calcforge'
											) }
										</th>
										<th scope="col">
											{ __(
												'Interest Paid',
												'calcforge'
											) }
										</th>
										<th scope="col">
											{ __(
												'Remaining Balance',
												'calcforge'
											) }
										</th>
									</tr>
								</thead>
								<tbody>{ scheduleRows }</tbody>
							</table>
						</div>
					);
				} ) }
			</div>
		</>
	);
}
