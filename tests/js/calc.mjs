/**
 * Standalone Node driver for the JS mortgage math.
 *
 * Loads the real shared module (src/utils/calculator.js) via a data URL so it
 * is evaluated as an ES module regardless of the package's module type, then
 * prints a deterministic JSON snapshot of the same test matrix used by
 * tests/parity.php. Output format:
 *
 *   { "monthly": [...], "schedule": [...] }
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

process.stdout.write(
	JSON.stringify( { monthly, schedule } )
);
