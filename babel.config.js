/**
 * Babel configuration.
 *
 * wp-scripts only injects its own fallback Babel config when the project has
 * none, so this file replaces it. The preset list therefore has to be restated.
 *
 * The important part is the JSX runtime. @wordpress/babel-preset-default pins
 * `runtime: 'automatic'`, which compiles JSX to imports from `react/jsx-runtime`.
 * WordPress does not externalise that module: @wordpress/dependency-extraction-
 * webpack-plugin has no mapping for it, so it falls through to the polyfill
 * branch and emits `window.ReactJSXRuntime`. WordPress never defines that global
 * (it is a module-local `var` inside wp-includes/js/dist/vendor/react-jsx-
 * runtime.js), so every compiled component throws on its first `jsxs` call and
 * the editor reports "This block has encountered an error and cannot be
 * previewed".
 *
 * Compiling to the classic runtime instead emits calls to `wp.element.createElement`
 * and `wp.element.Fragment`, which the editor always provides. Registering the
 * same plugin the preset uses, with different options, makes Babel use these.
 */

module.exports = {
	presets: [ require.resolve( '@wordpress/babel-preset-default' ) ],
	plugins: [
		[
			require.resolve( '@babel/plugin-transform-react-jsx' ),
			{
				runtime: 'classic',
				pragma: 'wp.element.createElement',
				pragmaFrag: 'wp.element.Fragment',
			},
		],
	],
};
