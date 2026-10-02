/**
 * The design token schema, as handed to the editor by PHP.
 *
 * The token list itself is never duplicated here. `includes/design-tokens.php` is
 * the single source of truth and ships the groups through the `amortexaData`
 * global, exactly like the skin and font lists. That is deliberate: the two
 * hand-maintained copies that used to exist drifted apart, so the editor wrote
 * CSS variable names the stylesheet never read and two colour controls silently
 * did nothing in the editor while working on the frontend.
 *
 * `tests/parity.php` fails if the schema PHP sends and the schema this module
 * reads ever disagree about the token keys, so the mirror cannot rot.
 */

import { getData } from './editor-data';

/**
 * Returns the design groups from PHP.
 *
 * @return {Array<{key: string, label: string, summary: string, tokens: Array<Object>}>} Groups.
 */
export function getDesignGroups() {
	const { designGroups } = getData();

	return Array.isArray( designGroups ) ? designGroups : [];
}

/**
 * Flattens the groups into a key => token map.
 *
 * @return {Object<string,Object>} Tokens keyed by design key.
 */
export function getDesignTokenMap() {
	const map = {};

	getDesignGroups().forEach( ( group ) => {
		( group.tokens || [] ).forEach( ( token ) => {
			map[ token.key ] = { ...token, group: group.key };
		} );
	} );

	return map;
}

/**
 * Returns every token, flattened.
 *
 * @return {Array<Object>} Token definitions.
 */
export function getDesignTokens() {
	return Object.values( getDesignTokenMap() );
}

/**
 * Converts a stored token value into the CSS declaration value.
 *
 * Mirrors amortexa_design_css_value() in PHP: lengths and spacing gain a
 * pixel unit, colours and selects are emitted verbatim.
 *
 * @param {Object} token Token definition.
 * @param {string} value Stored value.
 * @return {string} CSS value.
 */
export function designCssValue( token, value ) {
	if ( 'length' === token.type ) {
		return `${ value }px`;
	}

	if ( 'spacing' === token.type ) {
		return value
			.split( /\s+/ )
			.filter( Boolean )
			.map( ( part ) => `${ part }px` )
			.join( ' ' );
	}

	return value;
}

/**
 * Clamps a number into the token's declared range.
 *
 * @param {number} value Value to clamp.
 * @param {number} min   Lower bound.
 * @param {number} max   Upper bound.
 * @return {number} Clamped value.
 */
function clamp( value, min, max ) {
	return Math.min( max, Math.max( min, value ) );
}

/**
 * Normalises one raw token value into the string that should be emitted.
 *
 * The editor holds unsaved attribute values, which can be out of range or junk
 * until the block is saved, and the server sanitizes again on render. Without
 * this the preview would show a 9999px chart and a negative swatch that the
 * published page silently corrects. Mirrors amortexa_sanitize_design_value()
 * in PHP, and tests/parity.php asserts the two stay identical.
 *
 * @param {Object} token Token definition.
 * @param {*}      raw   Untrusted value straight off the attribute.
 * @return {string} Sanitized value, or an empty string to inherit.
 */
export function sanitizeDesignValue( token, raw ) {
	if ( null === raw || undefined === raw || 'object' === typeof raw ) {
		return '';
	}

	const value = String( raw ).trim();

	if ( '' === value ) {
		return '';
	}

	const min = 'number' === typeof token.min ? token.min : 0;
	const max = 'number' === typeof token.max ? token.max : 9999;

	if ( 'color' === token.type ) {
		if ( 'transparent' === value.toLowerCase() ) {
			return 'transparent';
		}

		const hex = /^#?(?:[0-9a-f]{3}|[0-9a-f]{6})$/i.test( value )
			? `#${ value.replace( '#', '' ) }`
			: '';

		return hex;
	}

	if ( 'length' === token.type ) {
		if ( ! isNumeric( value ) ) {
			return '';
		}

		return String( Math.round( clamp( parseFloat( value ), min, max ) ) );
	}

	if ( 'spacing' === token.type ) {
		const kept = value
			.split( /\s+/ )
			.filter( isNumeric )
			.slice( 0, 4 )
			.map( ( part ) =>
				Math.round( clamp( parseFloat( part ), 0, max ) )
			);

		// No usable parts means the value was junk, so inherit rather than clear.
		return kept.length ? kept.join( ' ' ) : '';
	}

	if ( 'select' === token.type ) {
		// Options arrive as ordered pairs, so membership is a linear scan.
		const options = token.options || [];

		return options.some( ( option ) => option.value === value )
			? value
			: '';
	}

	return '';
}

