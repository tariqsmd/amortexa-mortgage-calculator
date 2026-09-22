/**
 * Edit entry point for the MT Mortgage Calculator block.
 *
 * Computes the calculation up front and composes the inspector Controls and
 * the live Preview, attaching the block wrapper props/className/style.
 */

import { useBlockProps } from '@wordpress/block-editor';
import { useMemo, useRef } from '@wordpress/element';
import './../style.scss';
import './../editor.scss';
import {
	buildAmortizationSchedule,
	calculateMortgage,
} from '../utils/calculator';
import Controls, {
	COLOR_CONTROLS,
	FONT_FAMILIES,
	FONT_STACKS,
	SKINS,
} from './controls';
import Preview from './preview';

/**
 * Builds inline CSS custom property overrides from the per-block color and
 * font settings so they beat any skin in both specificity orders.
 *
 * @param {Object} attributes Current attribute values.
 * @param {string} fontFamily Active font-family attribute.
 * @return {Object|undefined} React style object with CSS variables.
 */
function getPaletteOverrides( attributes, fontFamily ) {
	const overrides = {};

	COLOR_CONTROLS.forEach( ( control ) => {
		const value = attributes[ control.key ];

		if ( value ) {
			overrides[ control.cssVar ] = value;
		}
	} );

	const stack =
		FONT_STACKS[
			FONT_FAMILIES.some( ( f ) => f.value === fontFamily )
				? fontFamily
				: 'inherit'
		];

	if ( stack ) {
		overrides.fontFamily = stack;
	}

	return Object.keys( overrides ).length ? overrides : undefined;
}

/**
 * Renders the block edit UI.
 *
 * @param {Object}   props               Component props.
 * @param {Object}   props.attributes    Current attribute values.
 * @param {Function} props.setAttributes Attribute updater.
 * @return {JSX.Element} Block edit interface.
 */
export default function Edit( { attributes, setAttributes } ) {
	const {
		loanAmount,
		interestRate,
		loanTerm,
		downPayment,
		theme,
		fontFamily,
		paymentFontSize,
		paymentFontWeight,
	} = attributes;

	const result = useMemo(
		() =>
			calculateMortgage( {
				loanAmount,
				downPayment,
				interestRate,
				loanTerm,
			} ),
		[ loanAmount, downPayment, interestRate, loanTerm ]
	);

	const chartSchedule = useMemo(
		() =>
			buildAmortizationSchedule(
				result.principal,
				interestRate,
				loanTerm
			),
		[ result.principal, interestRate, loanTerm ]
	);

	const rootRef = useRef( null );

	const blockProps = useBlockProps( {
		className: `mtgb-calc mtgb-theme-${
			SKINS.some( ( skin ) => skin.value === theme ) ? theme : 'light'
		}`,
		style: getPaletteOverrides( attributes, fontFamily ),
	} );

	const paymentTypography = {};

	if ( Number( paymentFontSize ) > 0 ) {
		paymentTypography.fontSize = `${ Number( paymentFontSize ) }px`;
	}

	if ( paymentFontWeight ) {
		paymentTypography.fontWeight = paymentFontWeight;
	}

	return (
		<>
			<Controls
				attributes={ attributes }
				setAttributes={ setAttributes }
			/>
			<div { ...blockProps } ref={ rootRef }>
				<Preview
					attributes={ attributes }
					setAttributes={ setAttributes }
					result={ result }
					chartSchedule={ chartSchedule }
					rootRef={ rootRef }
					paymentTypography={ paymentTypography }
				/>
			</div>
		</>
	);
}
