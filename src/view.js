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
	COST_COMPONENTS,
	round2,
} from './utils/calculator';
import {
	createBarChart,
	createDotsChart,
	createDonutChart,
	createLineChart,
	withAlpha,
} from './utils/charts';
import { NUMERIC_FIELDS, sliderBoundsFor } from './utils/field-definitions';

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
 * @param {Object} config Block config parsed from data-amortexa-config.
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
 * @param {Object} config Block config parsed from data-amortexa-config.
 * @return {string} Formatted amount such as "$1,234.56" or "1.234,56 €".
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
		const input = root.querySelector( `[data-amortexa-field="${ name }"]` );
		return input ? parseFloat( input.value ) : NaN;
	};

	const values = {
		loanAmount: read( 'loanAmount' ),
		downPayment: read( 'downPayment' ),
		interestRate: read( 'interestRate' ),
		loanTerm: read( 'loanTerm' ),
	};

	/*
	 * Cost components are optional, so their inputs only exist when the author
	 * turned them on. A missing field reads as zero rather than NaN, which matters
	 * because NaN would poison the sum and blank out the total.
	 */
	for ( const component of COST_COMPONENTS ) {
		const value = read( component.attribute );

		values[ component.attribute ] = Number.isFinite( value ) ? value : 0;

		const unit = root.querySelector(
			`[data-amortexa-unit="${ component.attribute }Unit"]`
		);

		values[ `${ component.attribute }Unit` ] = unit
			? unit.dataset.actualUnit || unit.dataset.amortexaUnit || 'percent'
			: 'amount';
	}

	return values;
}

/**
 * Clamps the down payment so it never exceeds the loan amount.
 *
 * The slider and the number input are two independent controls, so shrinking
 * the loan amount has to correct both. Leaving this to syncSliders() alone
 * desynchronises them, because the browser clamps the slider to its new max
 * while the input keeps the stale, larger number.
 *
 * The slider is only present when the block renders sliders, so the input is
 * clamped whether or not the slider exists.
 *
 * @param {HTMLElement}   root       Calculator container element.
 * @param {string|number} loanAmount Current loan amount value.
 */
function clampDownPayment( root, loanAmount ) {
	const downField = root.querySelector(
		'[data-amortexa-field="downPayment"]'
	);

	if ( ! downField ) {
		return;
	}

	const max = Math.max( parseFloat( loanAmount ) || 0, 1 );
	const downSlider = root.querySelector(
		'[data-amortexa-slider="downPayment"]'
	);

	if ( downSlider ) {
		downSlider.max = String( max );
	}

	if ( ( parseFloat( downField.value ) || 0 ) > max ) {
		downField.value = String( max );
	}
}

/**
 * Keeps each range slider in sync with its number input and widens a track that
 * cannot represent the value beside it.
 *
 * The two controls are independent, and the browser clamps a range input to its
 * own min/max. A track narrower than the accepted range therefore does not
 * reject anything -- it just shows the thumb pinned against an end while the
 * input holds a different number, so the pair disagrees on screen with nothing
 * to explain it. sliderBoundsFor() stretches the track to cover the value
 * instead, which is what makes the two controls mean the same thing.
 *
 * @param {HTMLElement} root Calculator container element.
 */
