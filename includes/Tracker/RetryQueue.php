<?php
namespace Mpc\Tracker;

class RetryQueue {
	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'mpc_retry_failed_events', [ $this, 'process_queue' ] );
	}

	public static function add_to_queue( $payload ) {
		global $wpdb;
		$wpdb->insert(
			"{$wpdb->prefix}mpc_retry_queue",
			array(
				'payload' => wp_json_encode( $payload ),
				'status' => 'pending'
			)
		);
	}

	public function process_queue() {
		global $wpdb;
		$items = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}mpc_retry_queue WHERE status = 'pending' AND attempts < 3 LIMIT 20" );

		$token = get_option( 'mpc_capi_token' );
		$pixel_id = get_option( 'mpc_pixel_id' );

		if ( ! $token || ! $pixel_id ) return;

		$url = "https://graph.facebook.com/" . MPC_GRAPH_VERSION . "/{$pixel_id}/events?access_token={$token}";

		foreach ( $items as $item ) {
			$response = wp_remote_post( $url, array(
				'body'    => $item->payload,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'timeout' => 15,
			) );

			if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
				$wpdb->update( "{$wpdb->prefix}mpc_retry_queue", [ 'status' => 'success' ], [ 'id' => $item->id ] );
			} else {
				$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}mpc_retry_queue SET attempts = attempts + 1 WHERE id = %d", $item->id ) );
			}
		}

		// Auto-purge successful items and items older than 7 days
		$wpdb->query( "DELETE FROM {$wpdb->prefix}mpc_retry_queue WHERE status = 'success' OR created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)" );
	}
}
