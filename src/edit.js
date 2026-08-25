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
	BaseControl,
	ColorPalette,
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
	{
		value: 'midnight',
		label: __( 'Midnight Violet', 'mortgage-calculator-block' ),
	},
	{
		value: 'rose',
		label: __( 'Rose Quartz', 'mortgage-calculator-block' ),
	},
	{
		value: 'slate',
		label: __( 'Minimal Slate', 'mortgage-calculator-block' ),
	},
	{
		value: 'grape',
		label: __( 'Royal Grape', 'mortgage-calculator-block' ),
	},
	{ value: 'aqua', label: __( 'Aqua Fresh', 'mortgage-calculator-block' ) },
	{
		value: 'mocha',
		label: __( 'Mocha Cream', 'mortgage-calculator-block' ),
	},
	{ value: 'cyber', label: __( 'Cyber Neon', 'mortgage-calculator-block' ) },
];

const FONT_FAMILIES = [
	{
		value: 'inherit',
		label: __( 'Theme default', 'mortgage-calculator-block' ),
	},
	{
		value: 'sans',
		label: __( 'Modern Sans (system)', 'mortgage-calculator-block' ),
	},
	{
		value: 'serif',
		label: __( 'Classic Serif (system)', 'mortgage-calculator-block' ),
	},
	{
		value: 'mono',
		label: __( 'Monospace (system)', 'mortgage-calculator-block' ),
	},
];

const FONT_STACKS = {
	inherit: '',
	sans: 'system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif',
	serif: 'Georgia, "Times New Roman", Times, serif',
	mono: 'ui-monospace, "SF Mono", "Cascadia Code", Consolas, Menlo, monospace',
};

