<?php
namespace Mpc\Channels;

use Mpc\Events\Event;

/**
 * Shared behaviour for platform channels: option access, SHA-256 hashing that
 * matches the normalisation rules the ad platforms expect, and an event-log
 * writer so every channel's traffic shows up in the same dashboard.
 *
 * @package Mpc\Channels
 */
abstract class AbstractChannel implements ChannelInterface {

	public function browser_config() {
		return [];
	}

	/** Ad platforms need marketing consent; analytics channels override this. */
	public function consent_category() {
		return 'marketing';
	}

	/** Read a channel option (namespaced as mpc_{id}_{key}). */
	protected function opt( $key, $default = '' ) {
		return get_option( 'mpc_' . $this->get_id() . '_' . $key, $default );
	}

	/** SHA-256 with lower-casing + trimming, the standard for hashed match keys. */
	protected function hash( $value ) {
		$value = trim( strtolower( (string) $value ) );
		return $value === '' ? '' : hash( 'sha256', $value );
	}

	/** SHA-256 for phone numbers: strip everything but digits first. */
	protected function hash_phone( $value ) {
		$value = preg_replace( '/[^0-9]/', '', (string) $value );
		return $value === '' ? '' : hash( 'sha256', $value );
	}

	/** SHA-256 for zip/city: remove whitespace, lower-case. */
	protected function hash_compact( $value ) {
		$value = strtolower( preg_replace( '/\s+/', '', (string) $value ) );
		return $value === '' ? '' : hash( 'sha256', $value );
	}

	/**
	 * Write a row to the shared event log so multi-platform traffic is visible
	 * in one place. Mirrors the columns used by the Meta path.
	 */
	protected function log( Event $event, $status, $response, array $payload = [] ) {
		global $wpdb;
		$wpdb->insert(
			"{$wpdb->prefix}mpc_event_logs",
			[
				'event_name' => $event->name,
				'event_id'   => $event->event_id,
				'event_type' => $this->get_id(),
				'payload'    => wp_json_encode( $payload ),
				'status'     => (string) $status,
				'response'   => is_string( $response ) ? $response : wp_json_encode( $response ),
			]
		);
	}

	/**
	 * POST JSON to a platform endpoint, non-perceived (the caller runs after
	 * fastcgi_finish_request). Returns [ status, body ].
	 *
	 * @return array{0:int,1:string}
	 */
	protected function post_json( $url, array $payload, array $headers = [] ) {
		$response = wp_remote_post( $url, [
			'body'    => wp_json_encode( $payload ),
			'headers' => array_merge( [ 'Content-Type' => 'application/json' ], $headers ),
			'timeout' => 10,
		] );

		if ( is_wp_error( $response ) ) {
			return [ 500, $response->get_error_message() ];
		}
		return [ (int) wp_remote_retrieve_response_code( $response ), wp_remote_retrieve_body( $response ) ];
	}
}
