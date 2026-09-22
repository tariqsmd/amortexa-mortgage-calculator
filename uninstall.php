<?php
/**
 * Uninstall routine for MT Gutenberg Blocks.
 *
 * Fired by WordPress when the plugin is deleted via the admin screen.
 * Never handle uninstall logic inline in the main plugin file.
 *
 * @package MortgageCalculatorBlock
 */

// Abort if WordPress is not unloading the plugin properly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) || ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deletes all persisted plugin data for a single site.
 */
function mtgb_delete_site_data() {
	delete_option( 'mtgb_settings' );
}

if ( is_multisite() ) {
	$mtgb_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $mtgb_site_ids as $mtgb_site_id ) {
		switch_to_blog( (int) $mtgb_site_id );
		mtgb_delete_site_data();
		restore_current_blog();
	}
} else {
	mtgb_delete_site_data();
}
