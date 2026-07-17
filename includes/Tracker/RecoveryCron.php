<?php
namespace Mpc\Tracker;

class RecoveryCron {
	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'mpc_nightly_recovery_cron', [ $this, 'run_recovery' ] );
		
		if ( ! wp_next_scheduled( 'mpc_nightly_recovery_cron' ) ) {
			wp_schedule_event( time(), 'daily', 'mpc_nightly_recovery_cron' );
		}
	}

	public function run_recovery() {
		if ( ! get_option( 'mpc_enable_acr', 1 ) ) return;
		if ( ! function_exists( 'wc_get_orders' ) ) return;

		// HPOS-safe: pull recent paid orders via the CRUD layer instead of raw wp_posts SQL.
		$orders = wc_get_orders( [
			'status'       => [ 'wc-processing', 'wc-completed' ],
			'date_created' => '>' . ( time() - DAY_IN_SECONDS ),
			'limit'        => 200,
			'return'       => 'objects',
		] );

		if ( empty( $orders ) ) return;

		$capi = Capi::get_instance();

		foreach ( $orders as $order ) {
			if ( $order->get_meta( '_mpc_capi_sent' ) ) continue;

			// Clear the dedup flag so the purchase can be re-sent, then dispatch server-side.
			$order->delete_meta_data( '_mpc_purchase_tracked' );
			$order->save();
			$capi->send_purchase_event_server_only( $order->get_id() );
		}
	}
}
