<?php
/**
 * MT Gutenberg Blocks
 *
 * An interactive mortgage calculator Gutenberg block with server-rendered
 * output, amortization schedules, and a REST calculation endpoint.
 *
 * @package           MortgageCalculatorBlock
 * @author            Tariq
 * @copyright         2026 Tariq
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       MT Gutenberg Blocks
 * Plugin URI:        https://example.com/plugins/mt-gutenberg-blocks/
 * Description:       A native Gutenberg block providing an interactive mortgage calculator with live results and an amortization schedule.
 * Version:           1.2.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Tariq
 * Author URI:        https://example.com/
 * Text Domain:       mt-gutenberg-blocks
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

// Abort if this file is called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'mtgb_TEXT_DOMAIN', 'mt-gutenberg-blocks' );
define( 'mtgb_VERSION', '1.2.0' );
define( 'mtgb_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'mtgb_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'mtgb_PLUGIN_FILE', __FILE__ );

require_once mtgb_PLUGIN_DIR . 'includes/class-mt-gutenberg-blocks.php';

register_activation_hook( __FILE__, array( 'MTGB_Gutenberg_Blocks', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MTGB_Gutenberg_Blocks', 'deactivate' ) );

/**
 * Boots the single instance of the main plugin class.
 */
MTGB_Gutenberg_Blocks::get_instance();
