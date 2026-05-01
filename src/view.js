/**
 * Front-end behavior for the Mortgage Calculator block.
 *
 * Progressive enhancement only: results, sliders, and the amortization table
 * are already usable without JavaScript. This script adds slider/number
 * syncing, live recalculation while typing, dependency-free SVG charts, and
 * the schedule collapse toggle.
 *
 * All dynamic text is inserted via textContent/createElement — never
 * innerHTML — so user input can never inject markup.
 */

import {
	buildAmortizationSchedule,
	calculateMortgage,
} from './utils/calculator';
import { createDonutChart, createLineChart } from './utils/charts';

const CHART_WIDTH = 520;
const CHART_HEIGHT = 250;

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
 * Formats an amount using the block's configured symbol and precision.
 *
 * @param {number} amount Amount to format.
 * @param {Object} config Block config parsed from data-mcb-config.
 * @return {string} Formatted amount such as "$1,234.56".
 */
function formatAmount( amount, config ) {
	const decimals = Number.isFinite( config.decimals ) ? config.decimals : 2;
	const formatted = new Intl.NumberFormat( undefined, {
		minimumFractionDigits: decimals,
		maximumFractionDigits: decimals,
	} ).format( Number.isFinite( amount ) ? amount : 0 );

	return `${ config.symbol }${ formatted }`;
}

/**
 * Reads the current values of every bound number input in an instance.
 *
 * @param {HTMLElement} root Calculator container element.
 * @return {{loanAmount: number, downPayment: number, interestRate: number, loanTerm: number}} Current values.
 */
function readValues( root ) {
	const read = ( name ) => {
		const input = root.querySelector( `[data-mcb-field="${ name }"]` );
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
	root.querySelectorAll( '[data-mcb-field]' ).forEach( ( field ) => {
		const slider = root.querySelector(
			`[data-mcb-slider="${ field.dataset.mcbField }"]`
		);

		if ( ! slider || slider.value === field.value ) {
			return;
		}

		if ( field.dataset.mcbField === 'downPayment' ) {
			const amount = root.querySelector(
				'[data-mcb-field="loanAmount"]'
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
	const tbody = root.querySelector( '[data-mcb-schedule]' );

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
		const node = root.querySelector( `[data-mcb-bind="${ key }"]` );

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
		accent: read( '--mcb-accent', '#1a6f4b' ),
		accent2: read( '--mcb-accent-2', '#d97706' ),
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
		entry.className = 'mcb-calc__legend-item';

		const dot = document.createElement( 'span' );
		dot.className = 'mcb-calc__legend-dot';
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
 * table is disabled — the two features are independent.
 *
 * @param {HTMLElement} root   Calculator container element.
 * @param {Object}      values Current input values.
 * @param {Object}      config Block config.
 * @param {Object}      result Result from calculateMortgage().
 */
function renderCharts( root, values, config, result ) {
	const donutHost = root.querySelector( '[data-mcb-chart="donut"]' );
	const lineHost = root.querySelector( '[data-mcb-chart="line"]' );

	if ( ! donutHost && ! lineHost ) {
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
					size: 190,
					thickness: 26,
					centerTitle: labels.monthly,
					centerValue: formatAmount( result.monthlyPayment, config ),
				}
			)
		);

		renderLegend( root.querySelector( '[data-mcb-legend="donut"]' ), [
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
					height: CHART_HEIGHT,
					formatY: ( value ) =>
						formatAmount( value, { ...config, decimals: 0 } ),
					xLabels: buildXTicks( schedule ),
				}
			)
		);

		renderLegend( root.querySelector( '[data-mcb-legend="line"]' ), [
			{ label: labels.balance, color: palette.accent },
			{ label: labels.cumInt, color: palette.accent2 },
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
		config = JSON.parse( root.dataset.mcbConfig || '{}' );
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

	const form = root.querySelector( '.mcb-calc__form' );

	if ( form && ! form.dataset.mcbBound ) {
		form.addEventListener( 'input', ( event ) => {
			const target = event.target;

			if ( target.matches( '[data-mcb-slider]' ) ) {
				const field = root.querySelector(
					`[data-mcb-field="${ target.dataset.mcbSlider }"]`
				);

				if ( field ) {
					field.value = target.value;
				}

				if ( target.dataset.mcbSlider === 'loanAmount' ) {
					const downSlider = root.querySelector(
						'[data-mcb-slider="downPayment"]'
					);
					const downField = root.querySelector(
						'[data-mcb-field="downPayment"]'
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

		form.dataset.mcbBound = 'true';

		syncSliders( root );
		recalc();
	}

	const toggle = root.querySelector( '.mcb-calc__toggle' );
	const scheduleWrap = root.querySelector( '.mcb-calc__schedule' );

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
	.querySelectorAll( '.mcb-calc[data-mcb-config]' )
	.forEach( initializeCalculator );
