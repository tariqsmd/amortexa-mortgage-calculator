/**
 * Standalone Node driver for the design token schema.
 *
 * Loads the real editor modules (src/utils/editor-data.js and
 * src/utils/design.js) via data URLs, exactly as tests/js/calc.mjs does for the
 * calculator, so the functions under test are the ones the editor actually uses
 * rather than a copy. The schema itself is injected from PHP, so both languages
 * are asked to interpret the same token list.
 *
 * Usage: node tests/js/design.mjs <editor-data.json>
 *
 * Prints JSON:
 *
 *   { "groups": [...], "cssValues": {...}, "sanitized": {...}, "overrides": {...} }
 */

import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const here = dirname( fileURLToPath( import.meta.url ) );
const src = ( relative ) =>
	readFileSync( join( here, '../../src/utils/', relative ), 'utf8' );

const asModule = ( source ) =>
	'data:text/javascript;base64,' +
	Buffer.from( source ).toString( 'base64' );

/*
 * The schema arrives as a file path rather than an environment variable,
 * because a shell_exec() on Windows goes through cmd.exe, which cannot set an
 * inline variable, and the payload is larger than the command line limit.
 */
const payloadPath = process.argv[ 2 ];

if ( ! payloadPath ) {
	process.stderr.write( 'usage: node tests/js/design.mjs <editor-data.json>\n' );
	process.exit( 1 );
}

/*
 * editor-data.js reads the PHP payload off the window global, and imports
 * nothing, so it loads on its own. design.js imports it, so its specifier is
 * rewritten to point at the already-loaded module.
 */
globalThis.window = globalThis.window || {};
globalThis.window.calcforgeData = JSON.parse( readFileSync( payloadPath, 'utf8' ) );

const editorDataUrl = asModule( src( 'editor-data.js' ) );
await import( editorDataUrl );

// The base64 payload cannot contain a quote, so re-quoting the specifier is safe.
const design = await import(
	asModule(
		src( 'design.js' ).replace( "'./editor-data'", "'" + editorDataUrl + "'" )
	)
);

/*
 * A deterministic sample value per token type. PHP mirrors this in
 * tests/parity.php; if the two ever disagree about a sample the comparison
 * still holds because each side reports what it produced for the same input.
 */
function sampleFor( token ) {
	switch ( token.type ) {
		case 'color':
			return '#1d4ed8';
		case 'length':
			return String( Math.min( token.max, Math.max( token.min, 18 ) ) );
		case 'spacing':
			return '8 12 16 20';
		case 'select': {
			// Options are ordered pairs, so the first non-empty value wins.
			const options = token.options || [];

			const found = options.find(
				( option ) => option && '' !== option.value
			);

			return found ? found.value : '';
		}
		default:
			return '';
	}
}

const groups = design.getDesignGroups();
const tokens = design.getDesignTokenMap();

const cssValues = {};
Object.keys( tokens ).forEach( ( key ) => {
	const token = tokens[ key ];
	const sample = sampleFor( token );

	cssValues[ key ] = sample
		? design.designCssValue( token, sample )
		: '';
} );

/*
 * A deliberately messy design object: valid values, junk values, unknown keys,
 * out of range numbers and legacy colour attributes at once. Both languages
 * sanitize the same input.
 */
const messy = {
	fieldPadding: '10 14',
	fieldBg: '#0f766e',
	fieldBgJunk: '#zzzzzz',
	chartHeight: '9999',
	labelWeight: 'not-a-weight',
	legendSwatchSize: '-4',
	notAToken: '#fff',
	legacyOnlyColor: '',
};

const attributes = {
	design: messy,
	// A legacy per-block attribute that the design tab layered on top of.
	labelColor: '#be123c',
};

const sanitized = {};
Object.keys( messy ).forEach( ( key ) => {
	const token = tokens[ key ];

	if ( ! token ) {
		sanitized[ key ] = null;
		return;
	}

	const raw = messy[ key ];
	const value = design.resolveDesignValues( {
		design: { [ key ]: String( raw ) },
	} )[ key ];

	sanitized[ key ] = key in messy ? value || null : null;
} );

const resolvedAll = design.resolveDesignValues( attributes );
const overrides = design.getDesignOverrides( attributes );

/*
 * Resetting a token has to hand the block back to its legacy attribute, not to
 * the skin, otherwise a per-block colour set before the design tab existed would
 * be lost the first time someone touched the new tab.
 */
const afterReset = design.resolveDesignValues( {
	design: {},
	labelColor: '#be123c',
} );

/*
 * The chart is SVG, so the height is the one token that cannot travel as a
 * custom property and has to match across the PHP render and the JS preview.
 */
const chartHeights = [
	{ design: { chartHeight: '240' } },
	{ design: { chartHeight: '9999' } },
	{ design: { chartHeight: '10' } },
	{ design: {} },
	{ design: { chartHeight: 'junk' } },
].map( ( attrs ) => design.getChartHeight( attrs ) );

process.stdout.write(
	JSON.stringify( {
		groups: groups,
		cssValues: cssValues,
		sanitized: sanitized,
		resolved: resolvedAll,
		overrides: overrides,
		afterReset: afterReset,
		chartHeights: chartHeights,
	} )
);
