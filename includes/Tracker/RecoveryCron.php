<?php
namespace Mpc\Tracker;

class RecoveryCron {
	/** Stop recovering an order after this many failed sends. */
	const MAX_RECOVERY_ATTEMPTS = 3;

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
			if ( $this->should_skip( $order ) ) {
				continue;
			}

			// Re-send explicitly. Previously this deleted _mpc_purchase_tracked to
			// slip past the guard; that also stripped the marker from orders whose
			// send later succeeded, so the next pass recovered them all over again.
			$capi->resend_purchase_event( $order->get_id() );
		}
	}

	/**
	 * Whether this order should be left alone by the recovery pass.
	 *
	 * True when delivery is already confirmed, OR when we have retried enough
	 * times that continuing would just re-send nightly forever — the failure mode
	 * that inflated reported conversions. Recovery is a safety net for a send that
	 * never landed, not an unbounded retry loop.
	 */
	private function should_skip( $order ) {
		if ( $order->get_meta( '_mpc_capi_sent' ) ) {
			return true;
		}

		global $wpdb;

		// Claim table: the authoritative delivery + attempt record.
		$claim = $wpdb->get_row( $wpdb->prepare(
			"SELECT delivered, attempts FROM {$wpdb->prefix}mpc_purchase_sent WHERE order_id = %d",
			$order->get_id()
		) );
		if ( $claim ) {
			if ( (int) $claim->delivered === 1 ) {
				return true;
			}
			if ( (int) $claim->attempts >= self::MAX_RECOVERY_ATTEMPTS ) {
				return true;
			}
		}
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
