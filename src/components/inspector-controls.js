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
	PanelBody,
	PanelRow,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import {
	getChartTypes,
	getColorControls,
	getCurrencyPositions,
	getFormColumns,
	getFontFamilies,
	getFontStacks,
	getFontWeights,
	getLayouts,
	getSiteDefaults,
	getSkins,
} from '../utils/editor-data';
import { NUMERIC_FIELDS } from '../utils/field-definitions';
import { PANEL_KEYS, movePanel, resolvePanelOrder } from '../utils/panel-order';
import { getDesignOverrides } from '../utils/design';
import DesignControls from './design-controls';

/**
 * Display names for the reorderable panels.
 *
 * Kept here rather than in utils/panel-order so the labels pass through the
 * translator in the editor bundle like every other inspector string.
 */
const PANEL_LABELS = {
	form: __( 'Inputs', 'amortexa-mortgage-calculator' ),
	results: __( 'Results', 'amortexa-mortgage-calculator' ),
	charts: __( 'Charts', 'amortexa-mortgage-calculator' ),
	schedule: __( 'Schedule', 'amortexa-mortgage-calculator' ),
};

/**
 * Returns a translated panel name, falling back to the raw key.
 *
 * @param {string} panel Panel key.
 * @return {string} Panel name.
 */
function panelLabel( panel ) {
	return PANEL_LABELS[ panel ] || panel;
}