const COLOR_CONTROLS = [
	{
		key: 'accentColor',
		label: __( 'Accent', 'mortgage-calculator-block' ),
		cssVar: '--mcb-accent',
	},
	{
		key: 'accentAltColor',
		label: __( 'Secondary accent (charts)', 'mortgage-calculator-block' ),
		cssVar: '--mcb-accent-2',
	},
	{
		key: 'labelColor',
		label: __( 'Label text', 'mortgage-calculator-block' ),
		cssVar: '--mcb-label-color',
	},
	{
		key: 'fieldTextColor',
		label: __( 'Field text', 'mortgage-calculator-block' ),
		cssVar: '--mcb-field-text',
	},
	{
		key: 'fieldBackgroundColor',
		label: __( 'Field background', 'mortgage-calculator-block' ),
		cssVar: '--mcb-field-bg',
	},
	{
		key: 'fieldBorderColor',
		label: __( 'Field border', 'mortgage-calculator-block' ),
		cssVar: '--mcb-field-border',
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
 * Formats an amount with a currency symbol placed before or after it.
 *
 * @param {number} amount   Amount to format.
 * @param {string} symbol   Currency symbol.
 * @param {string} position Symbol placement: 'prefix' or 'suffix'.
 * @return {string} Formatted amount such as "$1,234.56" or "1.234,56 €".
 */
function formatAmount( amount, symbol, position = 'prefix' ) {
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
		currencyPosition,
		showAmortization,
		showCharts,
		showSliders,
		showResults,
		chartType,
		paymentFontSize,
		paymentFontWeight,
		theme,
		fontFamily,
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

	/**
	 * Builds inline CSS custom property overrides from the per-block color
	 * and font settings so they beat any skin in both specificity orders.
	 *
	 * @return {Object} React style object with CSS variables.
	 */
	function getPaletteOverrides() {
		const overrides = {};

		COLOR_CONTROLS.forEach( ( control ) => {
			const value = attributes[ control.key ];

			if ( value ) {
				overrides[ control.cssVar ] = value;
			}
		} );

		const stack =
			FONT_STACKS[
				FONT_FAMILIES.some( ( f ) => f.value === fontFamily )
					? fontFamily
					: 'inherit'
			];

		if ( stack ) {
			overrides.fontFamily = stack;
		}

		return Object.keys( overrides ).length ? overrides : undefined;
	}

	const blockProps = useBlockProps( {
		className: `mcb-calc mcb-theme-${
			SKINS.some( ( skin ) => skin.value === theme ) ? theme : 'light'
		}`,
		style: getPaletteOverrides(),
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
	}, [ result, chartSchedule, currencySymbol, currencyPosition ] );

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

	const paymentTypography = {};

	if ( Number( paymentFontSize ) > 0 ) {
		paymentTypography.fontSize = `${ Number( paymentFontSize ) }px`;
	}

	if ( paymentFontWeight ) {
		paymentTypography.fontWeight = paymentFontWeight;
	}

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
					<SelectControl
						label={ __(
							'Currency Position',
							'mortgage-calculator-block'
						) }
						value={
							[ 'prefix', 'suffix' ].includes( currencyPosition )
								? currencyPosition
								: 'prefix'
						}
						options={ [
							{
								value: 'prefix',
								label: __(
									'Before amount ($99)',
									'mortgage-calculator-block'
								),
							},
							{
								value: 'suffix',
								label: __(
									'After amount (99 €)',
									'mortgage-calculator-block'
								),
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { currencyPosition: value } )
						}
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Display', 'mortgage-calculator-block' ) }
				>
					<ToggleControl
						label={ __(
							'Show Results Summary',
							'mortgage-calculator-block'
						) }
						checked={ showResults }
						onChange={ ( value ) =>
							setAttributes( { showResults: value } )
						}
					/>
					<ToggleControl
						label={ __(
							'Show Sliders',
							'mortgage-calculator-block'
						) }
						checked={ showSliders }
						onChange={ ( value ) =>
							setAttributes( { showSliders: value } )
						}
					/>
					<ToggleControl
						label={ __(
							'Show Charts',
							'mortgage-calculator-block'
						) }
						checked={ showCharts }
						onChange={ ( value ) =>
							setAttributes( { showCharts: value } )
						}
					/>
					<SelectControl
						label={ __(
							'Chart Type',
							'mortgage-calculator-block'
						) }
						value={
							[ 'donut', 'line', 'both' ].includes( chartType )
								? chartType
								: 'both'
						}
						options={ [
							{
								value: 'both',
								label: __(
									'Both Charts',
									'mortgage-calculator-block'
								),
							},
							{
								value: 'donut',
								label: __(
									'Donut Only',
									'mortgage-calculator-block'
								),
							},
							{
								value: 'line',
								label: __(
									'Line Only',
									'mortgage-calculator-block'
								),
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { chartType: value } )
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

				<PanelBody
					title={ __( 'Colors', 'mortgage-calculator-block' ) }
					initialOpen={ false }
				>
					<p>
						{ __(
							'Leave a color empty to use the selected skin.',
							'mortgage-calculator-block'
						) }
					</p>
					{ COLOR_CONTROLS.map( ( control ) => (
						<BaseControl
							key={ control.key }
							id={ `mcb-color-${ control.key }` }
							label={ control.label }
						>
							<ColorPalette
								value={ attributes[ control.key ] || undefined }
								onChange={ ( value ) =>
									setAttributes( {
										[ control.key ]: value || '',
									} )
								}
							/>
						</BaseControl>
					) ) }
				</PanelBody>

				<PanelBody
					title={ __( 'Typography', 'mortgage-calculator-block' ) }
					initialOpen={ false }
				>
					<SelectControl
						label={ __(
							'Font Family',
							'mortgage-calculator-block'
						) }
						value={ fontFamily }
						options={ FONT_FAMILIES }
						onChange={ ( value ) =>
							setAttributes( { fontFamily: value } )
						}
					/>
					<TextControl
						type="number"
						label={ __(
							'Payment Font Size (px, 0 = theme default)',
							'mortgage-calculator-block'
						) }
						value={ String( Number( paymentFontSize ) || 0 ) }
						min={ 0 }
						max={ 120 }
						onChange={ ( value ) =>
							setAttributes( {
								paymentFontSize: parseFloat( value ) || 0,
							} )
						}
					/>
					<SelectControl
						label={ __(
							'Payment Font Weight',
							'mortgage-calculator-block'
						) }
						value={
							[
								'300',
								'400',
								'500',
								'600',
								'700',
								'800',
							].includes( paymentFontWeight )
								? paymentFontWeight
								: ''
						}
						options={ [
							{
								value: '',
								label: __(
									'Theme default',
									'mortgage-calculator-block'
								),
							},
							{
								value: '300',
								label: __(
									'Light (300)',
									'mortgage-calculator-block'
								),
							},
							{
								value: '400',
								label: __(
									'Normal (400)',
									'mortgage-calculator-block'
								),
							},
							{
								value: '500',
								label: __(
									'Medium (500)',
									'mortgage-calculator-block'
								),
							},
							{
								value: '600',
								label: __(
									'Semi Bold (600)',
									'mortgage-calculator-block'
								),
							},
							{
								value: '700',
								label: __(
									'Bold (700)',
									'mortgage-calculator-block'
								),
							},
							{
								value: '800',
								label: __(
									'Extra Bold (800)',
									'mortgage-calculator-block'
								),
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { paymentFontWeight: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>

			<div { ...blockProps } ref={ rootRef }>
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
			</div>
		</>
	);
}
