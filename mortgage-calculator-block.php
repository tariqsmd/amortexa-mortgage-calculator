<?php
/**
 * Mortgage Calculator Block
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
 * Plugin Name:       Mortgage Calculator Block
 * Plugin URI:        https://example.com/plugins/mortgage-calculator-block/
 * Description:       A native Gutenberg block providing an interactive mortgage calculator with live results and an amortization schedule.
 * Version:           1.1.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Tariq
 * Author URI:        https://example.com/
 * Text Domain:       mortgage-calculator-block
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

// Abort if this file is called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MCB_TEXT_DOMAIN', 'mortgage-calculator-block' );
define( 'MCB_VERSION', '1.1.0' );
define( 'MCB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MCB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MCB_PLUGIN_FILE', __FILE__ );

require_once MCB_PLUGIN_DIR . 'includes/class-mortgage-calculator-block.php';

register_activation_hook( __FILE__, array( 'MCB_Mortgage_Calculator_Block', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MCB_Mortgage_Calculator_Block', 'deactivate' ) );

/**
 * Boots the single instance of the main plugin class.
 */
MCB_Mortgage_Calculator_Block::get_instance();
