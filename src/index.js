/**
 * Registers the Mortgage Calculator block.
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
	title: __( 'Mortgage Calculator', 'mortgage-calculator-block' ),
	description: __(
		'Interactive mortgage calculator with monthly payments and amortization schedule.',
		'mortgage-calculator-block'
	),
	keywords: [
		__( 'mortgage', 'mortgage-calculator-block' ),
		__( 'loan', 'mortgage-calculator-block' ),
		__( 'calculator', 'mortgage-calculator-block' ),
	],
	edit: Edit,
	save,
} );
