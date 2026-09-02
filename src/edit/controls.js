/**
 * Inspector controls for the Mortgage Calculator block.
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
		label: __( 'Classic Light', 'mortgage-calculator-block' ),
	},
	{ value: 'dark', label: __( 'Elegant Dark', 'mortgage-calculator-block' ) },
	{ value: 'ocean', label: __( 'Ocean Blue', 'mortgage-calculator-block' ) },
	{
		value: 'sunset',
		label: __( 'Sunset Warm', 'mortgage-calculator-block' ),
	},
	{
		value: 'forest',
		label: __( 'Forest Green', 'mortgage-calculator-block' ),
	},
	{
		value: 'midnight',
		label: __( 'Midnight Violet', 'mortgage-calculator-block' ),
	},
	{
		value: 'rose',
		label: __( 'Rose Quartz', 'mortgage-calculator-block' ),
	},
	{
		value: 'slate',
		label: __( 'Minimal Slate', 'mortgage-calculator-block' ),
	},
	{
		value: 'grape',
		label: __( 'Royal Grape', 'mortgage-calculator-block' ),
	},
	{ value: 'aqua', label: __( 'Aqua Fresh', 'mortgage-calculator-block' ) },
	{
		value: 'mocha',
		label: __( 'Mocha Cream', 'mortgage-calculator-block' ),
	},
	{ value: 'cyber', label: __( 'Cyber Neon', 'mortgage-calculator-block' ) },
];

export const FONT_FAMILIES = [
	{
		value: 'inherit',
		label: __( 'Theme default', 'mortgage-calculator-block' ),
	},
	{
		value: 'sans',
		label: __( 'Modern Sans (system)', 'mortgage-calculator-block' ),
	},
	{
		value: 'serif',
		label: __( 'Classic Serif (system)', 'mortgage-calculator-block' ),
	},
	{
		value: 'mono',
		label: __( 'Monospace (system)', 'mortgage-calculator-block' ),
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
		label: __( 'Accent', 'mortgage-calculator-block' ),
		cssVar: '--mcb-accent',
	},
	{
		key: 'accentAltColor',
		label: __( 'Secondary accent (charts)', 'mortgage-calculator-block' ),
		cssVar: '--mcb-accent-2',
	},
	{
		key: 'labelColor',
		label: __( 'Label text', 'mortgage-calculator-block' ),
		cssVar: '--mcb-label-color',
	},
	{
		key: 'fieldTextColor',
		label: __( 'Field text', 'mortgage-calculator-block' ),
		cssVar: '--mcb-field-text',
	},
	{
		key: 'fieldBackgroundColor',
		label: __( 'Field background', 'mortgage-calculator-block' ),
		cssVar: '--mcb-field-bg',
	},
	{
		key: 'fieldBorderColor',
		label: __( 'Field border', 'mortgage-calculator-block' ),
		cssVar: '--mcb-field-border',
	},
];

export const NUMERIC_FIELDS = [
	{
		key: 'loanAmount',
		label: __( 'Loan Amount', 'mortgage-calculator-block' ),
		min: 0,
		max: undefined,
		step: 'any',
		sliderMin: 10000,
		sliderMax: 2000000,
		sliderStep: 5000,
	},
	{
		key: 'downPayment',
		label: __( 'Down Payment', 'mortgage-calculator-block' ),
		min: 0,
		max: undefined,
		step: 'any',
		sliderMin: 0,
		sliderMaxFrom: 'loanAmount',
		sliderStep: 2500,
	},
	{
		key: 'interestRate',
		label: __( 'Interest Rate (%)', 'mortgage-calculator-block' ),
		min: 0,
		max: 100,
		step: '0.01',
		sliderMin: 0,
		sliderMax: 20,
		sliderStep: 0.05,
	},
	{
		key: 'loanTerm',
		label: __( 'Term (Years)', 'mortgage-calculator-block' ),
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
	{ value: 'both', label: __( 'Both Charts', 'mortgage-calculator-block' ) },
	{
		value: 'donut',
		label: __( 'Donut Only', 'mortgage-calculator-block' ),
	},
	{ value: 'line', label: __( 'Line Only', 'mortgage-calculator-block' ) },
];

const CURRENCY_POSITIONS = [
	{
		value: 'prefix',
		label: __( 'Before amount ($99)', 'mortgage-calculator-block' ),
	},
	{
		value: 'suffix',
		label: __( 'After amount (99 €)', 'mortgage-calculator-block' ),
	},
];

const WEIGHT_OPTIONS = [
	{ value: '', label: __( 'Theme default', 'mortgage-calculator-block' ) },
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
					'mortgage-calculator-block'
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
						'mortgage-calculator-block'
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
						'mortgage-calculator-block'
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

			<PanelBody title={ __( 'Display', 'mortgage-calculator-block' ) }>
				<ToggleControl
					label={ __(
						'Show Results Summary',
						'mortgage-calculator-block'
					) }
					checked={ showResults }
					onChange={ ( value ) =>
						setAttributes( { showResults: value } )
					}
				/>
				<ToggleControl
					label={ __( 'Show Sliders', 'mortgage-calculator-block' ) }
					checked={ showSliders }
					onChange={ ( value ) =>
						setAttributes( { showSliders: value } )
					}
				/>
				<ToggleControl
					label={ __( 'Show Charts', 'mortgage-calculator-block' ) }
					checked={ showCharts }
					onChange={ ( value ) =>
						setAttributes( { showCharts: value } )
					}
				/>
				<SelectControl
					label={ __( 'Chart Type', 'mortgage-calculator-block' ) }
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
						'mortgage-calculator-block'
					) }
					checked={ showAmortization }
					onChange={ ( value ) =>
						setAttributes( { showAmortization: value } )
					}
				/>
				<SelectControl
					label={ __( 'Skin', 'mortgage-calculator-block' ) }
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
				title={ __( 'Colors', 'mortgage-calculator-block' ) }
				initialOpen={ false }
			>
				<p>
					{ __(
						'Leave a color empty to use the selected skin.',
						'mortgage-calculator-block'
					) }
				</p>
				{ COLOR_CONTROLS.map( ( control ) => (
					<BaseControl
						key={ control.key }
						id={ `mcb-color-${ control.key }` }
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
				title={ __( 'Typography', 'mortgage-calculator-block' ) }
				initialOpen={ false }
			>
				<SelectControl
					label={ __( 'Font Family', 'mortgage-calculator-block' ) }
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
						'mortgage-calculator-block'
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
						'mortgage-calculator-block'
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
