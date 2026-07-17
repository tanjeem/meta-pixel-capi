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

		global $wpdb;
		
		$query = $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts}
			 WHERE post_type = 'shop_order'
			 AND post_status IN ('wc-processing', 'wc-completed')
			 AND post_date >= %s
			 AND post_date <= %s",
			date( 'Y-m-d H:i:s', strtotime( '-24 hours' ) ),
			date( 'Y-m-d H:i:s' )
		);
		
		$orders = $wpdb->get_col( $query );

		if ( empty( $orders ) ) return;

		$capi = Capi::get_instance();

		foreach ( $orders as $order_id ) {
			$sent = get_post_meta( $order_id, '_mpc_capi_sent', true );
			
			if ( ! $sent ) {
				delete_post_meta( $order_id, '_mpc_purchase_tracked' );
				$capi->send_purchase_event_server_only( $order_id );
			}
		}
	}
}
