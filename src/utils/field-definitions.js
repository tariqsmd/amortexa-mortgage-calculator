/**
 * Numeric loan field definitions.
 *
 * Shared by the block inspector and the editor preview so a field's input
 * constraints and its slider bounds are defined exactly once.
 */

import { __ } from '@wordpress/i18n';
import { COST_COMPONENTS } from './calculator';

/**
 * The four numeric loan inputs.
 *
 * `min`/`max`/`step` bound what the browser accepts for the number input.
 * `sliderMin`/`sliderMax`/`sliderStep` are display bounds for the range slider
 * only, so a visitor can drag through sensible values without the number input
 * rejecting larger legitimate amounts. `sliderMaxFrom` derives the slider's upper
 * bound from another attribute (a down payment can never exceed the loan).
 *
 * The labels are byte-identical to the `$fields` array in src/render.php, and
 * tests/parity.php asserts it. They name the unit even though the `help` text
 * below already does, because the sidebar is not where an author has to read it
 * twice to connect "Interest Rate" to the "4.75" sitting next to it on the front
 * end. One wording everywhere also means the sidebar shows an author exactly
 * what a visitor will see.
 */
export const NUMERIC_FIELDS = [
	{
		key: 'loanAmount',
		label: __( 'Loan Amount', 'amortexa-mortgage-calculator' ),
		help: __(
			'Total amount being financed.',
			'amortexa-mortgage-calculator'
		),
		min: 0,
		step: 'any',
		sliderMin: 10000,
		sliderMax: 2000000,
		sliderStep: 5000,
	},
	{
		key: 'downPayment',
		label: __( 'Down Payment', 'amortexa-mortgage-calculator' ),
		help: __(
			'Paid up front. Interest is charged on the remainder.',
			'amortexa-mortgage-calculator'
		),
		min: 0,
		step: 'any',
		sliderMin: 0,
		sliderMaxFrom: 'loanAmount',
		sliderStep: 2500,
	},
	{
		key: 'interestRate',
		label: __( 'Interest Rate (%)', 'amortexa-mortgage-calculator' ),
		help: __(
			'Annual rate as a percentage.',
			'amortexa-mortgage-calculator'
		),
		min: 0,
		max: 100,
		step: '0.01',
		sliderMin: 0,
		sliderMax: 20,
		sliderStep: 0.05,
	},
	{
		key: 'loanTerm',
		label: __( 'Term (Years)', 'amortexa-mortgage-calculator' ),
		help: __(
			'Length of the loan in years.',
			'amortexa-mortgage-calculator'
		),
		min: 1,
		max: 60,
		step: 1,
		sliderMin: 1,
		sliderMax: 40,
		sliderStep: 1,
	},
];

/**
 * Labels for the recurring cost components.
 *
 * Written as literals rather than read from COST_COMPONENTS so the i18n
 * extractor sees each string; that module carries the schema, not the
 * translations.
 */
const COST_LABELS = {
	tax: __( 'Property Tax', 'amortexa-mortgage-calculator' ),
	insurance: __( 'Home Insurance', 'amortexa-mortgage-calculator' ),
	hoa: __( 'HOA Fee', 'amortexa-mortgage-calculator' ),
	pmi: __( 'PMI', 'amortexa-mortgage-calculator' ),
	other: __( 'Other Costs', 'amortexa-mortgage-calculator' ),
};

/**
 * The recurring cost inputs, paired with their unit attribute.
 *
 * Shared by the block inspector and the editor preview so the sidebar and the
 * canvas list the same components in the same order with the same translated
 * labels. `unitKey` names the attribute that stores 'percent' or 'amount'.
 */
export const COST_FIELDS = COST_COMPONENTS.map( ( component ) => ( {
	...component,
	label: COST_LABELS[ component.key ] || component.label,
	unitKey: `${ component.attribute }Unit`,
} ) );
