<?php
/**
 * Plugin Name: Meta Pixel & CAPI Ultimate for WooCommerce
 * Plugin URI:  https://github.com/tanjeem/meta-pixel-capi
 * Description: Multi-platform server-side tracking for WooCommerce — Meta CAPI, GA4, Google Ads, TikTok, Pinterest & Snapchat with deduplication, consent gating, and fake-order protection.
 * Version:     2.1.0
 * Author:      Tanzeem
 * Author URI:  https://github.com/tanjeem
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

define( 'MPC_VERSION', '2.1.0' );
define( 'MPC_PLUGIN_FILE', __FILE__ );
define( 'MPC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MPC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Meta Graph API version — bumped centrally so every request stays in sync.
if ( ! defined( 'MPC_GRAPH_VERSION' ) ) {
	define( 'MPC_GRAPH_VERSION', 'v21.0' );
}

// Autoloader
require_once MPC_PLUGIN_DIR . 'includes/Autoloader.php';

// Initialize Autoloader
$autoloader = new \Mpc\Autoloader();
$autoloader->register();
$autoloader->addNamespace( 'Mpc', MPC_PLUGIN_DIR . 'includes' );

// Activation hooks
register_activation_hook( __FILE__, [ '\Mpc\Activator', 'activate' ] );

// Declare WooCommerce feature compatibility (HPOS / custom order tables + Cart & Checkout Blocks).
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
} );

// Boot the plugin
function mpc_init() {
	// Initialize Auto Updater
	if ( file_exists( MPC_PLUGIN_DIR . 'includes/plugin-update-checker/plugin-update-checker.php' ) ) {
		require_once MPC_PLUGIN_DIR . 'includes/plugin-update-checker/plugin-update-checker.php';
		$myUpdateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			'https://github.com/tanjeem/meta-pixel-capi/',
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
