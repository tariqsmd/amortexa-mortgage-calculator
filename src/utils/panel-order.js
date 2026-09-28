/**
 * Panel ordering for the calculator block.
 *
 * The editor preview renders in JavaScript while the front end renders from
 * PHP, so both sides need the same rule. This module mirrors
 * calcforge_resolve_panel_order() in includes/helpers.php; the two must be
 * changed together or the editor will preview a different order from the one
 * visitors get.
 */

/**
 * Reorderable panels. The form is not listed because it is always first.
 *
 * @type {Array<string>}
 */
export const PANEL_KEYS = [ 'results', 'charts', 'schedule' ];

/**
 * Resolves which panels render, in what order.
 *
 * Unknown keys are dropped and visible panels missing from the saved order are
 * appended, so a block saved by an older version still renders everything. In
 * the two column split the results are pulled to the front, because a full
 * width panel between the form and the results would push the results into a
 * column of their own.
 *
 * @param {Array<string>} order   Saved panel order.
 * @param {string}        layout  Active layout, 'stacked' or 'split'.
 * @param {Array<string>} visible Panel keys that should render.
 * @return {Array<string>} Ordered, de-duplicated panel keys.
 */
export function resolvePanelOrder( order, layout, visible ) {
	const resolved = [];

	( Array.isArray( order ) ? order : [] ).forEach( ( key ) => {
		if (
			PANEL_KEYS.includes( key ) &&
			visible.includes( key ) &&
			! resolved.includes( key )
		) {
			resolved.push( key );
		}
	} );

	visible.forEach( ( key ) => {
		if ( ! resolved.includes( key ) ) {
			resolved.push( key );
		}
	} );

	if ( 'split' === layout && resolved.includes( 'results' ) ) {
		return [
			'results',
			...resolved.filter( ( key ) => 'results' !== key ),
		];
	}

	return resolved;
}
