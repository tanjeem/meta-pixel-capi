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
			// Recover only orders with NO confirmed successful Purchase send. This
			// still catches genuine failures (attempted but never got a 2xx) while
			// never re-sending a purchase Meta already received.
			if ( $this->purchase_confirmed_sent( $order ) ) {
				continue;
			}

			// Clear the in-request dedup guard so do_purchase will actually re-send
			// (it early-returns when _mpc_purchase_tracked is set).
			$order->delete_meta_data( '_mpc_purchase_tracked' );
			$order->save();
			$capi->send_purchase_event_server_only( $order->get_id() );
		}
	}

	/**
	 * Whether a successful (2xx) Purchase send is on record for this order.
	 *
	 * Uses the event-log table, whose status is written with a direct $wpdb query
	 * (reliable on shutdown), rather than the `_mpc_capi_sent` order meta, whose
	 * WooCommerce save can silently fail on shutdown and previously caused nightly
	 * re-sends. The order-meta flag is still honoured as a fast path.
	 */
	private function purchase_confirmed_sent( $order ) {
		if ( $order->get_meta( '_mpc_capi_sent' ) ) {
			return true;
		}

		global $wpdb;
		$event_id = Deduplication::get_order_event_id( $order->get_id() );
		$count    = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}mpc_event_logs
			 WHERE event_id = %s AND event_name = %s AND CAST(status AS UNSIGNED) BETWEEN 200 AND 299",
			$event_id,
			'Purchase'
		) );
		return $count > 0;
	}
}
