/**
 * Registers the MT Mortgage Calculator block.
 *
 * @package
 */

import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import './style.scss';
import './editor.scss';
import metadata from './block.json';
import Edit from './edit';
import save from './save';

registerBlockType( metadata.name, {
	...metadata,
	title: __( 'MT Mortgage Calculator', 'mt-gutenberg-blocks' ),
	description: __(
		'Interactive mortgage calculator with monthly payments and amortization schedule.',
		'mt-gutenberg-blocks'
	),
	keywords: [
		__( 'mortgage', 'mt-gutenberg-blocks' ),
		__( 'loan', 'mt-gutenberg-blocks' ),
		__( 'calculator', 'mt-gutenberg-blocks' ),
	],
	edit: Edit,
	save,
} );
