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
import { getSkinSlugs } from './utils/editor-data';
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
		paymentFontSize,
		paymentFontWeight,
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

	// useBlockProps() hands back its own ref, which the editor uses to locate the
	// block node. Spreading the props and then adding `ref={ rootRef }` would
	// silently drop that ref, so the two are merged instead.
	const { ref: blockRef, ...restBlockProps } = useBlockProps( {
		className: `calcforge-calc calcforge-theme-${
			getSkinSlugs().includes( theme ) ? theme : 'light'
		} calcforge-calc--layout-${ layout === 'split' ? 'split' : 'stacked' }${
			formColumns === 'compact'
				? ' calcforge-calc--form-columns-compact'
				: ''
		}`,
		style: getPaletteOverrides( attributes, fontFamily ),
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

	if ( Number( paymentFontSize ) > 0 ) {
		paymentTypography.fontSize = `${ Number( paymentFontSize ) }px`;
	}

	if ( paymentFontWeight ) {
		paymentTypography.fontWeight = paymentFontWeight;
	}

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
				/>
			</div>
		</>
	);
}
