<?php
/**
 * Uninstall routine for CalcForge.
 *
 * Fired by WordPress when the plugin is deleted via the admin screen.
 * Never handle uninstall logic inline in the main plugin file.
 *
 * @package CalcForge
 */

// Abort if WordPress is not unloading the plugin properly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) || ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deletes all persisted plugin data for a single site.
 */
function calcforge_delete_site_data() {
	delete_option( 'calcforge_settings' );

	// Clean up anything left behind by builds from before the plugin rename.
	delete_option( 'mtgb_settings' );
}

if ( is_multisite() ) {
	$calcforge_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $calcforge_site_ids as $calcforge_site_id ) {
		switch_to_blog( (int) $calcforge_site_id );
		calcforge_delete_site_data();
		restore_current_blog();
	}
} else {
	calcforge_delete_site_data();
}
