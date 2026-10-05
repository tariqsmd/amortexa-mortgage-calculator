/**
 * Builds a WordPress.org-ready release of the plugin.
 *
 * Run with: npm run dist
 *
 * Produces, under ./dist:
 *   trunk/                        the current release (what SVN serves)
 *   tags/<version>/              an immutable copy of the same tree
 *   amortexa-mortgage-calculator.zip
 *
 * Only the allowlist below is packaged. This is deliberate: the repository
 * contains development files (tests, tooling) that must never reach a public
 * release. In particular tests/parity.php calls shell_exec(), which
 * WordPress.org rejects outright.
 */

const fs = require( 'fs' );
const path = require( 'path' );
const AdmZip = require( 'adm-zip' );

const PLUGIN_DIR = path.resolve( __dirname, '..' );
const DIST_DIR = path.join( PLUGIN_DIR, 'dist' );
const MAIN_FILE = path.join( PLUGIN_DIR, 'amortexa-mortgage-calculator.php' );
const SLUG = 'amortexa-mortgage-calculator';
const LISTING_SRC = path.join( PLUGIN_DIR, '.wordpress-org', 'assets' );
const LISTING_OUT = path.join( DIST_DIR, 'assets' );

/**
 * The exact set of paths that ship.
 *
 * Files (not directories) and directories are both supported.
 *
 * The compiled `build/` directory is what WordPress actually runs, but the
 * unminified sources under `src/` ship alongside it together with the build
 * config. Directory guideline #4 requires the source behind every minified
 * asset to be publicly available, and shipping it here satisfies that without
 * relying on a third-party link staying reachable. Nothing in `src/` is loaded
 * at runtime: the block is registered from build/block.json, so every "file:"
 * reference in that manifest -- including "render": "file:./render.php" --
 * resolves inside build/, and wp-scripts copies render.php there. The only
 * runtime PHP outside build/ is includes/, which the main file requires.
 *
 * `package-lock.json` is deliberately excluded. It is ~800 KB of dependency
 * hashes that add nothing to reviewing the plugin's own code; `npm install`
 * regenerates it from package.json.
 */
const SHIP = [
	'amortexa-mortgage-calculator.php',
	'uninstall.php',
	'readme.txt',
	'LICENSE',
	'plugin.json',
	'build',
	'languages',
	'assets',
	'includes',
	'src',
	'package.json',
	'webpack.config.js',
	'babel.config.js',
];

/**
 * Reads the Version field from the plugin main file.
 *
 * @return {string} Version string.
 */
function readVersion() {
	const header = fs.readFileSync( MAIN_FILE, 'utf8' );
	const match = header.match( /^ \* Version:\s*(.+)$/m );

	if ( ! match ) {
		throw new Error( 'No "Version" field found in the plugin header.' );
	}

	return match[ 1 ].trim();
}

/**
 * Recursively copies a file or directory.
 *
 * @param {string} source Absolute source path.
 * @param {string} target Absolute target path.
 */
function copy( source, target ) {
	const stats = fs.statSync( source );

	if ( stats.isDirectory() ) {
		fs.mkdirSync( target, { recursive: true } );

		for ( const entry of fs.readdirSync( source ) ) {
			copy( path.join( source, entry ), path.join( target, entry ) );
		}

		return;
	}

	fs.mkdirSync( path.dirname( target ), { recursive: true } );
	fs.copyFileSync( source, target );
}

/**
 * Lists every file inside a directory, relative and slash-separated.
 *
 * @param {string} dir Absolute directory to walk.
 * @param {string} base Absolute directory the results are relative to.
 * @return {string[]} Relative file paths.
 */
function listFiles( dir, base = dir ) {
	return fs
		.readdirSync( dir, { withFileTypes: true } )
		.flatMap( ( entry ) => {
			const full = path.join( dir, entry.name );

			return entry.isDirectory()
				? listFiles( full, base )
				: [ path.relative( base, full ).replace( /\\/g, '/' ) ];
		} );
}

const version = readVersion();

// Start from a clean dist so a removed file can never linger in a release.
fs.rmSync( DIST_DIR, { recursive: true, force: true } );

// Fail loudly if an allowlist entry is missing, rather than shipping a
// half-broken plugin.
const missing = SHIP.filter(
	( entry ) => ! fs.existsSync( path.join( PLUGIN_DIR, entry ) )
);

if ( missing.length ) {
	throw new Error( `Allowlisted path(s) not found: ${ missing.join( ', ' ) }` );
}

const trunk = path.join( DIST_DIR, 'trunk' );

for ( const entry of SHIP ) {
	copy( path.join( PLUGIN_DIR, entry ), path.join( trunk, entry ) );
}

const tagDir = path.join( DIST_DIR, 'tags', version );
fs.mkdirSync( path.dirname( tagDir ), { recursive: true } );
fs.cpSync( trunk, tagDir, { recursive: true } );

const files = listFiles( trunk );
const zip = new AdmZip();

for ( const file of files ) {
	// AdmZip appends the basename to zipPath, so zipPath must be the entry's
	// *directory*. Passing the full relative path would produce doubled names
	// such as "amortexa-mortgage-calculator/amortexa-mortgage-calculator.php/amortexa-mortgage-calculator.php".
	zip.addLocalFile(
		path.join( trunk, file ),
		path.join( SLUG, path.dirname( file ) )
	);
}

const zipPath = path.join( DIST_DIR, `${ SLUG }.zip` );
zip.writeZip( zipPath );

const bytes = fs.statSync( zipPath ).size;

console.log( `Amortexa ${ version }` );
console.log( `  dist/trunk/          ${ files.length } files` );
console.log( `  dist/tags/${ version }/` );
console.log(
	`  ${ path.basename( zipPath ) }  ${ ( bytes / 1024 ).toFixed( 1 ) } KB`
);

// WordPress.org reads the icon, banners and screenshots from an `assets`
// directory that is a *sibling* of trunk and tags in the SVN repository, not
// from inside the plugin. Copy them alongside the plugin tree so `dist/` can be
// committed to SVN as-is.
const listing = fs.existsSync( LISTING_SRC )
	? fs
			.readdirSync( LISTING_SRC )
			.filter( ( entry ) => ! entry.startsWith( '.' ) )
			.sort()
	: [];

if ( listing.length ) {
	fs.mkdirSync( LISTING_OUT, { recursive: true } );

	for ( const entry of listing ) {
		copy( path.join( LISTING_SRC, entry ), path.join( LISTING_OUT, entry ) );
	}

	const pngs = listing.filter( ( entry ) => entry.endsWith( '.png' ) );
	console.log(
		`  dist/assets/         ${ listing.length } files (${ pngs.length } PNG for wp.org)`
	);
} else {
	console.warn(
		'  dist/assets/         empty - run `npm run render-assets` before packaging a submission'
	);
}
