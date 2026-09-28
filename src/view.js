/**
 * Front-end behavior for the Mortgage Calculator block.
 *
 * Progressive enhancement only: results, sliders, and the amortization table
 * are already usable without JavaScript. This script adds slider/number
 * syncing, live recalculation while typing, dependency-free SVG charts, and
 * the schedule collapse toggle.
 *
 * All dynamic text is inserted via textContent/createElement â€” never
 * innerHTML â€” so user input can never inject markup.
 */

import {
	buildAmortizationSchedule,
	calculateMortgage,
} from './utils/calculator';
import {
	createBarChart,
	createDotsChart,
	createDonutChart,
	createLineChart,
	withAlpha,
} from './utils/charts';

const CHART_WIDTH = 520;
const DEFAULT_CHART_HEIGHT = 250;

/**
 * Resolves the chart height, honouring the inspector's chart height token.
 *
 * The charts are drawn as SVG rather than sized by CSS, so a height override
 * arrives as a number in the config payload instead of as a custom property.
 * A missing, zero or non-numeric value means the token is unset and the default
 * applies, which is what keeps existing posts rendering exactly as before.
 *
 * @param {Object} config Block config parsed from data-calcforge-config.
 * @return {number} Chart height in SVG user units.
 */
function chartHeight( config ) {
	const value = Number( config.chartHeight );

	return Number.isFinite( value ) && value >= 120 && value <= 560
		? value
		: DEFAULT_CHART_HEIGHT;
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
 * Formats an amount using the block's configured symbol, precision, and
 * symbol position.
 *
 * @param {number} amount Amount to format.
 * @param {Object} config Block config parsed from data-calcforge-config.
 * @return {string} Formatted amount such as "$1,234.56" or "1.234,56 â‚¬".
 */
function formatAmount( amount, config ) {
	const decimals = Number.isFinite( config.decimals ) ? config.decimals : 2;
	const formatted = new Intl.NumberFormat( undefined, {
		minimumFractionDigits: decimals,
		maximumFractionDigits: decimals,
	} ).format( Number.isFinite( amount ) ? amount : 0 );

	return 'suffix' === config.position
		? `${ formatted }${ config.symbol }`
		: `${ config.symbol }${ formatted }`;
}

/**
 * Reads the current values of every bound number input in an instance.
 *
 * @param {HTMLElement} root Calculator container element.
 * @return {{loanAmount: number, downPayment: number, interestRate: number, loanTerm: number}} Current values.
 */
function readValues( root ) {
	const read = ( name ) => {
		const input = root.querySelector(
			`[data-calcforge-field="${ name }"]`
		);
		return input ? parseFloat( input.value ) : NaN;
	};

	return {
		loanAmount: read( 'loanAmount' ),
		downPayment: read( 'downPayment' ),
		interestRate: read( 'interestRate' ),
		loanTerm: read( 'loanTerm' ),
	};
}

/**
 * Keeps each range slider in sync with its number input and clamps the down
 * payment track to the current loan amount.
 *
 * @param {HTMLElement} root Calculator container element.
 */
function syncSliders( root ) {
	root.querySelectorAll( '[data-calcforge-field]' ).forEach( ( field ) => {
		const slider = root.querySelector(
			`[data-calcforge-slider="${ field.dataset.calcforgeField }"]`
		);

		if ( ! slider || slider.value === field.value ) {
			return;
		}

		if ( field.dataset.calcforgeField === 'downPayment' ) {
			const amount = root.querySelector(
				'[data-calcforge-field="loanAmount"]'
			);
			slider.max = String(
				Math.max( parseFloat( amount ? amount.value : '0' ), 1 )
			);
		}

		slider.value = field.value;
	} );
}

/**
 * Replaces the schedule table body with freshly calculated annual rows.
 *
 * @param {HTMLElement}   root     Calculator container element.
 * @param {Array<Object>} schedule Annual schedule rows.
 * @param {Object}        config   Block config.
 */
function renderSchedule( root, schedule, config ) {
	const tbody = root.querySelector( '[data-calcforge-schedule]' );

	if ( ! tbody ) {
		return;
	}

	const fragment = document.createDocumentFragment();

	schedule.forEach( ( row ) => {
		const tr = document.createElement( 'tr' );

		[ row.year, row.principal, row.interest, row.balance ].forEach(
			( value, index ) => {
				const cell = document.createElement( 'td' );
				cell.textContent =
					index === 0
						? String( value )
						: formatAmount( value, config );
				tr.appendChild( cell );
			}
		);

		fragment.appendChild( tr );
	} );

	tbody.replaceChildren( fragment );
}

/**
 * Updates every bound result node for a calculator instance.
 *
 * @param {HTMLElement} root   Calculator container element.
 * @param {Object}      result Result object from calculateMortgage().
 * @param {Object}      config Block config.
 */
function renderResults( root, result, config ) {
	const bindings = {
		monthlyPayment: result.monthlyPayment,
		principal: result.principal,
		totalInterest: result.totalInterest,
		totalPaid: result.totalPaid,
	};

	Object.entries( bindings ).forEach( ( [ key, value ] ) => {
		const node = root.querySelector( `[data-calcforge-bind="${ key }"]` );

		if ( node ) {
			node.textContent = formatAmount( value, config );
		}
	} );
}

/**
 * Reads the active skin palette from CSS custom properties so charts always
 * match the selected skin without duplicating colors in JS.
 *
 * @param {HTMLElement} root Calculator container element.
 * @return {{accent: string, accent2: string}} Resolved colors.
 */
function readPalette( root ) {
	const styles = window.getComputedStyle( root );

	const read = ( name, fallback ) =>
		( styles.getPropertyValue( name ) || fallback ).trim();

	return {
		accent: read( '--calcforge-accent', '#1a6f4b' ),
		accent2: read( '--calcforge-accent-2', '#d97706' ),
	};
}

/**
 * Builds a small legend (color dot + label) into a host element.
 *
 * @param {HTMLElement}                           host  Legend container.
 * @param {Array<{label: string, color: string}>} items Legend entries.
 */
function renderLegend( host, items ) {
	if ( ! host ) {
		return;
	}

	const fragment = document.createDocumentFragment();

	items.forEach( ( item ) => {
		const entry = document.createElement( 'span' );
		entry.className = 'calcforge-calc__legend-item';

		const dot = document.createElement( 'span' );
		dot.className = 'calcforge-calc__legend-dot';
		dot.style.backgroundColor = item.color;

		const label = document.createElement( 'span' );
		label.textContent = item.label;

		entry.appendChild( dot );
		entry.appendChild( label );
		fragment.appendChild( entry );
	} );

	host.replaceChildren( fragment );
}

/**
 * Draws the donut (payment composition) and line (balance over time) charts.
 *
 * The balance series is always computed for charts even when the schedule
 * table is disabled â€” the two features are independent.
 *
 * @param {HTMLElement} root   Calculator container element.
 * @param {Object}      values Current input values.
 * @param {Object}      config Block config.
 * @param {Object}      result Result from calculateMortgage().
 */
function renderCharts( root, values, config, result ) {
	const donutHost = root.querySelector( '[data-calcforge-chart="donut"]' );
	const lineHost = root.querySelector( '[data-calcforge-chart="line"]' );
	const barHost = root.querySelector( '[data-calcforge-chart="bar"]' );
	const dotsHost = root.querySelector( '[data-calcforge-chart="dots"]' );

	if ( ! donutHost && ! lineHost && ! barHost && ! dotsHost ) {
		return;
	}

	const palette = readPalette( root );
	const labels = config.labels || {};
	const schedule = buildAmortizationSchedule(
		result.principal,
		values.interestRate,
		values.loanTerm
	);

	if ( donutHost ) {
		donutHost.replaceChildren(
			createDonutChart(
				[
					{ value: result.principal, color: palette.accent },
					{ value: result.totalInterest, color: palette.accent2 },
				],
				{
					size: Math.min(
						260,
						Math.round( chartHeight( config ) * 0.76 )
					),
					thickness: Math.max(
						12,
						Math.round(
							Math.min(
								260,
								Math.round( chartHeight( config ) * 0.76 )
							) * 0.135
						)
					),
					centerTitle: labels.monthly,
					centerValue: formatAmount( result.monthlyPayment, config ),
				}
			)
		);

		renderLegend( root.querySelector( '[data-calcforge-legend="donut"]' ), [
			{ label: labels.principal, color: palette.accent },
			{ label: labels.totalInt, color: palette.accent2 },
		] );
	}

	if ( lineHost ) {
		let runningInterest = 0;
		const cumulativeInterest = schedule.map( ( row ) => {
			runningInterest += row.interest;
			return Math.round( runningInterest );
		} );
		const balances = schedule.map( ( row ) => Math.round( row.balance ) );

		lineHost.replaceChildren(
			createLineChart(
				[
					{
						points: balances,
						color: palette.accent,
						area: true,
					},
					{
						points: cumulativeInterest,
						color: palette.accent2,
					},
				],
				{
					width: CHART_WIDTH,
					height: chartHeight( config ),
					formatY: ( value ) =>
						formatAmount( value, { ...config, decimals: 0 } ),
					xLabels: buildXTicks( schedule ),
				}
			)
		);

		renderLegend( root.querySelector( '[data-calcforge-legend="line"]' ), [
			{ label: labels.balance, color: palette.accent },
			{ label: labels.cumInt, color: palette.accent2 },
		] );
	}

	if ( barHost ) {
		const principalPaid = schedule.map( ( row ) =>
			Math.round( row.principal )
		);
		const interestPaid = schedule.map( ( row ) =>
			Math.round( row.interest )
		);

		barHost.replaceChildren(
			createBarChart(
				[
					{
						points: principalPaid,
						color: palette.accent,
						label: labels.prinPaid,
					},
					{
						points: interestPaid,
						color: palette.accent2,
						label: labels.intPaid,
					},
				],
				{
					width: CHART_WIDTH,
					height: chartHeight( config ),
					formatY: ( value ) =>
						formatAmount( value, { ...config, decimals: 0 } ),
					xLabels: buildXTicks( schedule ),
				}
			)
		);

		renderLegend( root.querySelector( '[data-calcforge-legend="bar"]' ), [
			{ label: labels.prinPaid, color: palette.accent },
			{ label: labels.intPaid, color: palette.accent2 },
		] );
	}

	if ( dotsHost ) {
		/*
		 * Only loan level totals are plotted. The monthly payment is a rate
		 * rather than a total, so on a shared linear scale it would collapse
		 * onto zero and read as "nothing", which is misleading next to sums
		 * that are thousands of times larger. The balance is read after the
		 * first year, because at origination it would just repeat the
		 * principal and the terminal balance is always zero.
		 */
		const yearOneBalance = schedule.length
			? schedule[ 0 ].balance
			: result.principal;

		const series = [
			{
				value: result.principal,
				color: palette.accent,
				label: labels.principal,
			},
			{
				value: yearOneBalance,
				color: withAlpha( palette.accent, 0.5 ),
				label: labels.balanceY1,
			},
			{
				value: result.totalInterest,
				color: palette.accent2,
				label: labels.totalInt,
			},
			{
				value: result.totalPaid,
				color: withAlpha( palette.accent2, 0.5 ),
				label: labels.totalPaid,
			},
		];

		dotsHost.replaceChildren(
			createDotsChart( series, {
				width: CHART_WIDTH,
				// The dot plot is drawn shorter than the line and bar charts by
				// design, so the height token scales it rather than replacing it.
				height: Math.round( chartHeight( config ) * 0.76 ),
				formatY: ( value ) =>
					formatAmount( value, { ...config, decimals: 0 } ),
			} )
		);

		renderLegend( root.querySelector( '[data-calcforge-legend="dots"]' ), [
			{ label: labels.principal, color: series[ 0 ].color },
			{ label: labels.balance, color: series[ 1 ].color },
			{ label: labels.totalInt, color: series[ 2 ].color },
			{ label: labels.totalPaid, color: series[ 3 ].color },
		] );
	}
}

/**
 * Wires up a single calculator instance found on the page.
 *
 * @param {HTMLElement} root Calculator container element.
 */
function initializeCalculator( root ) {
	let config;

	try {
		config = JSON.parse( root.dataset.calcforgeConfig || '{}' );
	} catch ( error ) {
		config = {};
	}

	const recalc = () => {
		const values = readValues( root );
		const result = calculateMortgage( values );

		renderResults( root, result, config );
		renderCharts( root, values, config, result );

		if ( config.showAmortization !== false ) {
			renderSchedule(
				root,
				buildAmortizationSchedule(
					result.principal,
					values.interestRate,
					values.loanTerm
				),
				config
			);
		}
	};

	const form = root.querySelector( '.calcforge-calc__form' );

	if ( form && ! form.dataset.calcforgeBound ) {
		form.addEventListener( 'input', ( event ) => {
			const target = event.target;

			if ( target.matches( '[data-calcforge-slider]' ) ) {
				const field = root.querySelector(
					`[data-calcforge-field="${ target.dataset.calcforgeSlider }"]`
				);

				if ( field ) {
					field.value = target.value;
				}

				if ( target.dataset.calcforgeSlider === 'loanAmount' ) {
					const downSlider = root.querySelector(
						'[data-calcforge-slider="downPayment"]'
					);
					const downField = root.querySelector(
						'[data-calcforge-field="downPayment"]'
					);

					if ( downSlider && downField ) {
						downSlider.max = String(
							Math.max( parseFloat( target.value ) || 0, 1 )
						);

						if (
							( parseFloat( downField.value ) || 0 ) >
							( parseFloat( downSlider.max ) || 0 )
						) {
							downField.value = downSlider.max;
						}
					}
				}
			}

			syncSliders( root );
			recalc();
		} );

		form.dataset.calcforgeBound = 'true';

		syncSliders( root );
		recalc();
	}

	const toggle = root.querySelector( '.calcforge-calc__toggle' );
	const scheduleWrap = root.querySelector( '.calcforge-calc__schedule' );

	if ( toggle && scheduleWrap ) {
		toggle.style.display = '';

		toggle.addEventListener( 'click', () => {
			const expanded = toggle.getAttribute( 'aria-expanded' ) === 'true';
			toggle.setAttribute( 'aria-expanded', expanded ? 'false' : 'true' );
			scheduleWrap.hidden = expanded;
		} );
	}
}

document
	.querySelectorAll( '.calcforge-calc[data-calcforge-config]' )
	.forEach( initializeCalculator );
