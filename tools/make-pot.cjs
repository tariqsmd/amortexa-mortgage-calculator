/**
 * Generates languages/amortexa-mortgage-calculator.pot from the plugin source.
 *
 * Run with: npm run make-pot
 *
 * Covers the three places translatable strings live in this plugin:
 *   - PHP  : amortexa-mortgage-calculator.php, everything under includes/, and src/render.php
 *   - JS   : every .js file under src/ (via @babel/parser + @babel/traverse)
 *   - JSON : src/block.json (title, description, keywords)
 *
 * The text domain, version, and bug-report URL are read from the plugin header
 * so the generated catalogue can never drift from the shipped metadata.
 */

const fs = require( 'fs' );
const path = require( 'path' );
const { WP_Pot } = require( 'wp-pot' );
const { parse: parseJs } = require( '@babel/parser' );
const traverse = require( '@babel/traverse' ).default;

const PLUGIN_DIR = path.resolve( __dirname, '..' );
const MAIN_FILE = path.join( PLUGIN_DIR, 'amortexa-mortgage-calculator.php' );
const TEXT_DOMAIN = 'amortexa-mortgage-calculator';

/**
 * Gettext functions understood in JavaScript, with their argument roles.
 *
 * `domainIndex` is the zero-based position of the text domain argument.
 * `contextIndex` / `pluralIndex` are set only for the functions that have them.
 */
const JS_FUNCTIONS = {
	__: { domainIndex: 1 },
	_x: { domainIndex: 2, contextIndex: 1 },
	_n: { domainIndex: 3, pluralIndex: 1 },
	_nx: { domainIndex: 4, contextIndex: 3, pluralIndex: 1 },
	esc_attr__: { domainIndex: 1 },
	esc_attr_x: { domainIndex: 2, contextIndex: 1 },
	esc_html__: { domainIndex: 1 },
	esc_html_x: { domainIndex: 2, contextIndex: 1 },
};

/**
 * Reads a header field from the plugin main file.
 *
 * @param {string} name Header field name, e.g. "Text Domain".
 * @return {string} Field value, or an empty string when absent.
 */
function readHeader( name ) {
	const header = fs.readFileSync( MAIN_FILE, 'utf8' );
	const match = header.match( new RegExp( `^ \\* ${ name }:\\s*(.+)$`, 'm' ) );

	return match ? match[ 1 ].trim() : '';
}

/**
 * Returns the static string value of an AST node, or null when it is dynamic.
 *
 * Dynamic strings (template literals, concatenation, variables) cannot be
 * extracted at build time and are skipped, matching WP-CLI behaviour.
 *
 * @param {Object} node Babel AST node.
 * @return {string|null} Literal value or null.
 */
function staticString( node ) {
	if ( ! node ) {
		return null;
	}

	if ( node.type === 'StringLiteral' ) {
		return node.value;
	}

	if (
		node.type === 'TemplateLiteral' &&
		node.expressions.length === 0 &&
		node.quasis.length === 1
	) {
		return node.quasis[ 0 ].value.cooked;
	}

	return null;
}

/**
 * Extracts translatable strings from one JavaScript file.
 *
 * @param {string} file Absolute path to the JS file.
 * @return {Array<Object>} Translation entries in wp-pot's internal shape.
 */
function extractJsFile( file ) {
	const code = fs.readFileSync( file, 'utf8' );
	const relative = path.relative( PLUGIN_DIR, file ).replace( /\\/g, '/' );
	const ast = parseJs( code, {
		sourceType: 'unambiguous',
		plugins: [ 'jsx' ],
	} );

	const found = [];

	traverse( ast, {
		CallExpression( nodePath ) {
			const { callee } = nodePath.node;

			if ( callee.type !== 'Identifier' ) {
				return;
			}

			const signature = JS_FUNCTIONS[ callee.name ];

			if ( ! signature ) {
				return;
			}

			const args = nodePath.node.arguments;
			const domain = staticString( args[ signature.domainIndex ] );

			// Only strings bound to this plugin's text domain belong here.
			if ( domain !== TEXT_DOMAIN ) {
				return;
			}

			const msgid = staticString( args[ 0 ] );

			if ( msgid === null ) {
				return;
			}

			const entry = {
				translationId: msgid,
				msgid,
				filename: relative,
				line: nodePath.node.loc.start.line,
			};

			if ( signature.contextIndex !== undefined ) {
				entry.context = staticString( args[ signature.contextIndex ] );
				entry.translationId = msgid + ( entry.context || '' );
			}

			if ( signature.pluralIndex !== undefined ) {
				entry.plural = staticString( args[ signature.pluralIndex ] );
			}

			found.push( entry );
		},
	} );

	return found;
}

