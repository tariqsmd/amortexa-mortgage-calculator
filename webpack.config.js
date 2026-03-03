/**
 * Extends the default @wordpress/scripts webpack configuration.
 *
 * The only customization is an additional `view` entry point that compiles
 * src/view.js into build/view.js — the progressive-enhancement script loaded
 * on the front end. Everything else (entries, loaders, externals, asset
 * generation) is inherited from wp-scripts untouched.
 */

const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

/**
 * Normalizes an entry definition to a plain object.
 *
 * @param {Object|Function} entry Entry object or lazy entry factory.
 * @return {Object} Resolved entry map.
 */
function resolveEntries( entry ) {
	if ( typeof entry === 'function' ) {
		return entry() || {};
	}

	return entry || {};
}

/**
 * Adds the `view` entry to a single config object.
 *
 * @param {Object} partial One webpack config object from wp-scripts.
 * @return {Object} Config with the view entry merged into its entries.
 */
function extendPartial( partial ) {
	const { entry, ...rest } = partial;

	return {
		...rest,
		entry: () => ( {
			...resolveEntries( entry ),
			view: './src/view.js',
		} ),
	};
}

/**
 * Adds the view entry to every config object emitted by wp-scripts.
 *
 * @param {Object|Array<Object>} config Default wp-scripts configuration.
 * @return {Object|Array<Object>} Extended configuration in the same shape.
 */
function addViewEntry( config ) {
	const configs = Array.isArray( config ) ? config : [ config ];
	const extended = configs.map( ( partial ) =>
		partial && typeof partial === 'object' ? extendPartial( partial ) : partial
	);

	return Array.isArray( config ) ? extended : extended[ 0 ];
}

module.exports = addViewEntry( defaultConfig );
