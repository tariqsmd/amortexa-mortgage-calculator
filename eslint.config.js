const base = require( '@wordpress/scripts/config/eslint.config.cjs' );

module.exports = [
	...base,
	{
		rules: {
			// The editor components annotate render helpers as returning JSX and
			// pass DOM nodes around using platform types.
			'jsdoc/no-undefined-types': [
				'error',
				{
					definedTypes: [
						'JSX',
						'Element',
						'HTMLElement',
						'SVGElement',
						'SVGSVGElement',
					],
				},
			],
		},
	},
];