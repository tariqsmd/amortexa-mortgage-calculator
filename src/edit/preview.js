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
import { createDonutChart, createLineChart } from '../utils/charts';
import { NUMERIC_FIELDS } from './controls';

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
			dot.className = 'mcb-calc__legend-dot';
			dot.style.backgroundColor = item.color;
			span.className = 'mcb-calc__legend-item';
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
	} = attributes;

	const donutRef = useRef( null );
	const legendRef = useRef( null );
	const lineRef = useRef( null );
	const lineLegendRef = useRef( null );

	useEffect( () => {
		if ( ! rootRef.current ) {
			return;
		}

		const styles = window.getComputedStyle( rootRef.current );
		const palette = {
			accent:
				styles.getPropertyValue( '--mcb-accent' ).trim() || '#1a6f4b',
			accent2:
				styles.getPropertyValue( '--mcb-accent-2' ).trim() || '#d97706',
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
						centerTitle: __(
							'Monthly Payment',
							'mortgage-calculator-block'
						),
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

		renderLegendInto( legendRef.current, [
			{
				label: __( 'Financed Principal', 'mortgage-calculator-block' ),
				color: palette.accent,
			},
			{
				label: __( 'Total Interest', 'mortgage-calculator-block' ),
				color: palette.accent2,
			},
		] );
		renderLegendInto( lineLegendRef.current, [
			{
				label: __( 'Remaining Balance', 'mortgage-calculator-block' ),
				color: palette.accent,
			},
			{
				label: __( 'Cumulative Interest', 'mortgage-calculator-block' ),
				color: palette.accent2,
			},
		] );
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

	return (
		<>
			<p className="mcb-calc__editor-note">
				{ __(
					'Live preview — edit values in the Settings sidebar. Visitors get a fully interactive calculator.',
					'mortgage-calculator-block'
				) }
			</p>
			<div
				className={ `mcb-calc__grid${
					showResults ? '' : ' mcb-calc__grid--form-only'
				}` }
			>
				<form
					className="mcb-calc__form"
					onSubmit={ ( event ) => event.preventDefault() }
				>
					{ NUMERIC_FIELDS.map( ( field ) => (
						<div key={ field.key } className="mcb-calc__control">
							<label
								className="mcb-calc__label"
								htmlFor={ `mcb-edit-${ field.key }` }
							>
								{ field.label }
							</label>
							<div className="mcb-calc__control-row">
								{ showSliders && (
									<input
										type="range"
										className="mcb-calc__slider"
										value={ Number(
											attributes[ field.key ]
										) }
										min={ field.sliderMin }
										max={ sliderMaxFor( field ) }
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
									id={ `mcb-edit-${ field.key }` }
									className="mcb-calc__field"
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
				</form>

				{ showResults && (
					<div className="mcb-calc__results">
						<p className="mcb-calc__result-label">
							{ __(
								'Monthly Payment',
								'mortgage-calculator-block'
							) }
						</p>
						<p
							className="mcb-calc__result-primary"
							style={ paymentTypography }
						>
							{ formatAmount(
								result.monthlyPayment,
								currencySymbol,
								currencyPosition
							) }
						</p>
						<dl className="mcb-calc__result-list">
							<div className="mcb-calc__result-row">
								<dt>
									{ __(
										'Financed Principal',
										'mortgage-calculator-block'
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
							<div className="mcb-calc__result-row">
								<dt>
									{ __(
										'Total Interest',
										'mortgage-calculator-block'
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
							<div className="mcb-calc__result-row">
								<dt>
									{ __(
										'Total Paid',
										'mortgage-calculator-block'
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
					</div>
				) }
			</div>

			{ showCharts && (
				<div className="mcb-calc__charts">
					{ [ 'donut', 'both' ].includes( chartType ) && (
						<figure className="mcb-calc__chart">
							<figcaption className="mcb-calc__chart-title">
								{ __(
									'Payment Composition',
									'mortgage-calculator-block'
								) }
							</figcaption>
							<div
								className="mcb-calc__chart-body"
								ref={ donutRef }
							/>
							<figcaption
								className="mcb-calc__legend"
								ref={ legendRef }
							/>
						</figure>
					) }

					{ [ 'line', 'both' ].includes( chartType ) && (
						<figure className="mcb-calc__chart">
							<figcaption className="mcb-calc__chart-title">
								{ __(
									'Balance Over Time',
									'mortgage-calculator-block'
								) }
							</figcaption>
							<div
								className="mcb-calc__chart-body"
								ref={ lineRef }
							/>
							<figcaption
								className="mcb-calc__legend"
								ref={ lineLegendRef }
							/>
						</figure>
					) }
				</div>
			) }

			{ showAmortization && (
				<div className="mcb-calc__schedule">
					<table className="mcb-calc__table">
						<thead>
							<tr>
								<th scope="col">
									{ __(
										'Year',
										'mortgage-calculator-block'
									) }
								</th>
								<th scope="col">
									{ __(
										'Principal Paid',
										'mortgage-calculator-block'
									) }
								</th>
								<th scope="col">
									{ __(
										'Interest Paid',
										'mortgage-calculator-block'
									) }
								</th>
								<th scope="col">
									{ __(
										'Remaining Balance',
										'mortgage-calculator-block'
									) }
								</th>
							</tr>
						</thead>
						<tbody>{ scheduleRows }</tbody>
					</table>
				</div>
			) }
		</>
	);
}
