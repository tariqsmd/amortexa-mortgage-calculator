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
		/* Starts at 0, not at a round-looking 10000, so a loan below that
		 * still gets a thumb in the right place rather than pinned left. */
		sliderMin: 0,
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
		/*
		 * Deliberately short of the input's max of 100: nobody borrows at 100%
		 * and a track spanning 0-100 spends its whole width on rates no
		 * mortgage reaches. sliderBoundsFor() stretches the track to cover a
		 * larger typed value, so the two ends never disagree.
		 */
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
		/* Matches the input's max, so every accepted term is reachable by drag. */
		sliderMax: 60,
		sliderStep: 1,
	},
];

/**
 * Returns a slider track that can represent `value`.
 *
 * A range input silently clamps its value to min/max, so a track narrower than
 * the number input beside it does not refuse the value -- it hides it, leaving
 * the thumb pinned against an end with no indication that a larger or smaller
 * number is sitting in the input. The reader then has two controls showing
 * different things, which is the whole reason the paired slider exists.
 *
 * So the track is widened to cover the value rather than the value being forced
 * into the track. The two rules that keep this honest:
 *
 * - It only ever widens, never contracts. Shrinking the track back would move
 *   the thumb while someone is mid-drag.
 * - A field whose max comes from another attribute (`sliderMaxFrom`) is left
 *   alone. That bound encodes a real constraint -- a down payment cannot exceed
 *   the loan -- so it is enforced by clamping the input, not by stretching the
 *   track until the constraint stops meaning anything.
 *
 * Shared by the front end and the editor preview so both agree on where the
 * thumb belongs.
 *
 * @param {Object}        field         Field definition from NUMERIC_FIELDS.
 * @param {string|number} value         Current value of the paired number input.
 * @param {number}        [resolvedMax] Slider max when the field derives one.
 * @return {{min: number, max: number}} Track bounds.
 */
export function sliderBoundsFor( field, value, resolvedMax ) {
	if ( field.sliderMaxFrom ) {
		return {
			min: field.sliderMin,
			max: Number.isFinite( Number( resolvedMax ) )
				? Number( resolvedMax )
				: field.sliderMin,
		};
	}

	const current = Number( value );

	return {
		min: Math.min(
			field.sliderMin,
			Number.isFinite( current ) ? current : field.sliderMin
		),
		max: Math.max(
			field.sliderMax,
			Number.isFinite( current ) ? current : field.sliderMin
		),
	};
}

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