/**
 * Whether a string is a plain number, matching PHP's is_numeric() closely
 * enough for the token types the schema uses.
 *
 * @param {string} value Candidate.
 * @return {boolean} True when numeric.
 */
function isNumeric( value ) {
	return /^-?\d*\.?\d+$/.test( String( value ) );
}

/**
 * Resolves the design object against the legacy per-block colour attributes.
 *
 * A token wins when set; otherwise the older attribute it replaced is honoured,
 * so a block saved before the design tab existed keeps its colours instead of
 * reverting to the skin. Mirrors amortexa_get_design_values() in PHP.
 *
 * @param {Object} attributes Block attributes.
 * @return {Object<string,string>} Resolved key => value.
 */
export function resolveDesignValues( attributes ) {
	const values = {};
	const design = ( attributes && attributes.design ) || {};
	const tokens = getDesignTokenMap();

	Object.keys( tokens ).forEach( ( key ) => {
		const token = tokens[ key ];
		const own = sanitizeDesignValue( token, design[ key ] );

		if ( '' !== own ) {
			values[ key ] = own;

			return;
		}

		if ( ! token.legacy ) {
			return;
		}

		const legacy = attributes ? attributes[ token.legacy ] : '';
		const resolved = sanitizeDesignValue( token, legacy );

		if ( '' !== resolved ) {
			values[ key ] = resolved;
		}
	} );

	return values;
}

/**
 * Builds the inline custom property overrides for the design tokens.
 *
 * Returned as a React style object so it can be spread straight into the block
 * wrapper, which puts it in the same inline style the PHP render writes on the
 * front end. The inline declaration always beats the stylesheet and the
 * responsive token redefinitions, so a value set here is never discarded.
 *
 * @param {Object} attributes Block attributes.
 * @return {Object} React style object.
 */
export function getDesignOverrides( attributes ) {
	const overrides = {};
	const values = resolveDesignValues( attributes );
	const tokens = getDesignTokenMap();

	Object.keys( values ).forEach( ( key ) => {
		const token = tokens[ key ];

		if ( ! token || ! token.var ) {
			return;
		}

		overrides[ token.var ] = designCssValue( token, values[ key ] );
	} );

	return overrides;
}

/**
 * Reads one stored spacing value as up to four numbers.
 *
 * The uniform case is a single value; expanding to a per-side box simply fills
 * the array out, so one token and one custom property serve both.
 *
 * @param {string} value Stored spacing value.
 * @return {Array<number>} One to four numbers.
 */
export function parseSpacing( value ) {
	if ( typeof value !== 'string' || '' === value.trim() ) {
		return [];
	}

	return value
		.trim()
		.split( /\s+/ )
		.map( ( part ) => parseInt( part, 10 ) )
		.filter( ( part ) => Number.isFinite( part ) )
		.slice( 0, 4 );
}

/**
 * Serialises up to four numbers back into a stored spacing value.
 *
 * @param {Array<number>} sides One to four numbers.
 * @return {string} Stored value.
 */
export function serializeSpacing( sides ) {
	return sides
		.map( ( side ) => Math.round( Number( side ) || 0 ) )
		.join( ' ' );
}

/**
 * Returns the resolved chart height, or zero when the token is unset.
 *
 * The charts are SVG, so the preview needs a number rather than a CSS length.
 *
 * @param {Object} attributes Block attributes.
 * @return {number} Height in SVG user units, or 0 to use the default.
 */
export function getChartHeight( attributes ) {
	const values = resolveDesignValues( attributes );
	const height = parseInt( values.chartHeight, 10 );

	return Number.isFinite( height ) && height >= 120 && height <= 560
		? height
		: 0;
}
