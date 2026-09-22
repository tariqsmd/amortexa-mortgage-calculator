/**
 * Pure mortgage math shared between the block editor preview and the
 * front-end view script.
 *
 * This module mirrors the authoritative PHP helpers in includes/helpers.php
 * (mtgb_calculate_monthly_payment / mtgb_calculate_amortization_schedule) so the
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
 * Runs the full calculation for a set of attribute-like values.
 *
 * @param {Object}  attrs                    Attribute bag.
 * @param {number}  attrs.loanAmount         Total loan amount.
 * @param {number}  attrs.downPayment        Down payment.
 * @param {number}  attrs.interestRate       Annual rate percentage.
 * @param {number}  attrs.loanTerm           Term in years.
 * @param {boolean} [attrs.showAmortization] Include schedule in result.
 * @return {{principal: number, monthlyPayment: number, totalPaid: number, totalInterest: number, schedule: Array}}
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

	return {
		principal,
		monthlyPayment,
		totalPaid,
		totalInterest: round2( Math.max( totalPaid - principal, 0 ) ),
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
function round2( value ) {
	return Math.round( ( value + Number.EPSILON ) * 100 ) / 100;
}
