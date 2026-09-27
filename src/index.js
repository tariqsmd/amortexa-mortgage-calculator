/**
 * Registers the Mortgage Calculator block.
 *
 * Title, description, keywords, and category all come from block.json, which
 * WordPress translates through the block's `textdomain` field. Keeping them out
 * of this file means there is a single definition of the block's identity.
 *
 * @package
 */

import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import save from './save';
import './style.scss';
import './index.css';

registerBlockType( metadata.name, {
	...metadata,
	edit: Edit,
	save,
} );
