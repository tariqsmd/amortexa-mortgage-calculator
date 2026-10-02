/**
 * Access to the option lists that PHP hands to the editor.
 *
 * The settings screen, the attribute sanitizer, and this inspector all need the
 * same lists (skins, chart types, fonts, colors). Rather than duplicating them
 * in JavaScript, PHP passes them in via the `amortexaData` global, so adding a
 * skin means editing exactly one place. The fallbacks below only exist so the
 * compiled bundle still behaves if the global is ever missing.
 */

const FALLBACK_SKINS = [
	'light',
	'dark',
	'ocean',
	'sunset',
	'forest',
	'midnight',
	'rose',
	'slate',
	'grape',
	'aqua',
	'mocha',
	'cyber',
	'emerald',
	'crimson',
	'charcoal',
	'copper',
	'royal',
	'amber',
	'cobalt',
	'fuchsia',
	'mint',
	'sand',
	'lemon',
	'steel',
];

/**
 * Returns the data object PHP printed before the editor script.
 *
 * Exported so other utils can read parts of it, such as the design token
 * schema, without each re-implementing the global lookup.
 *
 * @return {Object} Editor data, or an empty object when unavailable.
 */
export function getData() {
	if ( typeof window === 'undefined' ) {
		return {};
	}

	return window.amortexaData || {};
}

/**
 * Converts a PHP map into SelectControl options.
 *
 * @param {Object|undefined} map      Value => label map.
 * @param {Array}            fallback Fallback options when the map is missing.
 * @return {Array<{value: string, label: string}>} Control options.
 */
function toOptions( map, fallback = [] ) {
	if ( ! map || typeof map !== 'object' ) {
		return fallback;
	}

	return Object.keys( map ).map( ( value ) => ( {
		value,
		label: map[ value ],
	} ) );
}

/**
 * Returns the selectable skins.
 *
 * @return {Array<{value: string, label: string}>} Skin options.
 */
export function getSkins() {
	const { skins } = getData();

	if ( Array.isArray( skins ) && skins.length ) {
		return skins;
	}

	return FALLBACK_SKINS.map( ( value ) => ( { value, label: value } ) );
}

/**
 * Returns the skin slugs currently known to the plugin.
 *
 * @return {Array<string>} Skin slugs.
 */
export function getSkinSlugs() {
	return getSkins().map( ( skin ) => skin.value );
}

/**
 * Returns the chart type options.
 *
 * @return {Array<{value: string, label: string}>} Chart options.
 */
export function getChartTypes() {
	return toOptions( getData().chartTypes, [
		{ value: 'both', label: 'both' },
		{ value: 'donut', label: 'donut' },
		{ value: 'line', label: 'line' },
		{ value: 'bar', label: 'bar' },
		{ value: 'dots', label: 'dots' },
	] );
}

/**
 * Returns the layout options.
 *
 * @return {Array<{value: string, label: string}>} Layout options.
 */
export function getLayouts() {
	return toOptions( getData().layouts, [
		{ value: 'stacked', label: 'stacked' },
		{ value: 'split', label: 'split' },
	] );
}

/**
 * Returns the form column treatment options.
 *
 * @return {Array<{value: string, label: string}>} Form column options.
 */
export function getFormColumns() {
	return toOptions( getData().formColumns, [
		{ value: 'wide', label: 'wide' },
		{ value: 'compact', label: 'compact' },
	] );
}

/**
 * Returns the font family options.
 *
 * @return {Array<{value: string, label: string}>} Font family options.
 */
export function getFontFamilies() {
	return toOptions( getData().fontFamilies, [
		{ value: 'inherit', label: 'inherit' },
		{ value: 'sans', label: 'sans' },
		{ value: 'serif', label: 'serif' },
		{ value: 'mono', label: 'mono' },
	] );
}

/**
 * Returns the font weight options.
 *
 * @return {Array<{value: string, label: string}>} Font weight options.
 */
export function getFontWeights() {
	return toOptions( getData().fontWeights, [
		{ value: '', label: '' },
		{ value: '300', label: '300' },
		{ value: '400', label: '400' },
		{ value: '500', label: '500' },
		{ value: '600', label: '600' },
		{ value: '700', label: '700' },
		{ value: '800', label: '800' },
	] );
}

/**
 * Returns the currency position options.
 *
 * @return {Array<{value: string, label: string}>} Currency position options.
 */
export function getCurrencyPositions() {
	return toOptions( getData().currencyPosition, [
		{ value: 'prefix', label: 'prefix' },
		{ value: 'suffix', label: 'suffix' },
	] );
}

/**
 * Returns the swatches shown inside the inspector colour pickers.
 *
 * ColorPalette renders an empty popover without a `colors` list, so the presets
 * come from PHP (amortexa_get_color_swatches) with a local fallback.
 *
 * @return {Array<{name: string, color: string}>} Swatch list.
 */
export function getColorSwatches() {
	const { colorSwatches } = getData();

	if ( Array.isArray( colorSwatches ) && colorSwatches.length ) {
		return colorSwatches;
	}

	return [
		{ name: 'Forest', color: '#1a6f4b' },
		{ name: 'Amber', color: '#d97706' },
		{ name: 'Red', color: '#b91c1c' },
		{ name: 'Violet', color: '#7c3aed' },
		{ name: 'Blue', color: '#1d4ed8' },
		{ name: 'Cyan', color: '#0e7490' },
		{ name: 'Ink', color: '#111827' },
		{ name: 'Gray', color: '#6b7280' },
		{ name: 'Mist', color: '#f3f4f6' },
		{ name: 'White', color: '#ffffff' },
	];
}

/**
 * Returns the per-block color control descriptors.
 *
 * @return {Array<{key: string, label: string, cssVar: string}>} Color controls.
 */
export function getColorControls() {
	const { colors } = getData();

	if ( Array.isArray( colors ) && colors.length ) {
		return colors;
	}

	return [
		{ key: 'accentColor', label: 'Accent', cssVar: '--amortexa-accent' },
		{
			key: 'accentAltColor',
			label: 'Secondary accent',
			cssVar: '--amortexa-accent-alt',
		},
		{ key: 'labelColor', label: 'Label text', cssVar: '--amortexa-label' },
		{
			key: 'fieldTextColor',
			label: 'Field text',
			cssVar: '--amortexa-field-text',
		},
		{
			key: 'fieldBackgroundColor',
			label: 'Field background',
			cssVar: '--amortexa-field-bg',
		},
		{
			key: 'fieldBorderColor',
			label: 'Field border',
			cssVar: '--amortexa-field-border',
		},
	];
}

/**
 * Returns the CSS font stacks keyed by font family.
 *
 * @return {Object<string,string>} Font key => CSS font-family value.
 */
export function getFontStacks() {
	const { fontStacks } = getData();

	if ( fontStacks && typeof fontStacks === 'object' ) {
		return fontStacks;
	}

	return { inherit: '', sans: '', serif: '', mono: '' };
}

/**
 * Returns the site-wide defaults injected into the block at registration.
 *
 * Used to tell the editor which values it inherited rather than set itself.
 *
 * @return {Object} Default attribute values, keyed by block attribute name.
 */
export function getSiteDefaults() {
	const { siteDefaults } = getData();

	return siteDefaults && typeof siteDefaults === 'object' ? siteDefaults : {};
}
