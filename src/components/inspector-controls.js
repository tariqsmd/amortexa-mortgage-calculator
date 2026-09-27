/**
 * Block inspector controls.
 *
 * Every attribute declared in block.json is editable here. Option lists come from
 * PHP (see utils/editor-data) so the inspector, the settings screen, and the
 * server-side sanitizer always agree.
 */

import { InspectorControls } from '@wordpress/block-editor';
import {
	BaseControl,
	Button,
	ColorPalette,
	PanelBody,
	PanelRow,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
	getChartTypes,
	getColorControls,
	getColorSwatches,
	getCurrencyPositions,
	getFontFamilies,
	getFontStacks,
	getFontWeights,
	getSiteDefaults,
	getSkins,
} from '../utils/editor-data';
import { NUMERIC_FIELDS } from '../utils/field-definitions';

/**
 * The numeric loan inputs.
 *
 * `min`/`max`/`step` bound what the browser accepts; the server re-clamps every
 * value in calcforge_sanitize_attributes(), so these are a convenience only.
 */

/**
 * Builds the inline CSS custom properties for the per-block colour and font
 * overrides, so they win over the active skin in both specificity orders.
 *
 * @param {Object} attributes Current attribute values.
 * @param {string} fontFamily Active font-family attribute.
 * @return {Object|undefined} React style object, or undefined when nothing is overridden.
 */
export function getPaletteOverrides( attributes, fontFamily ) {
	const overrides = {};

	getColorControls().forEach( ( control ) => {
		const value = attributes[ control.key ];

		if ( value ) {
			overrides[ control.cssVar ] = value;
		}
	} );

	const families = getFontFamilies().map( ( family ) => family.value );
	const key = families.includes( fontFamily ) ? fontFamily : 'inherit';
	const stack = getFontStacks()[ key ];

	if ( stack ) {
		overrides.fontFamily = stack;
	}

	return Object.keys( overrides ).length ? overrides : undefined;
}

/**
 * Renders the block settings sidebar.
 *
 * @param {Object}   props               Component props.
 * @param {Object}   props.attributes    Current attribute values.
 * @param {Function} props.setAttributes Attribute updater.
 * @return {JSX.Element} InspectorControls tree.
 */
