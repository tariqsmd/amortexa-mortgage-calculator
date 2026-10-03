/**
 * Pure mortgage math shared between the block editor preview and the
 * front-end view script.
 *
 * This module mirrors the authoritative PHP helpers in includes/helpers.php
 * (amortexa_calculate_monthly_payment / amortexa_calculate_amortization_schedule) so the
 * editor and browser previews agree with server-rendered output.
 */

export const ATTR_LIMITS = {
	MAX_AMOUNT: 999999999999,
	MAX_RATE: 100,
	MIN_TERM: 1,
	MAX_TERM: 60,
};

/**
 * Clamps a numeric input to a range, coercing invalid values to the minimum.
 *
 * @param {unknown} value Raw value from an input or attribute.
 * @param {number}  min   Lower bound.
 * @param {number}  max   Upper bound.
 * @return {number} Clamped finite number.
 */
export function clampFloat( value, min, max ) {
	const parsed =
		typeof value === 'number' ? value : parseFloat( String( value ?? '' ) );
	const safe = Number.isFinite( parsed ) ? parsed : 0;
	return Math.min( Math.max( safe, min ), max );
}

/**
 * Calculates the monthly payment for an amortizing loan.
 *
 * @param {number} principal  Financed principal.
 * @param {number} annualRate Annual interest rate as a percentage (e.g., 6.5).
 * @param {number} termYears  Loan term in whole years.
 * @return {number} Monthly payment rounded to 2 decimals.
 */
export function calculateMonthlyPayment( principal, annualRate, termYears ) {
	const amount = clampFloat( principal, 0, ATTR_LIMITS.MAX_AMOUNT );
	const months =
		Math.round( clampFloat( termYears, 1, ATTR_LIMITS.MAX_TERM ) ) * 12;

	if ( amount <= 0 || months < 1 ) {
		return 0;
	}

	const monthlyRate = annualRate / 100 / 12;

	if ( monthlyRate <= 0 ) {
		return round2( amount / months );
	}

	return round2(
		( amount * monthlyRate ) / ( 1 - Math.pow( 1 + monthlyRate, -months ) )
	);
}

/**
 * Builds an annual amortization schedule, aggregated from monthly rows so it
 * matches the PHP implementation exactly.
 *
 * @param {number} principal  Financed principal.
 * @param {number} annualRate Annual interest rate as a percentage.
 * @param {number} termYears  Loan term in whole years.
 * @return {Array<{year: number, principal: number, interest: number, balance: number}>}
 *           One aggregated row per year of the loan term.
 */
export function buildAmortizationSchedule( principal, annualRate, termYears ) {
	const amount = clampFloat( principal, 0, ATTR_LIMITS.MAX_AMOUNT );
	const monthlyRate = annualRate / 100 / 12;
	const months =
		Math.round( clampFloat( termYears, 1, ATTR_LIMITS.MAX_TERM ) ) * 12;

	if ( amount <= 0 || months < 1 ) {
		return [];
	}

	const payment = calculateMonthlyPayment( amount, annualRate, termYears );
	let balance = amount;

	const schedule = [];
	for ( let month = 1; month <= months; month++ ) {
		const interest = round2( balance * monthlyRate );
		const principalPay = Math.min( round2( payment - interest ), balance );
		balance = round2( balance - principalPay );

		const yearIndex = Math.ceil( month / 12 );
		if ( ! schedule[ yearIndex - 1 ] ) {
			schedule[ yearIndex - 1 ] = {
				year: yearIndex,
				principal: 0,
				interest: 0,
				balance,
			};
		}

		schedule[ yearIndex - 1 ].principal += principalPay;
		schedule[ yearIndex - 1 ].interest += interest;
		schedule[ yearIndex - 1 ].balance = balance;
	}

	return schedule.map( ( row ) => ( {
		year: row.year,
		principal: round2( row.principal ),
		interest: round2( row.interest ),
		balance: round2( row.balance ),
	} ) );
}