/**
 * Extracts translatable strings from a block.json manifest.
 *
 * `title`, `description`, and each entry in `keywords` are translated by core.
 *
 * @param {string} file Absolute path to the block.json file.
 * @return {Array<Object>} Translation entries in wp-pot's internal shape.
 */
function extractBlockJson( file ) {
	const manifest = JSON.parse( fs.readFileSync( file, 'utf8' ) );
	const relative = path.relative( PLUGIN_DIR, file ).replace( /\\/g, '/' );
	const found = [];

	const add = ( value, line ) => {
		if ( typeof value !== 'string' || value === '' ) {
			return;
		}

		found.push( {
			translationId: value,
			msgid: value,
			filename: relative,
			line,
			comments: [
				'Translators: block title shown in the block inserter.',
			],
		} );
	};

	const lines = fs
		.readFileSync( file, 'utf8' )
		.split( /\r?\n/ );

	/**
	 * Finds the 1-based line number of a top-level JSON key.
	 *
	 * @param {string} key JSON key to locate.
	 * @return {number} Line number, or 1 when not found.
	 */
	const lineOfKey = ( key ) => {
		const index = lines.findIndex( ( line ) =>
			line.trimStart().startsWith( `"${ key }"` )
		);

		return index === -1 ? 1 : index + 1;
	};

	add( manifest.title, lineOfKey( 'title' ) );
	add( manifest.description, lineOfKey( 'description' ) );

	if ( Array.isArray( manifest.keywords ) ) {
		const base = lineOfKey( 'keywords' );

		manifest.keywords.forEach( ( keyword, offset ) => {
			if ( typeof keyword !== 'string' ) {
				return;
			}

			found.push( {
				translationId: keyword,
				msgid: keyword,
				filename: relative,
				line: base + 1 + offset,
				comments: [
					'Translators: block keyword used in the block inserter search.',
				],
			} );
		} );
	}

	return found;
}

/**
 * Recursively lists files under a directory matching an extension.
 *
 * @param {string}   dir      Absolute directory to walk.
 * @param {string[]} extensions Extensions to include, e.g. [ '.js' ].
 * @return {string[]} Absolute file paths.
 */
function walk( dir, extensions ) {
	return fs
		.readdirSync( dir, { withFileTypes: true } )
		.flatMap( ( entry ) => {
			const full = path.join( dir, entry.name );

			if ( entry.isDirectory() ) {
				return entry.name === 'node_modules' || entry.name === 'build'
					? []
					: walk( full, extensions );
			}

			return extensions.includes( path.extname( entry.name ) )
				? [ full ]
				: [];
		} );
}

const declaredDomain = readHeader( 'Text Domain' );

if ( declaredDomain !== TEXT_DOMAIN ) {
	throw new Error(
		`Text domain mismatch: main file declares "${ declaredDomain }", expected "${ TEXT_DOMAIN }".`
	);
}

const version = readHeader( 'Version' );
const pluginUri = readHeader( 'Plugin URI' );

const pot = new WP_Pot( {
	globOpts: { cwd: PLUGIN_DIR },
	pot: {
		package: `Amortexa ${ version }`,
		bugReport: pluginUri,
		team: 'Amortexa <https://profiles.wordpress.org/mtariqsmd/>',
		lastTranslator: 'Amortexa <https://profiles.wordpress.org/mtariqsmd/>',
	},
} );

// PHP sources.
	pot.parse( [
		'*.php',
		'includes/**/*.php',
		'src/**/*.php',
		'!node_modules/**',
		'!dist/**',
		'!build/**',
	] );
const phpCount = pot.translations.length;

// JavaScript sources.
const jsFiles = walk( path.join( PLUGIN_DIR, 'src' ), [ '.js' ] );
const jsTranslations = jsFiles.flatMap( extractJsFile );
pot.translations.push( ...jsTranslations );

// Block metadata.
pot.translations.push(
	...extractBlockJson( path.join( PLUGIN_DIR, 'src', 'block.json' ) )
);

const destination = path.join( PLUGIN_DIR, 'languages', `${ TEXT_DOMAIN }.pot` );

// languages/ is generated output, so it may not exist on a fresh checkout.
fs.mkdirSync( path.dirname( destination ), { recursive: true } );

pot.writePot( destination );

const unique = new Set( pot.translations.map( ( t ) => t.translationId ) ).size;

console.log(
	`Generated languages/${ TEXT_DOMAIN }.pot for Amortexa ${ version }`
);
console.log(
	`  PHP: ${ phpCount } | JS: ${ jsTranslations.length } | unique strings: ${ unique }`
);