function syncSliders( root ) {
	root.querySelectorAll( '[data-amortexa-field]' ).forEach( ( field ) => {
		const key = field.dataset.amortexaField;
		const slider = root.querySelector(
			`[data-amortexa-slider="${ key }"]`
		);

		if ( ! slider ) {
			return;
		}

		const definition = NUMERIC_FIELDS.find(
			( candidate ) => candidate.key === key
		);

		/*
		 * A derived max is a constraint, not a display bound: the down payment
		 * cannot exceed the loan. clampDownPayment() owns that rule, so leave its
		 * track alone here -- widening it to reach a value that should have been
		 * clamped would quietly discard the constraint.
		 */
		if ( definition && ! definition.sliderMaxFrom ) {
			const bounds = sliderBoundsFor( definition, field.value );

			slider.min = String( bounds.min );
			slider.max = String( Math.max( bounds.max, bounds.min ) );
		}

		if ( slider.value === field.value ) {
			return;
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
	const tbody = root.querySelector( '[data-amortexa-schedule]' );

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
	const costs = result.monthlyCosts || {};

	const bindings = {
		monthlyPayment: result.monthlyPayment,
		principal: result.principal,
		totalInterest: result.totalInterest,
		totalPaid: result.totalPaid,
		totalMonthlyCost: result.totalMonthlyCost,
		totalCosts: result.totalCosts,
		totalOutOfPocket: result.totalOutOfPocket,
	};

	// One binding per component row. Absent rows are simply skipped.
	for ( const component of COST_COMPONENTS ) {
		bindings[
			`cost${ component.key
				.charAt( 0 )
				.toUpperCase() }${ component.key.slice( 1 ) }`
		] = costs[ component.key ] || 0;
	}

	Object.entries( bindings ).forEach( ( [ key, value ] ) => {
		const node = root.querySelector( `[data-amortexa-bind="${ key }"]` );

		if ( node ) {
			node.textContent = formatAmount( value, config );
		}
	} );

	/*
	 * Rows the server omitted cannot reappear without markup, so only the ones
	 * already present are hidden. Clearing a cost therefore tidies the summary
	 * instead of leaving a row reading zero for something nobody entered.
	 */
	for ( const component of COST_COMPONENTS ) {
		const row = root.querySelector(
			`[data-amortexa-cost="${ component.key }"]`
		);

		if ( row ) {
			row.hidden = ! ( costs[ component.key ] > 0 );
		}
	}
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
		accent: read( '--amortexa-accent', '#1a6f4b' ),
		accent2: read( '--amortexa-accent-2', '#d97706' ),
	};
}

/**
 * Reads the per-component cost colours from CSS custom properties.
 *
 * Read the same way as the two-series palette so a skin or a design override
 * restyles the donut without any JavaScript knowing the hex values.
 *
 * @param {HTMLElement} root Calculator container element.
 * @return {Object.<string, string>} Component key => colour.
 */
function readCostPalette( root ) {
	const styles = window.getComputedStyle( root );

	const read = ( key, fallback ) =>
		(
			styles.getPropertyValue( `--amortexa-cost-${ key }` ) || fallback
		).trim();

	return {
		pi: read( 'pi', '#2563eb' ),
		tax: read( 'tax', '#047857' ),
		insurance: read( 'insurance', '#b45309' ),
		hoa: read( 'hoa', '#7c3aed' ),
		pmi: read( 'pmi', '#db2777' ),
		other: read( 'other', '#57534e' ),
	};
}

/**
 * Builds the donut and legend series for a result.
 *
 * When the author has entered any recurring cost, the chart switches from
 * "principal vs lifetime interest" to "what this month's payment is actually
 * made of", because a donut showing a 30-year interest total next to a monthly
 * figure mixes two different time scales and reads as a share of the wrong whole.
 * With no costs entered it keeps the original two slices, so an existing
 * calculator's chart is unchanged.
 *
 * Components left at zero are omitted rather than drawn as zero-width slices,
 * which would still claim space in the legend.
 *
 * @param {HTMLElement} root   Calculator container element.
 * @param {Object}      result Result from calculateMortgage().
 * @param {Object}      labels Config labels.
 * @return {{series: Array<Object>, legend: Array<Object>}|null} Null when there is nothing to show.
 */
function buildCostSeries( root, result, labels ) {
	const palette = readCostPalette( root );
	const costs = result.monthlyCosts || {};
	const series = [];
	const legend = [];

	const add = ( key, label, value ) => {
		if ( ! ( value > 0 ) ) {
			return;
		}

		series.push( { value, color: palette[ key ] } );
		legend.push( { key, label, color: palette[ key ] } );
	};

	const monthlyPayment = Number( result.monthlyPayment ) || 0;
	const active = Object.keys( costs ).some(
		( key ) => ( Number( costs[ key ] ) || 0 ) > 0
	);

	if ( ! active ) {
		return null;
	}

	add( 'pi', labels.pi || 'Principal & Interest', monthlyPayment );

	for ( const key of [ 'tax', 'insurance', 'hoa', 'pmi', 'other' ] ) {
		add( key, labels[ key ] || key, Number( costs[ key ] ) || 0 );
	}

	return { series, legend };
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
		entry.className = 'amortexa-calc__legend-item';

		const dot = document.createElement( 'span' );
		dot.className = 'amortexa-calc__legend-dot';

		/*
		 * The colour is set inline from the palette that was read off the
		 * computed style, so a legend entry is correct even before its CSS loads.
		 * The data attribute is what lets the stylesheet colour the same dot from
		 * the component variables, which is how an override in the editor shows up
		 * here without the JS having to know the hex value.
		 */
		if ( item.key ) {
			dot.dataset.amortexaSeries = item.key;
		}

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
	const donutHost = root.querySelector( '[data-amortexa-chart="donut"]' );
	const lineHost = root.querySelector( '[data-amortexa-chart="line"]' );
	const barHost = root.querySelector( '[data-amortexa-chart="bar"]' );
	const dotsHost = root.querySelector( '[data-amortexa-chart="dots"]' );

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
		const costSeries = buildCostSeries( root, result, labels );

		// With no recurring costs entered the donut keeps its original two slices.
		const slices = costSeries
			? costSeries.series
			: [
					{ value: result.principal, color: palette.accent },
					{ value: result.totalInterest, color: palette.accent2 },
				];

		const centerValue = costSeries
			? result.totalMonthlyCost
			: result.monthlyPayment;

		donutHost.replaceChildren(
			createDonutChart( slices, {
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
				centerTitle: costSeries ? labels.totalMonthly : labels.monthly,
				centerValue: formatAmount( centerValue, config ),
			} )
		);

		renderLegend(
			root.querySelector( '[data-amortexa-legend="donut"]' ),
			costSeries
				? costSeries.legend.map( ( item ) => ( {
						label: item.label,
						color: item.color,
						key: item.key,
					} ) )
				: [
						{ label: labels.principal, color: palette.accent },
						{ label: labels.totalInt, color: palette.accent2 },
					]
		);
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

		renderLegend( root.querySelector( '[data-amortexa-legend="line"]' ), [
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

		renderLegend( root.querySelector( '[data-amortexa-legend="bar"]' ), [
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

		renderLegend( root.querySelector( '[data-amortexa-legend="dots"]' ), [
			{ label: labels.principal, color: series[ 0 ].color },
			{ label: labels.balance, color: series[ 1 ].color },
			{ label: labels.totalInt, color: series[ 2 ].color },
			{ label: labels.totalPaid, color: series[ 3 ].color },
		] );
	}

	labelCharts( [ donutHost, lineHost, barHost, dotsHost ] );
}

/**
 * Gives every drawn chart an accessible name.
 *
 * Each chart is marked role="img", which without a name makes assistive tech
 * announce a bare "image". The visible caption beside the chart is already the
 * human-readable title, so it is reused instead of duplicating the string.
 *
 * @param {Array<Element|null>} hosts Chart containers that were drawn into.
 */
function labelCharts( hosts ) {
	hosts.forEach( ( host ) => {
		const svg = host && host.querySelector( 'svg' );

		if ( ! svg || svg.getAttribute( 'aria-label' ) ) {
			return;
		}

		const figure = host.closest( 'figure' );
		const caption = figure
			? figure.querySelector( '.amortexa-calc__chart-title' )
			: null;
		const text = caption ? ( caption.textContent || '' ).trim() : '';

		if ( text ) {
			svg.setAttribute( 'aria-label', text );
		}
	} );
}

/**
 * Wires up a single calculator instance found on the page.
 *
 * @param {HTMLElement} root Calculator container element.
 */
function initializeCalculator( root ) {
	let config;

	try {
		config = JSON.parse( root.dataset.amortexaConfig || '{}' );
	} catch {
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

	const form = root.querySelector( '.amortexa-calc__form' );

	if ( form && ! form.dataset.amortexaBound ) {
		form.addEventListener( 'input', ( event ) => {
			const target = event.target;

			if ( target.matches( '[data-amortexa-slider]' ) ) {
				const field = root.querySelector(
					`[data-amortexa-field="${ target.dataset.amortexaSlider }"]`
				);

				if ( field ) {
					field.value = target.value;
				}
			}

			// Clamp for both entry points: dragging the slider and typing into the
			// loan amount input each change the value the other control depends on.
			if (
				target.dataset.amortexaField === 'loanAmount' ||
				target.dataset.amortexaSlider === 'loanAmount'
			) {
				clampDownPayment( root, target.value );
			}

			syncSliders( root );
			recalc();
		} );

		form.dataset.amortexaBound = 'true';

		syncSliders( root );
		recalc();
	}

	/*
	 * The percent/amount toggle converts the number rather than only relabelling
	 * it, so switching a tax rate to a cash amount carries the value across
	 * instead of leaving a misleading figure in the field.
	 *
	 * A percentage becomes price * value / 100, and a cash amount becomes its
	 * share of the price. The converted value is what gets typed back, so the
	 * stored attribute always matches what is on screen.
	 */
	root.querySelectorAll( '[data-amortexa-unit]' ).forEach( ( button ) => {
		if ( button.dataset.amortexaBound ) {
			return;
		}

		button.dataset.actualUnit = button.dataset.amortexaUnit || 'percent';

		button.addEventListener( 'click', () => {
			const attribute = button.dataset.amortexaUnit.replace(
				/Unit$/,
				''
			);
			const input = root.querySelector(
				`[data-amortexa-field="${ attribute }"]`
			);

			if ( input ) {
				const current = parseFloat( input.value );
				const price = parseFloat(
					(
						root.querySelector(
							'[data-amortexa-field="loanAmount"]'
						) || {}
					).value
				);

				if ( Number.isFinite( current ) && Number.isFinite( price ) ) {
					const toAmount = 'percent' === button.dataset.actualUnit;

					input.value = round2(
						toAmount
							? ( price * current ) / 100
							: ( current / price ) * 100
					);
				}
			}

			button.dataset.actualUnit =
				'percent' === button.dataset.actualUnit ? 'amount' : 'percent';
			button.textContent =
				'percent' === button.dataset.actualUnit ? '%' : 'Amount';

			recalc();
		} );

		button.dataset.amortexaBound = 'true';
	} );

	const toggle = root.querySelector( '.amortexa-calc__toggle' );
	const scheduleBody = root.querySelector( '[data-amortexa-schedule-body]' );

	if ( toggle && scheduleBody ) {
		/*
		 * The button is display:none in CSS so it never appears without the
		 * collapse behavior behind it. It has to be given an explicit value here:
		 * clearing the inline style would just fall back to display:none and the
		 * control would stay invisible.
		 */
		toggle.style.display = 'inline-block';

		toggle.addEventListener( 'click', () => {
			const expanded = toggle.getAttribute( 'aria-expanded' ) === 'true';
			const collapse = toggle.dataset.amortexaLabelCollapse;
			const expand = toggle.dataset.amortexaLabelExpand;

			toggle.setAttribute( 'aria-expanded', expanded ? 'false' : 'true' );
			scheduleBody.hidden = expanded;

			/*
			 * The visible label names the action the button performs, so a
			 * collapsed table has to read "Expand schedule" rather than
			 * "Collapse schedule". A missing translation leaves the text alone.
			 */
			const next = expanded ? expand : collapse;

			if ( next ) {
				toggle.textContent = next;
			}
		} );
	}
}

document
	.querySelectorAll( '.amortexa-calc[data-amortexa-config]' )
	.forEach( initializeCalculator );
