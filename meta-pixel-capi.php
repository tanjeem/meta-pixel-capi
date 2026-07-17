<?php
/**
 * Plugin Name: Meta Pixel & CAPI Ultimate for WooCommerce
 * Plugin URI:  https://example.com/
 * Description: Advanced Meta SDK Integration, Server-side CAPI, Strict Deduplication, and Fake Order Protection.
 * Version:     1.1.0
 * Author:      Tanzeem
 * Author URI:  https://example.com/
 * Text Domain: meta-pixel-capi
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package Mpc
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'MPC_VERSION', '1.1.0' );
define( 'MPC_PLUGIN_FILE', __FILE__ );
define( 'MPC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MPC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Autoloader
require_once MPC_PLUGIN_DIR . 'includes/Autoloader.php';

// Initialize Autoloader
$autoloader = new \Mpc\Autoloader();
$autoloader->register();
$autoloader->addNamespace( 'Mpc', MPC_PLUGIN_DIR . 'includes' );

// Activation hooks
register_activation_hook( __FILE__, [ '\Mpc\Activator', 'activate' ] );

// Boot the plugin
function mpc_init() {
	// Initialize Auto Updater
	if ( file_exists( MPC_PLUGIN_DIR . 'includes/plugin-update-checker/plugin-update-checker.php' ) ) {
		require_once MPC_PLUGIN_DIR . 'includes/plugin-update-checker/plugin-update-checker.php';
		$myUpdateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			'https://github.com/tanzeem/meta-pixel-capi/',
			__FILE__,
			'meta-pixel-capi'
		);
		$myUpdateChecker->setBranch('main');
	}

	if ( class_exists( 'WooCommerce' ) ) {
		\Mpc\Plugin::get_instance();
	} else {
		add_action( 'admin_notices', 'mpc_missing_wc_notice' );
	}
}
add_action( 'plugins_loaded', 'mpc_init' );

function mpc_missing_wc_notice() {
	echo '<div class="error"><p><strong>Meta Pixel & CAPI Ultimate</strong> requires WooCommerce to be installed and active.</p></div>';
}
