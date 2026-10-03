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
import { getSiteDefaults } from './utils/editor-data';
import Edit from './edit';
import save from './save';
import './style.scss';
import './index.css';

/*
 * The administrator's site-wide defaults are injected into the attribute
 * schemas on the PHP side (Amortexa_Blocks::register_block_type), but that
 * registration does not reach the editor: WordPress merges the bootstrapped
 * server block type first and then spreads the client settings over it, so the
 * `attributes` imported from block.json win and the static defaults there
 * decide what a newly inserted calculator looks like.
 *
 * That is why block.json's `showAmortization: true` kept showing the schedule
 * even with the site setting turned off, and why the editor and the front end
 * disagreed. Overriding the same defaults here from the localized site values
 * puts both sides on one source of truth. Only the `default` is replaced - the
 * `type` and any `enum` stay as block.json declares them, so the schema still
 * validates the same way.
 */
const siteDefaults = getSiteDefaults();

const attributes = Object.fromEntries(
	Object.entries( metadata.attributes ?? {} ).map( ( [ name, schema ] ) => [
		name,
		name in siteDefaults
			? { ...schema, default: siteDefaults[ name ] }
			: schema,
	] )
);

registerBlockType( metadata.name, {
	...metadata,
	attributes,
	edit: Edit,
	save,
} );
