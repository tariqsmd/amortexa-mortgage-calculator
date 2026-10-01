/**
 * Standalone Node driver for the JS mortgage math.
 *
 * Loads the real shared module (src/utils/calculator.js) via a data URL so it
 * is evaluated as an ES module regardless of the package's module type, then
 * prints a deterministic JSON snapshot of the same test matrix used by
 * tests/parity.php. Output format:
 *
 *   { "monthly": [...], "schedule": [...], "costs": [...] }
 *
 * Run with: node tests/js/calc.mjs
 */

import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const here = dirname( fileURLToPath( import.meta.url ) );
const source = readFileSync(
	join( here, '../../src/utils/calculator.js' ),
	'utf8'
);

const mod = await import(
	'data:text/javascript;base64,' + Buffer.from( source ).toString( 'base64' )
);

const CASES = [
	{ principal: 200000, rate: 6.0, years: 30 },
	{ principal: 250000, rate: 0.0, years: 30 },
	{ principal: 100000, rate: 3.5, years: 15 },
	{ principal: 500000, rate: 7.25, years: 20 },
	{ principal: 45000, rate: 5.875, years: 10 },
];

const monthly = CASES.map( ( c ) =>
	mod.calculateMonthlyPayment( c.principal, c.rate, c.years )
);

const schedule = CASES.map( ( c ) =>
	mod.buildAmortizationSchedule( c.principal, c.rate, c.years )
);

/*
 * Cost scenarios run through the full entry point, because the interest rate,
 * the purchase price and the unit of each field all have to agree between the
 * server render and the live recalculation.
 */
const COST_CASES = [
	{
		loanAmount: 400000,
		downPayment: 80000,
		interestRate: 7.455,
		loanTerm: 30,
		propertyTax: 1.2,
		homeInsurance: 1500,
		otherCosts: 4000,
	},
	{
		loanAmount: 400000,
		downPayment: 40000,
		interestRate: 7.0,
		loanTerm: 30,
		pmi: 1200,
		pmiUnit: 'amount',
	},
	{
		loanAmount: 300000,
		interestRate: 6.5,
		loanTerm: 30,
	},
	{
		loanAmount: 250000,
		downPayment: 25000,
		interestRate: 5.5,
		loanTerm: 15,
		propertyTax: 1.8,
		propertyTaxUnit: 'percent',
		hoaFee: 3600,
		hoaFeeUnit: 'amount',
		homeInsurance: 2,
		homeInsuranceUnit: 'amount',
		pmi: 0.5,
		pmiUnit: 'percent',
		otherCosts: 1200,
		otherCostsUnit: 'amount',
	},
];

const costs = COST_CASES.map( ( attrs ) => {
	const result = mod.calculateMortgage( {
		...attrs,
		showAmortization: false,
	} );

	return {
		monthlyPayment: result.monthlyPayment,
		monthlyCosts: result.monthlyCosts,
		totalMonthlyCost: result.totalMonthlyCost,
		pmiEndMonth: result.pmiEndMonth,
		totalPmi: result.totalPmi,
		totalCosts: result.totalCosts,
		totalOutOfPocket: result.totalOutOfPocket,
	};
} );

process.stdout.write(
	JSON.stringify( { monthly, schedule, costs } )
);
