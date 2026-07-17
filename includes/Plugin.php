<?php
namespace Mpc;

class Plugin {
	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->load_dependencies();
	}

	private function load_dependencies() {
		Admin\AdminMenu::get_instance();
		Tracker\Pixel::get_instance();
		Tracker\Capi::get_instance();
		Tracker\RetryQueue::get_instance();
		Orders\FakeProtection::get_instance();
		Orders\Blocklist::get_instance();
		Marketing\CatalogFeed::get_instance();
		Marketing\AbandonedCart::get_instance();

		add_action( 'admin_init', [ $this, 'check_version' ] );
	}

	public function check_version() {
		$installed_version = get_option( 'mpc_plugin_version' );
		if ( $installed_version !== MPC_VERSION ) {
			Activator::activate();
			update_option( 'mpc_plugin_version', MPC_VERSION );
		}
	}
}
