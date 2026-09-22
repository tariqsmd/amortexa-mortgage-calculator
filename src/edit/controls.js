/**
 * Inspector controls for the MT Mortgage Calculator block.
 *
 * Collects every attribute control into a single InspectorControls tree, and
 * shares the skin/font/color/numeric config maps with the preview module so the
 * editor UI can never drift from what is rendered.
 */

import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
import {
	BaseControl,
	ColorPalette,
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';

export const SKINS = [
	{
		value: 'light',
		label: __( 'Classic Light', 'mt-gutenberg-blocks' ),
	},
	{ value: 'dark', label: __( 'Elegant Dark', 'mt-gutenberg-blocks' ) },
	{ value: 'ocean', label: __( 'Ocean Blue', 'mt-gutenberg-blocks' ) },
	{
		value: 'sunset',
		label: __( 'Sunset Warm', 'mt-gutenberg-blocks' ),
	},
	{
		value: 'forest',
		label: __( 'Forest Green', 'mt-gutenberg-blocks' ),
	},
	{
		value: 'midnight',
		label: __( 'Midnight Violet', 'mt-gutenberg-blocks' ),
	},
	{
		value: 'rose',
		label: __( 'Rose Quartz', 'mt-gutenberg-blocks' ),
	},
	{
		value: 'slate',
		label: __( 'Minimal Slate', 'mt-gutenberg-blocks' ),
	},
	{
		value: 'grape',
		label: __( 'Royal Grape', 'mt-gutenberg-blocks' ),
	},
	{ value: 'aqua', label: __( 'Aqua Fresh', 'mt-gutenberg-blocks' ) },
	{
		value: 'mocha',
		label: __( 'Mocha Cream', 'mt-gutenberg-blocks' ),
	},
	{ value: 'cyber', label: __( 'Cyber Neon', 'mt-gutenberg-blocks' ) },
];

export const FONT_FAMILIES = [
	{
		value: 'inherit',
		label: __( 'Theme default', 'mt-gutenberg-blocks' ),
	},
	{
		value: 'sans',
		label: __( 'Modern Sans (system)', 'mt-gutenberg-blocks' ),
	},
	{
		value: 'serif',
		label: __( 'Classic Serif (system)', 'mt-gutenberg-blocks' ),
	},
	{
		value: 'mono',
		label: __( 'Monospace (system)', 'mt-gutenberg-blocks' ),
	},
];

export const FONT_STACKS = {
	inherit: '',
	sans: 'system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif',
	serif: 'Georgia, "Times New Roman", Times, serif',
	mono: 'ui-monospace, "SF Mono", "Cascadia Code", Consolas, Menlo, monospace',
};

export const COLOR_CONTROLS = [
	{
		key: 'accentColor',
		label: __( 'Accent', 'mt-gutenberg-blocks' ),
		cssVar: '--mtgb-accent',
	},
	{
		key: 'accentAltColor',
		label: __( 'Secondary accent (charts)', 'mt-gutenberg-blocks' ),
		cssVar: '--mtgb-accent-2',
	},
	{
		key: 'labelColor',
		label: __( 'Label text', 'mt-gutenberg-blocks' ),
		cssVar: '--mtgb-label-color',
	},
	{
		key: 'fieldTextColor',
		label: __( 'Field text', 'mt-gutenberg-blocks' ),
		cssVar: '--mtgb-field-text',
	},
	{
		key: 'fieldBackgroundColor',
		label: __( 'Field background', 'mt-gutenberg-blocks' ),
		cssVar: '--mtgb-field-bg',
	},
	{
		key: 'fieldBorderColor',
		label: __( 'Field border', 'mt-gutenberg-blocks' ),
		cssVar: '--mtgb-field-border',
	},
];

export const NUMERIC_FIELDS = [
	{
		key: 'loanAmount',
		label: __( 'Loan Amount', 'mt-gutenberg-blocks' ),
		min: 0,
		max: undefined,
		step: 'any',
		sliderMin: 10000,
		sliderMax: 2000000,
		sliderStep: 5000,
	},
	{
		key: 'downPayment',
		label: __( 'Down Payment', 'mt-gutenberg-blocks' ),
		min: 0,
		max: undefined,
		step: 'any',
		sliderMin: 0,
		sliderMaxFrom: 'loanAmount',
		sliderStep: 2500,
	},
	{
		key: 'interestRate',
		label: __( 'Interest Rate (%)', 'mt-gutenberg-blocks' ),
		min: 0,
		max: 100,
		step: '0.01',
		sliderMin: 0,
		sliderMax: 20,
		sliderStep: 0.05,
	},
	{
		key: 'loanTerm',
		label: __( 'Term (Years)', 'mt-gutenberg-blocks' ),
		min: 1,
		max: 60,
		step: 1,
		sliderMin: 1,
		sliderMax: 40,
		sliderStep: 1,
	},
];

const FONT_WEIGHTS = [ '300', '400', '500', '600', '700', '800' ];

const CHART_TYPES = [
	{ value: 'both', label: __( 'Both Charts', 'mt-gutenberg-blocks' ) },
	{
		value: 'donut',
		label: __( 'Donut Only', 'mt-gutenberg-blocks' ),
	},
	{ value: 'line', label: __( 'Line Only', 'mt-gutenberg-blocks' ) },
];

const CURRENCY_POSITIONS = [
	{
		value: 'prefix',
		label: __( 'Before amount ($99)', 'mt-gutenberg-blocks' ),
	},
	{
		value: 'suffix',
		label: __( 'After amount (99 €)', 'mt-gutenberg-blocks' ),
	},
];

const WEIGHT_OPTIONS = [
	{ value: '', label: __( 'Theme default', 'mt-gutenberg-blocks' ) },
].concat(
	[ '300', '400', '500', '600', '700', '800' ].map( ( weight ) => {
		const names = {
			300: 'Light',
			400: 'Normal',
			500: 'Medium',
			600: 'Semi Bold',
			700: 'Bold',
			800: 'Extra Bold',
		};
		return {
			value: weight,
			label: `${ names[ weight ] } (${ weight })`,
		};
	} )
);

/**
 * Renders the sidebar inspector controls for every block attribute.
 *
 * @param {Object}   props               Component props.
 * @param {Object}   props.attributes    Current attribute values.
 * @param {Function} props.setAttributes Attribute updater.
 * @return {JSX.Element} InspectorControls tree.
 */
export default function Controls( { attributes, setAttributes } ) {
	const {
		currencySymbol,
		currencyPosition,
		showResults,
		showSliders,
		showCharts,
		showAmortization,
		chartType,
		theme,
		fontFamily,
		paymentFontSize,
		paymentFontWeight,
	} = attributes;

	const setNumericAttribute = ( key, raw ) => {
		setAttributes( {
			[ key ]: raw === '' ? 0 : parseFloat( raw ) || 0,
		} );
	};

	return (
		<InspectorControls>
			<PanelBody
				title={ __(
					'Calculator Settings',
					'mt-gutenberg-blocks'
				) }
			>
				{ NUMERIC_FIELDS.map( ( field ) => (
					<TextControl
						key={ field.key }
						type="number"
						label={ field.label }
						value={ String( attributes[ field.key ] ?? '' ) }
						min={ field.min }
						max={ field.max }
						step={ field.step }
						onChange={ ( value ) =>
							setNumericAttribute( field.key, value )
						}
					/>
				) ) }
				<TextControl
					label={ __(
						'Currency Symbol',
						'mt-gutenberg-blocks'
					) }
					value={ currencySymbol }
					maxLength={ 8 }
					onChange={ ( value ) =>
						setAttributes( { currencySymbol: value.slice( 0, 8 ) } )
					}
				/>
				<SelectControl
					label={ __(
						'Currency Position',
						'mt-gutenberg-blocks'
					) }
					value={
						[ 'prefix', 'suffix' ].includes( currencyPosition )
							? currencyPosition
							: 'prefix'
					}
					options={ CURRENCY_POSITIONS }
					onChange={ ( value ) =>
						setAttributes( { currencyPosition: value } )
					}
				/>
			</PanelBody>

			<PanelBody title={ __( 'Display', 'mt-gutenberg-blocks' ) }>
				<ToggleControl
					label={ __(
						'Show Results Summary',
						'mt-gutenberg-blocks'
					) }
					checked={ showResults }
					onChange={ ( value ) =>
						setAttributes( { showResults: value } )
					}
				/>
				<ToggleControl
					label={ __( 'Show Sliders', 'mt-gutenberg-blocks' ) }
					checked={ showSliders }
					onChange={ ( value ) =>
						setAttributes( { showSliders: value } )
					}
				/>
				<ToggleControl
					label={ __( 'Show Charts', 'mt-gutenberg-blocks' ) }
					checked={ showCharts }
					onChange={ ( value ) =>
						setAttributes( { showCharts: value } )
					}
				/>
				<SelectControl
					label={ __( 'Chart Type', 'mt-gutenberg-blocks' ) }
					value={
						[ 'donut', 'line', 'both' ].includes( chartType )
							? chartType
							: 'both'
					}
					options={ CHART_TYPES }
					onChange={ ( value ) =>
						setAttributes( { chartType: value } )
					}
				/>
				<ToggleControl
					label={ __(
						'Show Amortization Table',
						'mt-gutenberg-blocks'
					) }
					checked={ showAmortization }
					onChange={ ( value ) =>
						setAttributes( { showAmortization: value } )
					}
				/>
				<SelectControl
					label={ __( 'Skin', 'mt-gutenberg-blocks' ) }
					value={
						SKINS.some( ( skin ) => skin.value === theme )
							? theme
							: 'light'
					}
					options={ SKINS }
					onChange={ ( value ) => setAttributes( { theme: value } ) }
				/>
			</PanelBody>

			<PanelBody
				title={ __( 'Colors', 'mt-gutenberg-blocks' ) }
				initialOpen={ false }
			>
				<p>
					{ __(
						'Leave a color empty to use the selected skin.',
						'mt-gutenberg-blocks'
					) }
				</p>
				{ COLOR_CONTROLS.map( ( control ) => (
					<BaseControl
						key={ control.key }
						id={ `mtgb-color-${ control.key }` }
						label={ control.label }
					>
						<ColorPalette
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
				title={ __( 'Typography', 'mt-gutenberg-blocks' ) }
				initialOpen={ false }
			>
				<SelectControl
					label={ __( 'Font Family', 'mt-gutenberg-blocks' ) }
					value={ fontFamily }
					options={ FONT_FAMILIES }
					onChange={ ( value ) =>
						setAttributes( { fontFamily: value } )
					}
				/>
				<TextControl
					type="number"
					label={ __(
						'Payment Font Size (px, 0 = theme default)',
						'mt-gutenberg-blocks'
					) }
					value={ String( Number( paymentFontSize ) || 0 ) }
					min={ 0 }
					max={ 120 }
					onChange={ ( value ) =>
						setAttributes( {
							paymentFontSize: parseFloat( value ) || 0,
						} )
					}
				/>
				<SelectControl
					label={ __(
						'Payment Font Weight',
						'mt-gutenberg-blocks'
					) }
					value={
						FONT_WEIGHTS.includes( paymentFontWeight )
							? paymentFontWeight
							: ''
					}
					options={ WEIGHT_OPTIONS }
					onChange={ ( value ) =>
						setAttributes( { paymentFontWeight: value } )
					}
				/>
			</PanelBody>
		</InspectorControls>
	);
}
