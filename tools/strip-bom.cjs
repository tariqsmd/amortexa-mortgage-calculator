/**
 * Removes UTF-8 byte order marks from the build output.
 *
 * Run automatically as the last step of: npm run build
 *
 * Why this exists:
 *
 * On Windows the CSS emitted by @wordpress/scripts starts with an EF BB BF
 * byte order mark. That is harmless in a <link>ed stylesheet -- the browser
 * strips it while sniffing the encoding -- which is why the block editor
 * preview always looked correct. On the front end WordPress inlines
 * style-index.css into the page (wp_maybe_inline_styles), and inside a <style>
 * element the mark is not stripped. The CSS parser then folds it into the
 * first selector, so the very first rule in the file becomes
 *
 *     \uFEFF.amortexa-calc { ... }
 *
 * which matches nothing. That rule is the one declaring every --amortexa-*
 * custom property, the calculator's own background and border, and
 * `container-type: inline-size`. With it dead:
 *
 *   - var(--amortexa-field-bg) and friends resolve to nothing, so the number
 *     inputs fall back to invalid-at-computed-value-time and render with a
 *     transparent background, no border and the theme's inherited text colour.
 *   - the calculator's own background and border disappear.
 *   - container-type stays `normal`, so every @container breakpoint silently
 *     does nothing and the responsive layouts never collapse.
 *
 * A BOM is also stripped from build/render.php, which wp-scripts copies
 * verbatim from src/ and which is included directly by the render callback.
 *
 * src/ is cleaned too. It ships in the release alongside build/ so the source
 * behind every minified file is publicly available (directory guideline #4), and
 * a BOM in a JavaScript source file breaks the first statement for anything
 * that concatenates or re-parses it. Only build/ *output* is rewritten during a
 * normal build; the source cleanup is skipped when src/ has no BOM, so it costs
 * nothing once the tree is clean.
 *
 * The pass is idempotent and fails loudly if a stylesheet still starts with a
 * BOM afterwards, so this cannot quietly regress.
 */

const fs = require( 'fs' );
const path = require( 'path' );

const PLUGIN_DIR = path.resolve( __dirname, '..' );
const BUILD_DIR = path.join( PLUGIN_DIR, 'build' );
const SRC_DIR = path.join( PLUGIN_DIR, 'src' );

const BOM = Buffer.from( [ 0xef, 0xbb, 0xbf ] );

/** Extensions treated as text; binary assets in build/ are left alone. */
const TEXT_EXTENSIONS = new Set( [
	'.block.json',
	'.css',
	'.js',
	'.json',
	'.map',
	'.php',
	'.txt',
] );

/**
 * Recursively lists files under a directory.
 *
 * @param {string} dir Absolute directory path.
 * @return {string[]} Absolute file paths.
 */
function walk( dir ) {
	return fs.readdirSync( dir, { withFileTypes: true } ).flatMap( ( entry ) => {
		const full = path.join( dir, entry.name );
		return entry.isDirectory() ? walk( full ) : [ full ];
	} );
}

/**
 * Whether a path is a text file this pass should inspect.
 *
 * @param {string} file Absolute file path.
 * @return {boolean} True when the file is text we may rewrite.
 */
function isTextFile( file ) {
	return TEXT_EXTENSIONS.has( path.extname( file ).toLowerCase() );
}

if ( ! fs.existsSync( BUILD_DIR ) ) {
	throw new Error( 'build/ not found. Run `npm run build` first.' );
}

/**
 * Strips a leading BOM from a file, if it has one.
 *
 * @param {string} file Absolute file path.
 * @return {boolean} True when a BOM was removed.
 */
function stripBom( file ) {
	if ( ! isTextFile( file ) ) {
		return false;
	}

	const buffer = fs.readFileSync( file );

	if ( ! buffer.subarray( 0, 3 ).equals( BOM ) ) {
		return false;
	}

	fs.writeFileSync( file, buffer.subarray( 3 ) );

	return true;
}

const fixed = [];

/*
 * build/ first: a stylesheet there that still opens with a BOM silently breaks
 * its own first rule, so that output has to be correct before anything else.
 */
for ( const file of walk( BUILD_DIR ) ) {
	if ( stripBom( file ) ) {
		fixed.push( path.relative( PLUGIN_DIR, file ) );
	}
}

console.log( 'Stripping UTF-8 BOMs from build/' );

if ( fixed.length ) {
	fixed.forEach( ( file ) => console.log( `  removed  ${ file }` ) );
} else {
	console.log( '  none found' );
}

if ( fs.existsSync( SRC_DIR ) ) {
	const strippedSources = walk( SRC_DIR ).filter( stripBom );

	console.log( 'Stripping UTF-8 BOMs from src/' );

	if ( strippedSources.length ) {
		strippedSources.forEach( ( file ) =>
			console.log( `  removed  ${ path.relative( PLUGIN_DIR, file ) }` )
		);
	} else {
		console.log( '  none found' );
	}
}

// Guard: a stylesheet that still opens with a BOM would silently break the
// first rule again, so treat it as a build failure rather than shipping it.
const offenders = walk( BUILD_DIR ).filter(
	( file ) =>
		path.extname( file ).toLowerCase() === '.css' &&
		fs.readFileSync( file ).subarray( 0, 3 ).equals( BOM )
);

if ( offenders.length ) {
	throw new Error(
		`Stylesheet still starts with a BOM: ${ offenders
			.map( ( file ) => path.relative( PLUGIN_DIR, file ) )
			.join( ', ' ) }`
	);
}

console.log( 'Done.' );
