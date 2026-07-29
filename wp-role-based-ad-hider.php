<?php
/**
 * Plugin Name: Role-Based Ad Hider
 * Plugin URI: https://github.com/annapad/wp-role-based-ad-hider
 * Description: Hide ads from users with specific WordPress roles or capabilities. Useful for membership sites and subscription platforms.
 * Version: 1.0.0
 * Author: Anna Pantelakaki
 * Author URI: https://github.com/annapad
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: role-based-ad-hider
 * Requires at least: 5.0
 * Requires PHP: 7.4
 *
 * @package Role_Based_Ad_Hider
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'RBAH_VERSION', '1.0.0' );
define( 'RBAH_PATH', plugin_dir_path( __FILE__ ) );
define( 'RBAH_URL', plugin_dir_url( __FILE__ ) );
define( 'RBAH_OPTION_KEY', 'rbah_settings' );

// Load plugin classes.
require_once RBAH_PATH . 'includes/class-plugin.php';
require_once RBAH_PATH . 'includes/class-settings.php';

// Bootstrap on plugins_loaded so all roles/capabilities are registered.
add_action( 'plugins_loaded', function () {
	RBAH_Plugin::instance();
	if ( is_admin() ) {
		RBAH_Settings::instance();
	}
} );