/**
 * The recurring cost components, in display order.
 *
 * Kept in step with amortexa_get_cost_components() in helpers.php. The two must
 * agree, because one drives the server render and the other drives what happens
 * when a visitor types, and a disagreement shows up as the numbers jumping when
 * the page is cached.
 *
 * @type {Array<{key: string, attribute: string, label: string, defaultUnit: string}>}
 */
export const COST_COMPONENTS = [
	{
		key: 'tax',
		attribute: 'propertyTax',
		label: 'Property Tax',
		defaultUnit: 'percent',
	},
	{
		key: 'insurance',
		attribute: 'homeInsurance',
		label: 'Home Insurance',
		defaultUnit: 'amount',
	},
	{
		key: 'hoa',
		attribute: 'hoaFee',
		label: 'HOA Fee',
		defaultUnit: 'amount',
	},
	{
		key: 'pmi',
		attribute: 'pmi',
		label: 'PMI',
		defaultUnit: 'amount',
	},
	{
		key: 'other',
		attribute: 'otherCosts',
		label: 'Other Costs',
		defaultUnit: 'amount',
	},
];

/**
 * Adds the numeric values of every own property in an object.
 *
 * @param {Object} values Key => number map.
 * @return {number} Sum of the values.
 */
function sumValues( values ) {
	return Object.values( values ).reduce(
		( total, value ) => total + ( Number( value ) || 0 ),
		0
	);
}

/**
 * Converts the stored cost inputs into monthly figures.
 *
 * A component entered as a percentage is a share of the purchase price, which is
 * loanAmount in this block; one entered as an amount is already annual cash. The
 * unit is read per attribute rather than assumed, so a field labelled in dollars
 * is not silently multiplied by the price.
 *
 * @param {Object} attrs     Attribute bag.
 * @param {number} homePrice Purchase price the percentages apply to.
 * @return {Object.<string, number>} Component key => monthly amount.
 */
export function calculateMonthlyCosts( attrs, homePrice ) {
	const monthly = {};

	for ( const component of COST_COMPONENTS ) {
		monthly[ component.key ] = round2(
			annualCost( attrs, component, homePrice ) / 12
		);
	}

	return monthly;
}

/**
 * Converts one stored cost input into its annual figure.
 *
 * Split out from calculateMonthlyCosts() because PMI is also needed at full
 * annual size to work out its lifetime total, which is not simply a month times
 * twelve because the premium stops part way through the loan.
 *
 * @param {Object} attrs     Attribute bag.
 * @param {Object} component Entry from COST_COMPONENTS.
 * @param {number} homePrice Purchase price the percentages apply to.
 * @return {number} Annual cost, zero when nothing was entered.
 */
function annualCost( attrs, component, homePrice ) {
	const raw = Number( attrs[ component.attribute ] ) || 0;

	if ( ! ( raw > 0 ) ) {
		return 0;
	}

	const stored = attrs[ `${ component.attribute }Unit` ];

	/*
	 * Falling back to the declared default matters: a block nobody has touched
	 * still has to read the same here as it does on the server, where the unit
	 * defaults come from block.json. Assuming percent would turn an annual
	 * insurance figure into a share of the purchase price.
	 */
	const unit =
		'amount' === stored || 'percent' === stored
			? stored
			: component.defaultUnit;

	return 'amount' === unit ? raw : round2( ( homePrice * raw ) / 100 );
}

/**
 * Returns the first month the balance reaches 80% of the purchase price.
 *
 * Matches amortexa_get_pmi_end_month() in helpers.php, including the case of a
 * loan that starts under the threshold and therefore never carries PMI.
 *
 * @param {number} principal  Financed principal.
 * @param {number} homePrice  Purchase price.
 * @param {number} annualRate Annual rate percentage.
 * @param {number} months     Term in months.
 * @return {number} Month of cancellation, or 0 when PMI never applies.
 */
export function getPmiEndMonth( principal, homePrice, annualRate, months ) {
	if ( ! ( homePrice > 0 ) || ! ( principal > 0 ) || ! ( months > 0 ) ) {
		return 0;
	}

	if ( principal / homePrice <= 0.8 ) {
		return 0;
	}

	const monthlyRate = annualRate / 100 / 12;
	const payment = calculateMonthlyPayment(
		principal,
		annualRate,
		months / 12
	);
	const threshold = homePrice * 0.8;

	let balance = principal;

	for ( let month = 1; month <= months; month++ ) {
		const interest = balance * monthlyRate;
		const paid = Math.min( payment, balance + interest );

		balance -= Math.max( paid - interest, 0 );

		if ( balance <= threshold ) {
			return month;
		}
	}

	return 0;
}

