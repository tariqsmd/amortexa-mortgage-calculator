/**
 * Writes the plugin version into the compiled block metadata.
 *
 * Run automatically as the last step of: npm run build
 *
 * Why this exists:
 *
 * WordPress versions a block's scripts and styles from the "version" field in
 * block.json, not from the plugin header. register_block_type_from_metadata()
 * hands that field to register_block_script_handle(), which becomes the ?ver=
 * on the front-end <script> and <link> tags.
 *
 * That field used to be typed into src/block.json by hand, which is how the
 * header and the metadata drifted apart: the header said 1.1.0 while block.json
 * still said 1.0.0. The compiled CSS and JS keep stable filenames
 * (style-index.css, view.js), so with ?ver= pinned to a version that never
 * moves, a browser that has the assets cached keeps serving the old ones after
 * an upgrade -- which reads as "my settings do nothing" on the front end.
 *
 * So the version is no longer authored anywhere. It is read from the plugin
 * header, the same field WordPress itself shows in the plugins list, and
 * written into the compiled metadata. Bumping the header is now the only
 * step needed, and it moves the asset URLs with it.
 *
 * src/block.json deliberately declares no "version": a second hand-maintained
 * copy is the thing that drifted in the first place. tests/parity.php asserts
 * both halves of this contract.
 *
 * The pass fails loudly rather than emitting metadata without a version,
 * because a missing field silently disables cache busting.
 */

const fs = require( 'fs' );
const path = require( 'path' );

const PLUGIN_DIR = path.resolve( __dirname, '..' );
const MAIN_FILE = path.join( PLUGIN_DIR, 'amortexa-mortgage-calculator.php' );
const BLOCK_JSON = path.join( PLUGIN_DIR, 'build', 'block.json' );

/**
 * Reads the version from the plugin header.
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

if ( ! fs.existsSync( BLOCK_JSON ) ) {
	throw new Error( 'build/block.json not found. Run `npm run build` first.' );
}

const version = readVersion();
const metadata = JSON.parse( fs.readFileSync( BLOCK_JSON, 'utf8' ) );
const previous = metadata.version;

metadata.version = version;

// Tabs and a trailing newline match the rest of the committed JSON in this repo.
fs.writeFileSync( BLOCK_JSON, `${ JSON.stringify( metadata, null, '\t' ) }\n`, 'utf8' );

console.log( 'Syncing block metadata version' );
console.log( `  build/block.json  ${ previous || '(unset)' } -> ${ version }` );

if ( metadata.version !== version ) {
	throw new Error( 'Failed to write the version into build/block.json.' );
}