/**
 * Editor component for the Mortgage Calculator block.
 *
 * Provides inspector controls for every attribute plus a live preview that
 * reuses the same calculation/chart modules and CSS classes as the front
 * end, so what editors see matches the rendered output.
 */

import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { useEffect, useMemo, useRef } from '@wordpress/element';
import './style.scss';
import './editor.scss';
import {
	buildAmortizationSchedule,
	calculateMortgage,
} from './utils/calculator';
import { createDonutChart, createLineChart } from './utils/charts';

const SKINS = [
	{
		value: 'light',
		label: __( 'Classic Light', 'mortgage-calculator-block' ),
	},
	{ value: 'dark', label: __( 'Elegant Dark', 'mortgage-calculator-block' ) },
	{ value: 'ocean', label: __( 'Ocean Blue', 'mortgage-calculator-block' ) },
	{
		value: 'sunset',
		label: __( 'Sunset Warm', 'mortgage-calculator-block' ),
	},
	{
		value: 'forest',
		label: __( 'Forest Green', 'mortgage-calculator-block' ),
	},
];

const NUMERIC_FIELDS = [
	{
		key: 'loanAmount',
		label: __( 'Loan Amount', 'mortgage-calculator-block' ),
		min: 0,
		max: undefined,
		step: 'any',
		sliderMin: 10000,
		sliderMax: 2000000,
		sliderStep: 5000,
	},
	{
		key: 'downPayment',
		label: __( 'Down Payment', 'mortgage-calculator-block' ),
		min: 0,
		max: undefined,
		step: 'any',
		sliderMin: 0,
		sliderMaxFrom: 'loanAmount',
		sliderStep: 2500,
	},
	{
		key: 'interestRate',
		label: __( 'Interest Rate (%)', 'mortgage-calculator-block' ),
		min: 0,
		max: 100,
		step: '0.01',
		sliderMin: 0,
		sliderMax: 20,
		sliderStep: 0.05,
	},
	{
		key: 'loanTerm',
		label: __( 'Term (Years)', 'mortgage-calculator-block' ),
		min: 1,
		max: 60,
		step: 1,
		sliderMin: 1,
		sliderMax: 40,
		sliderStep: 1,
	},
];

/**
 * Formats an amount with a currency symbol prefix.
 *
 * @param {number} amount Amount to format.
 * @param {string} symbol Currency symbol.
 * @return {string} Formatted amount such as "$1,234.56".
 */
function formatAmount( amount, symbol ) {
	const formatted = new Intl.NumberFormat( undefined, {
		minimumFractionDigits: 2,
		maximumFractionDigits: 2,
	} ).format( Number.isFinite( amount ) ? amount : 0 );

	return `${ symbol }${ formatted }`;
}

/**
 * Picks roughly six evenly spaced year labels for the line chart's X axis.
 *
 * @param {Array<Object>} schedule Annual schedule rows.
 * @return {Array<{at: number, text: string}>} Tick positions and labels.
 */
