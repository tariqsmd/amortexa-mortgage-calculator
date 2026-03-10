<?php
/**
 * Uninstall routine for Mortgage Calculator Block.
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
function mcb_delete_site_data() {
	delete_option( 'mcb_settings' );
}

if ( is_multisite() ) {
	$mcb_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $mcb_site_ids as $mcb_site_id ) {
		switch_to_blog( (int) $mcb_site_id );
		mcb_delete_site_data();
		restore_current_blog();
	}
} else {
	mcb_delete_site_data();
}
