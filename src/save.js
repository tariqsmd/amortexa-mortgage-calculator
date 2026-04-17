/**
 * Save definition for the dynamic calculator block.
 *
 * The block is fully server-rendered via a PHP render callback, so nothing is
 * serialized into post content beyond the attributes stored by the editor.
 *
 * @return {null} Null tells WordPress to rely on the render callback.
 */
export default function save() {
	return null;
}