function buildXTicks( schedule ) {
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
 * Renders the block edit UI.
 *
 * @param {Object}   props               Component props.
 * @param {Object}   props.attributes    Current attribute values.
 * @param {Function} props.setAttributes Attribute updater.
 * @return {JSX.Element} Block edit interface.
 */
export default function Edit( { attributes, setAttributes } ) {
	const {
		loanAmount,
		interestRate,
		loanTerm,
		downPayment,
		currencySymbol,
		showAmortization,
		theme,
	} = attributes;

	const result = useMemo(
		() =>
			calculateMortgage( {
				loanAmount,
				downPayment,
				interestRate,
				loanTerm,
			} ),
		[ loanAmount, downPayment, interestRate, loanTerm ]
	);

	const chartSchedule = useMemo(
		() =>
			buildAmortizationSchedule(
				result.principal,
				interestRate,
				loanTerm
			),
		[ result.principal, interestRate, loanTerm ]
	);

	const blockProps = useBlockProps( {
		className: `mcb-calc mcb-theme-${
			SKINS.some( ( skin ) => skin.value === theme ) ? theme : 'light'
		}`,
	} );

	const donutRef = useRef( null );
	const legendRef = useRef( null );
	const lineRef = useRef( null );
	const lineLegendRef = useRef( null );
	const rootRef = useRef( null );

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
							currencySymbol
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
							formatAmount( value, '' ).replace(
								/\B(?=(\d{3})+(?!\d))/g,
								','
							),
						xLabels: buildXTicks( chartSchedule ),
					}
				)
			);
		}

		const renderLegendInto = ( host, items ) => {
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
		};

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
	}, [ result, chartSchedule, currencySymbol ] );

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
			<td>{ formatAmount( row.principal, currencySymbol ) }</td>
			<td>{ formatAmount( row.interest, currencySymbol ) }</td>
			<td>{ formatAmount( row.balance, currencySymbol ) }</td>
		</tr>
	) );

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __(
						'Calculator Settings',
						'mortgage-calculator-block'
					) }
				>
					{ NUMERIC_FIELDS.map( ( field ) => (
						<TextControl
							key={ field.key }
							type="number"
							label={ field.label }
							value={ String( attributes[ field.key ] ?? '' ) }
							min={ field.min }
							max={ field.max }
							step={ field.step }
							onChange={ ( value ) =>
								setNumericAttribute( field.key, value )
							}
						/>
					) ) }
					<TextControl
						label={ __(
							'Currency Symbol',
							'mortgage-calculator-block'
						) }
						value={ currencySymbol }
						maxLength={ 8 }
						onChange={ ( value ) =>
							setAttributes( {
								currencySymbol: value.slice( 0, 8 ),
							} )
						}
					/>
					<ToggleControl
						label={ __(
							'Show Amortization Table',
							'mortgage-calculator-block'
						) }
						checked={ showAmortization }
						onChange={ ( value ) =>
							setAttributes( { showAmortization: value } )
						}
					/>
					<SelectControl
						label={ __( 'Skin', 'mortgage-calculator-block' ) }
						value={
							SKINS.some( ( skin ) => skin.value === theme )
								? theme
								: 'light'
						}
						options={ SKINS }
						onChange={ ( value ) =>
							setAttributes( { theme: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps } ref={ rootRef }>
				<p className="mcb-calc__editor-note">
					{ __(
						'Live preview — visitors will see this calculator rendered by the server.',
						'mortgage-calculator-block'
					) }
				</p>
				<div className="mcb-calc__grid">
					<form
						className="mcb-calc__form"
						onSubmit={ ( event ) => event.preventDefault() }
					>
						{ NUMERIC_FIELDS.map( ( field ) => (
							<div
								key={ field.key }
								className="mcb-calc__control"
							>
								<label
									className="mcb-calc__label"
									htmlFor={ `mcb-edit-${ field.key }` }
								>
									{ field.label }
								</label>
								<div className="mcb-calc__control-row">
									<input
										type="range"
										className="mcb-calc__slider"
										value={ Number(
											attributes[ field.key ]
										) }
										min={ field.sliderMin }
										max={ sliderMaxFor( field ) }
										step={ field.sliderStep }
										aria-label={ field.label }
										onChange={ ( event ) =>
											setNumericAttribute(
												field.key,
												event.target.value
											)
										}
									/>
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

					<div className="mcb-calc__results">
						<p className="mcb-calc__result-label">
							{ __(
								'Monthly Payment',
								'mortgage-calculator-block'
							) }
						</p>
						<p className="mcb-calc__result-primary">
							{ formatAmount(
								result.monthlyPayment,
								currencySymbol
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
										currencySymbol
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
										currencySymbol
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
										currencySymbol
									) }
								</dd>
							</div>
						</dl>
					</div>
				</div>

				<div className="mcb-calc__charts">
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

					<figure className="mcb-calc__chart">
						<figcaption className="mcb-calc__chart-title">
							{ __(
								'Balance Over Time',
								'mortgage-calculator-block'
							) }
						</figcaption>
						<div className="mcb-calc__chart-body" ref={ lineRef } />
						<figcaption
							className="mcb-calc__legend"
							ref={ lineLegendRef }
						/>
					</figure>
				</div>

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
			</div>
		</>
	);
}
