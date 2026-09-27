/**
 * Numeric loan field definitions.
 *
 * Shared by the block inspector and the editor preview so a field's input
 * constraints and its slider bounds are defined exactly once.
 */

import { __ } from '@wordpress/i18n';

/**
 * The four numeric loan inputs.
 *
 * `min`/`max`/`step` bound what the browser accepts for the number input.
 * `sliderMin`/`sliderMax`/`sliderStep` are display bounds for the range slider
 * only, so a visitor can drag through sensible values without the number input
 * rejecting larger legitimate amounts. `sliderMaxFrom` derives the slider's upper
 * bound from another attribute (a down payment can never exceed the loan).
 */
export const NUMERIC_FIELDS = [
	{
		key: 'loanAmount',
		label: __( 'Loan Amount', 'calcforge' ),
		help: __( 'Total amount being financed.', 'calcforge' ),
		min: 0,
		step: 'any',
		sliderMin: 10000,
		sliderMax: 2000000,
		sliderStep: 5000,
	},
	{
		key: 'downPayment',
		label: __( 'Down Payment', 'calcforge' ),
		help: __(
			'Paid up front. Interest is charged on the remainder.',
			'calcforge'
		),
		min: 0,
		step: 'any',
		sliderMin: 0,
		sliderMaxFrom: 'loanAmount',
		sliderStep: 2500,
	},
	{
		key: 'interestRate',
		label: __( 'Interest Rate', 'calcforge' ),
		help: __( 'Annual rate as a percentage.', 'calcforge' ),
		min: 0,
		max: 100,
		step: '0.01',
		sliderMin: 0,
		sliderMax: 20,
		sliderStep: 0.05,
	},
	{
		key: 'loanTerm',
		label: __( 'Loan Term', 'calcforge' ),
		help: __( 'Length of the loan in years.', 'calcforge' ),
		min: 1,
		max: 60,
		step: 1,
		sliderMin: 1,
		sliderMax: 40,
		sliderStep: 1,
	},
];