export default function CalculatorInspector( { attributes, setAttributes } ) {
	const defaults = getSiteDefaults();

	const setNumber = ( key, raw ) => {
		setAttributes( { [ key ]: raw === '' ? 0 : parseFloat( raw ) || 0 } );
	};

	const setToggle = ( key ) => ( value ) =>
		setAttributes( { [ key ]: value } );

	const setSelect = ( key ) => ( value ) =>
		setAttributes( { [ key ]: value } );

	const skinOptions = getSkins();
	const skinValues = skinOptions.map( ( skin ) => skin.value );

	return (
		<InspectorControls>
			<PanelBody
				title={ __( 'Calculator Settings', 'calcforge' ) }
				initialOpen
			>
				{ NUMERIC_FIELDS.map( ( field ) => (
					<TextControl
						key={ field.key }
						__nextHasNoMarginBottom
						type="number"
						label={ field.label }
						help={
							field.help ||
							__(
								'Inherited from Settings → CalcForge.',
								'calcforge'
							)
						}
						value={ String( attributes[ field.key ] ?? '' ) }
						min={ field.min }
						max={ field.max }
						step={ field.step }
						onChange={ ( value ) => setNumber( field.key, value ) }
					/>
				) ) }

				<TextControl
					__nextHasNoMarginBottom
					label={ __( 'Currency Symbol', 'calcforge' ) }
					help={ __(
						'Up to 8 characters, shown next to every amount.',
						'calcforge'
					) }
					value={ attributes.currencySymbol ?? '' }
					maxLength={ 8 }
					onChange={ ( value ) =>
						setAttributes( {
							currencySymbol: value.slice( 0, 8 ),
						} )
					}
				/>

				<SelectControl
					__nextHasNoMarginBottom
					label={ __( 'Currency Position', 'calcforge' ) }
					value={
						getCurrencyPositions().some(
							( option ) =>
								option.value === attributes.currencyPosition
						)
							? attributes.currencyPosition
							: 'prefix'
					}
					options={ getCurrencyPositions() }
					onChange={ setSelect( 'currencyPosition' ) }
				/>
			</PanelBody>

			<PanelBody
				title={ __( 'Display', 'calcforge' ) }
				initialOpen={ false }
			>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Show Results Summary', 'calcforge' ) }
					checked={ !! attributes.showResults }
					onChange={ setToggle( 'showResults' ) }
				/>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Show Sliders', 'calcforge' ) }
					help={ __(
						'Front-end visitors can drag the sliders.',
						'calcforge'
					) }
					checked={ !! attributes.showSliders }
					onChange={ setToggle( 'showSliders' ) }
				/>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Show Charts', 'calcforge' ) }
					checked={ !! attributes.showCharts }
					onChange={ setToggle( 'showCharts' ) }
				/>

				<SelectControl
					__nextHasNoMarginBottom
					label={ __( 'Chart Type', 'calcforge' ) }
					value={
						getChartTypes().some(
							( option ) => option.value === attributes.chartType
						)
							? attributes.chartType
							: 'both'
					}
					options={ getChartTypes() }
					onChange={ setSelect( 'chartType' ) }
				/>

				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Show Amortization Table', 'calcforge' ) }
					checked={ !! attributes.showAmortization }
					onChange={ setToggle( 'showAmortization' ) }
				/>

				<SelectControl
					__nextHasNoMarginBottom
					label={ __( 'Skin', 'calcforge' ) }
					value={
						skinValues.includes( attributes.theme )
							? attributes.theme
							: skinValues[ 0 ]
					}
					options={ skinOptions }
					onChange={ setSelect( 'theme' ) }
				/>
			</PanelBody>

			<PanelBody
				title={ __( 'Colors', 'calcforge' ) }
				initialOpen={ false }
			>
				<p className="calcforge-inspector__note">
					{ __(
						'Leave a colour empty to use the selected skin.',
						'calcforge'
					) }
				</p>
				{ getColorControls().map( ( control ) => (
					<BaseControl
						key={ control.key }
						__nextHasNoMarginBottom
						id={ `calcforge-color-${ control.key }` }
						label={ control.label }
					>
						<ColorPalette
							colors={ getColorSwatches() }
							value={ attributes[ control.key ] || undefined }
							onChange={ ( value ) =>
								setAttributes( {
									[ control.key ]: value || '',
								} )
							}
						/>
					</BaseControl>
				) ) }
			</PanelBody>

			<PanelBody
				title={ __( 'Typography', 'calcforge' ) }
				initialOpen={ false }
			>
				<SelectControl
					__nextHasNoMarginBottom
					label={ __( 'Font Family', 'calcforge' ) }
					value={
						getFontFamilies().some(
							( option ) => option.value === attributes.fontFamily
						)
							? attributes.fontFamily
							: 'inherit'
					}
					options={ getFontFamilies() }
					onChange={ setSelect( 'fontFamily' ) }
				/>

				<TextControl
					__nextHasNoMarginBottom
					type="number"
					label={ __( 'Payment Font Size', 'calcforge' ) }
					help={ __(
						'In pixels. Use 0 for the skin default.',
						'calcforge'
					) }
					value={ String(
						Number( attributes.paymentFontSize ) || 0
					) }
					min={ 0 }
					max={ 120 }
					onChange={ ( value ) =>
						setAttributes( {
							paymentFontSize: parseFloat( value ) || 0,
						} )
					}
				/>

				<SelectControl
					__nextHasNoMarginBottom
					label={ __( 'Payment Font Weight', 'calcforge' ) }
					value={
						getFontWeights().some(
							( option ) =>
								option.value === attributes.paymentFontWeight
						)
							? attributes.paymentFontWeight
							: ''
					}
					options={ getFontWeights() }
					onChange={ setSelect( 'paymentFontWeight' ) }
				/>
			</PanelBody>

			{ Object.keys( defaults ).length > 0 && (
				<PanelBody
					title={ __( 'Site Defaults', 'calcforge' ) }
					initialOpen={ false }
				>
					<PanelRow>
						<p className="calcforge-inspector__note">
							{ __(
								'These are the values this site starts new calculators with. Reset to go back to them.',
								'calcforge'
							) }
						</p>
					</PanelRow>
					<PanelRow>
						<Button
							variant="secondary"
							onClick={ () => setAttributes( { ...defaults } ) }
						>
							{ __( 'Reset to Site Defaults', 'calcforge' ) }
						</Button>
					</PanelRow>
				</PanelBody>
			) }
		</InspectorControls>
	);
}
