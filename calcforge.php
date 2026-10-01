<?php
/**
 * CalcForge
 *
 * @package           CalcForge
 * @author            Muhammad Tariq
 * @copyright         2026 Muhammad Tariq
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       CalcForge
 * Plugin URI:        https://wordpress.org/plugins/calcforge/
 * Description:       An interactive mortgage calculator block with live monthly payment results, down payment support, recurring costs with PMI cancellation, charts, and an amortization schedule.
 * Version:           1.1.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Muhammad Tariq
 * Author URI:        https://profiles.wordpress.org/mtariqsmd/
 * Text Domain:       calcforge
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://wordpress.org/plugins/calcforge/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CALCFORGE_VERSION', '1.1.0' );
define( 'CALCFORGE_TEXT_DOMAIN', 'calcforge' );
define( 'CALCFORGE_PLUGIN_FILE', __FILE__ );
define( 'CALCFORGE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CALCFORGE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once CALCFORGE_PLUGIN_DIR . 'includes/helpers.php';
require_once CALCFORGE_PLUGIN_DIR . 'includes/design-tokens.php';
require_once CALCFORGE_PLUGIN_DIR . 'includes/class-i18n.php';
require_once CALCFORGE_PLUGIN_DIR . 'includes/class-assets.php';
require_once CALCFORGE_PLUGIN_DIR . 'includes/class-blocks.php';
require_once CALCFORGE_PLUGIN_DIR . 'includes/class-rest.php';
require_once CALCFORGE_PLUGIN_DIR . 'includes/class-settings.php';
require_once CALCFORGE_PLUGIN_DIR . 'includes/class-shortcode.php';
require_once CALCFORGE_PLUGIN_DIR . 'includes/class-widget.php';
require_once CALCFORGE_PLUGIN_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'CalcForge_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CalcForge_Plugin', 'deactivate' ) );

CalcForge_Plugin::get_instance();