/**
 * The numeric loan inputs.
 *
 * `min`/`max`/`step` bound what the browser accepts; the server re-clamps every
 * value in amortexa_sanitize_attributes(), so these are a convenience only.
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
	const overrides = getDesignOverrides( attributes );

	getColorControls().forEach( ( control ) => {
		const value = attributes[ control.key ];

		// A design token for the same variable takes precedence, so setting the
		// token in the Design tab is not undone by the older colour attribute.
		if ( value && ! overrides[ control.cssVar ] ) {
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

	// The saved order, with any missing or hand-edited key repaired by the same
	// resolver the preview and PHP render use. Passing every panel as visible
	// keeps hidden panels in the list so reordering stays predictable when a
	// panel is toggled back on.
	const panelOrder = resolvePanelOrder( attributes.panelOrder, PANEL_KEYS );

	// Drag state, kept separate from the saved order so an abandoned drag
	// cannot leave the attribute half-moved.
	const [ dragIndex, setDragIndex ] = useState( null );
	const [ overIndex, setOverIndex ] = useState( null );

	const moveTo = ( from, to ) => {
		setAttributes( { panelOrder: movePanel( panelOrder, from, to ) } );
	};

	const onDragStart = ( event, index ) => {
		setDragIndex( index );
		setOverIndex( index );

		// Firefox refuses to start a drag unless some data is set.
		event.dataTransfer.effectAllowed = 'move';
		event.dataTransfer.setData( 'text/plain', panelOrder[ index ] );
	};

	const onDragOver = ( event, index ) => {
		// Without preventDefault the element is not a valid drop target.
		event.preventDefault();
		event.dataTransfer.dropEffect = 'move';

		if ( overIndex !== index ) {
			setOverIndex( index );
		}
	};

	const onDrop = ( event, index ) => {
		event.preventDefault();

		if ( null !== dragIndex ) {
			moveTo( dragIndex, index );
		}

		setDragIndex( null );
		setOverIndex( null );
	};

	const onDragEnd = () => {
		setDragIndex( null );
		setOverIndex( null );
	};

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __(
						'Calculator Settings',
						'amortexa-mortgage-calculator'
					) }
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
									'Inherited from Settings → Amortexa.',
									'amortexa-mortgage-calculator'
								)
							}
							value={ String( attributes[ field.key ] ?? '' ) }
							min={ field.min }
							max={ field.max }
							step={ field.step }
							onChange={ ( value ) =>
								setNumber( field.key, value )
							}
						/>
					) ) }

					<TextControl
						__nextHasNoMarginBottom
						label={ __(
							'Currency Symbol',
							'amortexa-mortgage-calculator'
						) }
						help={ __(
							'Up to 8 characters, shown next to every amount.',
							'amortexa-mortgage-calculator'
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
						label={ __(
							'Currency Position',
							'amortexa-mortgage-calculator'
						) }
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
					title={ __( 'Display', 'amortexa-mortgage-calculator' ) }
					initialOpen={ false }
				>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Show Results Summary',
							'amortexa-mortgage-calculator'
						) }
						checked={ !! attributes.showResults }
						onChange={ setToggle( 'showResults' ) }
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Show Sliders',
							'amortexa-mortgage-calculator'
						) }
						help={ __(
							'Front-end visitors can drag the sliders.',
							'amortexa-mortgage-calculator'
						) }
						checked={ !! attributes.showSliders }
						onChange={ setToggle( 'showSliders' ) }
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Show Charts',
							'amortexa-mortgage-calculator'
						) }
						checked={ !! attributes.showCharts }
						onChange={ setToggle( 'showCharts' ) }
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Show Taxes & Costs',
							'amortexa-mortgage-calculator'
						) }
						help={ __(
							'Adds property tax, insurance, HOA, PMI and other recurring costs, and reports the true total monthly cost alongside the principal and interest payment.',
							'amortexa-mortgage-calculator'
						) }
						checked={ !! attributes.showCosts }
						onChange={ setToggle( 'showCosts' ) }
					/>

					<SelectControl
						__nextHasNoMarginBottom
						label={ __(
							'Chart Type',
							'amortexa-mortgage-calculator'
						) }
						value={
							getChartTypes().some(
								( option ) =>
									option.value === attributes.chartType
							)
								? attributes.chartType
								: 'both'
						}
						options={ getChartTypes() }
						onChange={ setSelect( 'chartType' ) }
					/>

					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Layout', 'amortexa-mortgage-calculator' ) }
						help={ __(
							'Two column split places the inputs beside the results on wide screens.',
							'amortexa-mortgage-calculator'
						) }
						value={
							getLayouts().some(
								( option ) => option.value === attributes.layout
							)
								? attributes.layout
								: 'stacked'
						}
						options={ getLayouts() }
						onChange={ setSelect( 'layout' ) }
					/>

					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Show Amortization Table',
							'amortexa-mortgage-calculator'
						) }
						checked={ !! attributes.showAmortization }
						onChange={ setToggle( 'showAmortization' ) }
					/>

					<SelectControl
						__nextHasNoMarginBottom
						label={ __(
							'Form Columns',
							'amortexa-mortgage-calculator'
						) }
						help={ __(
							'Full width gives each control the whole row. Compact packs more controls per row and drops each input below its slider, which shortens a single column calculator but makes a two column split taller.',
							'amortexa-mortgage-calculator'
						) }
						value={
							getFormColumns().some(
								( option ) =>
									option.value === attributes.formColumns
							)
								? attributes.formColumns
								: 'wide'
						}
						options={ getFormColumns() }
						onChange={ setSelect( 'formColumns' ) }
					/>

					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Skin', 'amortexa-mortgage-calculator' ) }
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
					title={ __(
						'Panel Order',
						'amortexa-mortgage-calculator'
					) }
					initialOpen={ false }
				>
					<BaseControl
						__nextHasNoMarginBottom
						id="amortexa-panel-order"
						label={ __(
							'Panel Order',
							'amortexa-mortgage-calculator'
						) }
						help={ __(
							'Drag a section to move it, or use the up and down buttons. Every section can go anywhere, including the inputs.',
							'amortexa-mortgage-calculator'
						) }
					>
						<ul className="amortexa-reorder">
							{ panelOrder.map( ( panel, index ) => (
								<li
									key={ panel }
									className={ `amortexa-reorder__row${
										dragIndex === index
											? ' amortexa-reorder__row--dragging'
											: ''
									}${
										null !== overIndex &&
										overIndex === index &&
										dragIndex !== index
											? ' amortexa-reorder__row--over'
											: ''
									}` }
									draggable
									onDragStart={ ( event ) =>
										onDragStart( event, index )
									}
									onDragOver={ ( event ) =>
										onDragOver( event, index )
									}
									onDrop={ ( event ) =>
										onDrop( event, index )
									}
									onDragEnd={ onDragEnd }
								>
									<span
										className="amortexa-reorder__handle"
										aria-hidden="true"
									>
										&#8942;&#8942;
									</span>
									<span className="amortexa-reorder__name">
										{ panelLabel( panel ) }
									</span>
									<Button
										className="amortexa-reorder__button"
										variant="tertiary"
										disabled={ 0 === index }
										onClick={ () =>
											moveTo( index, index - 1 )
										}
										label={ sprintf(
											/* translators: %s: panel name, e.g. "Results". */
											__(
												'Move %s up',
												'amortexa-mortgage-calculator'
											),
											panelLabel( panel )
										) }
									>
										{ __(
											'Up',
											'amortexa-mortgage-calculator'
										) }
									</Button>
									<Button
										className="amortexa-reorder__button"
										variant="tertiary"
										disabled={
											panelOrder.length - 1 === index
										}
										onClick={ () =>
											moveTo( index, index + 1 )
										}
										label={ sprintf(
											/* translators: %s: panel name, e.g. "Results". */
											__(
												'Move %s down',
												'amortexa-mortgage-calculator'
											),
											panelLabel( panel )
										) }
									>
										{ __(
											'Down',
											'amortexa-mortgage-calculator'
										) }
									</Button>
								</li>
							) ) }
						</ul>
					</BaseControl>
				</PanelBody>

				<PanelBody
					title={ __( 'Typography', 'amortexa-mortgage-calculator' ) }
					initialOpen={ false }
				>
					<SelectControl
						__nextHasNoMarginBottom
						label={ __(
							'Font Family',
							'amortexa-mortgage-calculator'
						) }
						value={
							getFontFamilies().some(
								( option ) =>
									option.value === attributes.fontFamily
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
						label={ __(
							'Payment Font Size',
							'amortexa-mortgage-calculator'
						) }
						help={ __(
							'In pixels. Use 0 for the skin default.',
							'amortexa-mortgage-calculator'
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
						label={ __(
							'Payment Font Weight',
							'amortexa-mortgage-calculator'
						) }
						value={
							getFontWeights().some(
								( option ) =>
									option.value ===
									attributes.paymentFontWeight
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
						title={ __(
							'Site Defaults',
							'amortexa-mortgage-calculator'
						) }
						initialOpen={ false }
					>
						<PanelRow>
							<p className="amortexa-inspector__note">
								{ __(
									'These are the values this site starts new calculators with. Reset to go back to them.',
									'amortexa-mortgage-calculator'
								) }
							</p>
						</PanelRow>
						<PanelRow>
							<Button
								variant="secondary"
								onClick={ () =>
									setAttributes( { ...defaults } )
								}
							>
								{ __(
									'Reset to Site Defaults',
									'amortexa-mortgage-calculator'
								) }
							</Button>
						</PanelRow>
					</PanelBody>
				) }
			</InspectorControls>

			{ /*
			 * The Design tab, separate from the calculator settings so behaviour and
			 * appearance stay distinct. Gutenberg gives every `group` its own tab in
			 * the block sidebar.
			 */ }
			<InspectorControls group="styles">
				<DesignControls
					attributes={ attributes }
					setAttributes={ setAttributes }
				/>
			</InspectorControls>
		</>
	);
}
