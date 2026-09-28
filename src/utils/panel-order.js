/**
 * Panel ordering for the calculator block.
 *
 * The editor preview renders in JavaScript while the front end renders from
 * PHP, so both sides need the same rule. This module mirrors
 * calcforge_resolve_panel_order() in includes/helpers.php; the two must be
 * changed together or the editor will preview a different order from the one
 * visitors get.
 *
 * Every panel is reorderable, including the form. The saved order is honoured
 * verbatim in both layouts: the grid places panels in DOM order, so a full
 * width charts panel ahead of the form simply takes the first row and the form
 * and results share the next one.
 */

/**
 * Reorderable panels, in their default vertical order.
 *
 * @type {Array<string>}
 */
export const PANEL_KEYS = [ 'form', 'results', 'charts', 'schedule' ];

/**
 * Resolves which panels render, in what order.
 *
 * Unknown keys are dropped, duplicates collapse, and visible panels missing
 * from the saved order are appended in their default position. That keeps a
 * hand-edited post, or a block saved before a panel existed, rendering
 * everything it should.
 *
 * A block saved before the form became reorderable has no 'form' key at all.
 * Those keep the form first, so upgrading never drops the inputs to the
 * bottom of existing content. Once a form key is present the saved order is
 * honoured exactly, wherever the author dragged it.
 *
 * @param {Array<string>} order   Saved panel order.
 * @param {Array<string>} visible Panel keys that should render.
 * @return {Array<string>} Ordered, de-duplicated panel keys.
 */
export function resolvePanelOrder( order, visible ) {
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

	if ( ! resolved.includes( 'form' ) && visible.includes( 'form' ) ) {
		resolved.unshift( 'form' );
	}

	visible.forEach( ( key ) => {
		if ( ! resolved.includes( key ) ) {
			resolved.push( key );
		}
	} );

	return resolved;
}

/**
 * Moves one entry of an order array to a new index.
 *
 * Shared by the inspector's drag handles and its up/down buttons so both
 * produce the same array.
 *
 * @param {Array<string>} order Current order.
 * @param {number}        from  Index being moved.
 * @param {number}        to    Destination index.
 * @return {Array<string>} A new order array.
 */
export function movePanel( order, from, to ) {
	if (
		from === to ||
		from < 0 ||
		to < 0 ||
		from >= order.length ||
		to >= order.length
	) {
		return order;
	}

	const next = [ ...order ];
	const [ moved ] = next.splice( from, 1 );

	next.splice( to, 0, moved );

	return next;
}
