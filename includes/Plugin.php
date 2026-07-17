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
		Consent\ConsentManager::get_instance();
		Admin\AdminMenu::get_instance();
		Tracker\Pixel::get_instance();
		Tracker\Capi::get_instance();
		Tracker\RetryQueue::get_instance();
		Tracker\RecoveryCron::get_instance();
		Orders\FakeProtection::get_instance();
		Orders\Blocklist::get_instance();
		Marketing\CatalogFeed::get_instance();
		Marketing\AbandonedCart::get_instance();

		$this->register_channels();

		add_action( 'admin_init', [ $this, 'check_version' ] );
	}

	/**
	 * Register the multi-platform tracking channels on the event bus and boot
	 * the WooCommerce collector that feeds them. Meta keeps its own dedicated
	 * path (Tracker\Capi) for now; these are the additional platforms.
	 */
	private function register_channels() {
		$bus = Events\EventBus::get_instance();
		$bus->register( new Channels\GA4Channel() );
		$bus->register( new Channels\GoogleAdsChannel() );
		$bus->register( new Channels\TikTokChannel() );
		$bus->register( new Channels\PinterestChannel() );
		$bus->register( new Channels\SnapchatChannel() );

		/**
		 * Allow add-ons to register further channels.
		 *
		 * @param Events\EventBus $bus
		 */
		do_action( 'mpc_register_channels', $bus );

		Events\Collector::boot();
	}

	public function check_version() {
		$installed_version = get_option( 'mpc_plugin_version' );
		if ( $installed_version !== MPC_VERSION ) {
			Activator::activate();
			update_option( 'mpc_plugin_version', MPC_VERSION );
		}
	}
}