/**
 * Runs the full calculation for a set of attribute-like values.
 *
 * @param {Object}  attrs                    Attribute bag.
 * @param {number}  attrs.loanAmount         Total loan amount.
 * @param {number}  attrs.downPayment        Down payment.
 * @param {number}  attrs.interestRate       Annual rate percentage.
 * @param {number}  attrs.loanTerm           Term in years.
 * @param {boolean} [attrs.showAmortization] Include schedule in result.
 * @return {{principal: number, monthlyPayment: number, totalPaid: number, totalInterest: number, monthlyCosts: Object, totalMonthlyCost: number, pmiEndMonth: number, totalPmi: number, totalCosts: number, totalOutOfPocket: number, schedule: Array}}
 *           Complete payment summary, with the schedule included unless opted out.
 */
export function calculateMortgage( attrs ) {
	const loanAmount = clampFloat(
		attrs.loanAmount,
		0,
		ATTR_LIMITS.MAX_AMOUNT
	);
	const downPayment = Math.min(
		clampFloat( attrs.downPayment, 0, ATTR_LIMITS.MAX_AMOUNT ),
		loanAmount
	);
	const principal = round2( Math.max( loanAmount - downPayment, 0 ) );

	const monthlyPayment = calculateMonthlyPayment(
		principal,
		attrs.interestRate,
		attrs.loanTerm
	);
	const totalPaid = round2(
		monthlyPayment * Math.round( attrs.loanTerm * 12 )
	);

	const months = Math.round( attrs.loanTerm * 12 );
	const monthlyCosts = calculateMonthlyCosts( attrs, loanAmount );
	const totalMonthlyCost = round2(
		monthlyPayment + sumValues( monthlyCosts )
	);

	/*
	 * PMI is the one component that stops part way through the loan, so its
	 * lifetime cost cannot be a month times twelve. The donut still shows the
	 * first month's premium, because that is the month a buyer sees.
	 */
	const pmiAnnual = annualCost(
		attrs,
		COST_COMPONENTS.find( ( component ) => 'pmi' === component.key ),
		loanAmount
	);
	const pmiEnd =
		pmiAnnual > 0
			? getPmiEndMonth(
					principal,
					loanAmount,
					attrs.interestRate,
					months
				)
			: 0;
	let pmiMonths = 0;

	if ( pmiEnd > 0 ) {
		pmiMonths = pmiEnd;
	} else if ( pmiAnnual > 0 ) {
		// A premium that never cancels runs for the rest of the term.
		pmiMonths = months;
	}
	const totalPmi = round2( pmiMonths * ( pmiAnnual / 12 ) );
	const otherAnnual = COST_COMPONENTS.filter(
		( component ) => 'pmi' !== component.key
	).reduce(
		( total, component ) =>
			total + annualCost( attrs, component, loanAmount ),
		0
	);
	const totalCosts = round2( totalPmi + otherAnnual * ( months / 12 ) );

	return {
		principal,
		monthlyPayment,
		totalPaid,
		totalInterest: round2( Math.max( totalPaid - principal, 0 ) ),
		monthlyCosts,
		totalMonthlyCost,
		pmiEndMonth: pmiEnd,
		totalPmi,
		totalCosts,
		totalOutOfPocket: round2( totalPaid + totalCosts ),
		schedule:
			attrs.showAmortization === false
				? []
				: buildAmortizationSchedule(
						principal,
						attrs.interestRate,
						attrs.loanTerm
					),
	};
}

/**
 * Rounds to two decimal places, matching PHP's round() for display purposes.
 *
 * @param {number} value Value to round.
 * @return {number} Rounded value.
 */
export function round2( value ) {
	return Math.round( ( value + Number.EPSILON ) * 100 ) / 100;
}
