/**
 * Edit entry point for the Mortgage Calculator block.
 *
 * Computes the calculation up front and composes the inspector controls and the
 * live preview, attaching the block wrapper props/className/style.
 */

import { useBlockProps } from '@wordpress/block-editor';
import { useCallback, useMemo, useRef } from '@wordpress/element';
import {
	buildAmortizationSchedule,
	calculateMortgage,
} from './utils/calculator';
import { getLayouts, getSkinSlugs } from './utils/editor-data';
import InspectorControls, {
	getPaletteOverrides,
} from './components/inspector-controls';
import Preview from './components/preview';

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
		layout,
		formColumns,
		fontFamily,
		propertyTax,
		propertyTaxUnit,
		homeInsurance,
		homeInsuranceUnit,
		hoaFee,
		hoaFeeUnit,
		pmi,
		pmiUnit,
		otherCosts,
		otherCostsUnit,
	} = attributes;

	const result = useMemo(
		() =>
			calculateMortgage( {
				loanAmount,
				downPayment,
				interestRate,
				loanTerm,
				propertyTax,
				propertyTaxUnit,
				homeInsurance,
				homeInsuranceUnit,
				hoaFee,
				hoaFeeUnit,
				pmi,
				pmiUnit,
				otherCosts,
				otherCostsUnit,
			} ),
		[
			loanAmount,
			downPayment,
			interestRate,
			loanTerm,
			propertyTax,
			propertyTaxUnit,
			homeInsurance,
			homeInsuranceUnit,
			hoaFee,
			hoaFeeUnit,
			pmi,
			pmiUnit,
			otherCosts,
			otherCostsUnit,
		]
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

	// The chart preview reads colours back out of the computed CSS custom
	// properties, so a skin or palette change has to invalidate the chart
	// effect. Serialising the overrides gives Preview a stable, primitive
	// dependency that changes only when a colour token actually changes.
	const paletteOverrides = getPaletteOverrides( attributes, fontFamily );
	const paletteKey = JSON.stringify( paletteOverrides || {} );

	// useBlockProps() hands back its own ref, which the editor uses to locate the
	// block node. Spreading the props and then adding `ref={ rootRef }` would
	// silently drop that ref, so the two are merged instead.
	const { ref: blockRef, ...restBlockProps } = useBlockProps( {
		className: `amortexa-calc amortexa-theme-${
			getSkinSlugs().includes( theme ) ? theme : 'light'
		} amortexa-calc--layout-${
			getLayouts().some( ( option ) => option.value === layout )
				? layout
				: 'stacked'
		}${
			formColumns === 'compact'
				? ' amortexa-calc--form-columns-compact'
				: ''
		}`,
		style: paletteOverrides,
	} );

	const setBlockRef = useCallback(
		( node ) => {
			rootRef.current = node;

			if ( typeof blockRef === 'function' ) {
				blockRef( node );
			} else if ( blockRef ) {
				blockRef.current = node;
			}
		},
		[ blockRef ]
	);

	const paymentTypography = {};

	return (
		<>
			<InspectorControls
				attributes={ attributes }
				setAttributes={ setAttributes }
			/>
			<div { ...restBlockProps } ref={ setBlockRef }>
				<Preview
					attributes={ attributes }
					setAttributes={ setAttributes }
					result={ result }
					chartSchedule={ chartSchedule }
					rootRef={ rootRef }
					paymentTypography={ paymentTypography }
					paletteKey={ paletteKey }
				/>
			</div>
		</>
	);
}
