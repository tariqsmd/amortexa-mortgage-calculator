/**
 * Rasterises the vector listing assets into the PNGs WordPress.org expects.
 *
 * Run with: npm run render-assets
 *
 * Sources live in .wordpress-org/assets and are the files actually committed to
 * the plugin's SVN trunk/assets directory. PNGs are generated rather than
 * hand-exported so the icon and banners can never drift out of sync.
 *
 * Requires a local Chrome/Chromium/Edge install; set CHROME_PATH to override
 * auto-detection.
 */

const fs = require( 'fs' );
const os = require( 'os' );
const path = require( 'path' );
const { execFileSync } = require( 'child_process' );

const PLUGIN_DIR = path.resolve( __dirname, '..' );
const ASSET_DIR = path.join( PLUGIN_DIR, '.wordpress-org', 'assets' );

/**
 * Every PNG the WordPress.org plugin directory and readme parser look for.
 *
 * Each entry names the vector source to rasterise and the output size. The
 * banner is authored at 1544x500 and emitted at both 2x and 1x so the smaller
 * file stays crisp on the 772px listing.
 */
const TARGETS = [
	{ source: 'icon.svg', output: 'icon-256x256.png', width: 256, height: 256 },
	{ source: 'icon.svg', output: 'icon-128x128.png', width: 128, height: 128 },
	{ source: 'banner.svg', output: 'banner-1544x500.png', width: 1544, height: 500 },
	{ source: 'banner.svg', output: 'banner-772x250.png', width: 772, height: 250 },
];

/**
 * Locates a Chromium-family browser.
 *
 * @return {string} Absolute path to the executable.
 */
function findBrowser() {
	const fromEnv = process.env.CHROME_PATH;

	if ( fromEnv ) {
		if ( ! fs.existsSync( fromEnv ) ) {
			throw new Error( `CHROME_PATH does not exist: ${ fromEnv }` );
		}
		return fromEnv;
	}

	const candidates = [
		process.env.PROGRAMFILES &&
			path.join( process.env.PROGRAMFILES, 'Google', 'Chrome', 'Application', 'chrome.exe' ),
		process.env[ 'PROGRAMFILES(X86)' ] &&
			path.join( process.env[ 'PROGRAMFILES(X86)' ], 'Google', 'Chrome', 'Application', 'chrome.exe' ),
		process.env.LOCALAPPDATA &&
			path.join( process.env.LOCALAPPDATA, 'Google', 'Chrome', 'Application', 'chrome.exe' ),
		process.env[ 'PROGRAMFILES(X86)' ] &&
			path.join( process.env[ 'PROGRAMFILES(X86)' ], 'Microsoft', 'Edge', 'Application', 'msedge.exe' ),
	].filter( Boolean );

	const found = candidates.find( ( candidate ) => fs.existsSync( candidate ) );

	if ( ! found ) {
		throw new Error(
			'No Chrome/Chromium/Edge found. Set CHROME_PATH to the executable.'
		);
	}

	return found;
}

const browser = findBrowser();
const workDir = fs.mkdtempSync( path.join( os.tmpdir(), 'cf-assets-' ) );

console.log( `Rendering listing assets with ${ path.basename( browser ) }\n` );

for ( const { source, output, width, height } of TARGETS ) {
	const sourcePath = path.join( ASSET_DIR, source );
	const outputPath = path.join( ASSET_DIR, output );

	if ( ! fs.existsSync( sourcePath ) ) {
		throw new Error( `Vector source not found: ${ sourcePath }` );
	}

	const svg = fs.readFileSync( sourcePath, 'utf8' );

	// Chrome wraps a bare SVG in a default document with body margins, so render
	// through an HTML wrapper that pins the artwork to the exact viewport.
	const htmlPath = path.join( workDir, `${ path.parse( output ).name }.html` );

	fs.writeFileSync(
		htmlPath,
		`<!DOCTYPE html><html><head><meta charset="utf-8"><style>
			html,body{margin:0;padding:0;background:transparent;overflow:hidden}
			svg{display:block;width:${ width }px;height:${ height }px}
		</style></head><body>${ svg }</body></html>`
	);

	execFileSync(
		browser,
		[
			'--headless=new',
			'--disable-gpu',
			'--no-sandbox',
			'--hide-scrollbars',
			'--default-background-color=00000000',
			`--force-device-scale-factor=1`,
			`--window-size=${ width },${ height }`,
			`--screenshot=${ outputPath }`,
			`file:///${ htmlPath.replace( /\\/g, '/' ) }`,
		],
		{ stdio: 'pipe' }
	);

	if ( ! fs.existsSync( outputPath ) ) {
		throw new Error( `Chrome did not produce ${ output }` );
	}

	const { size } = fs.statSync( outputPath );
	console.log( `  ${ output }  ${ width }x${ height }  ${ ( size / 1024 ).toFixed( 1 ) } KB` );
}

fs.rmSync( workDir, { recursive: true, force: true } );
console.log( '\nDone.' );
