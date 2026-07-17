<?php
namespace Mpc\Channels;

use Mpc\Events\Event;

/**
 * Snapchat channel.
 *
 * Browser: the Snap Pixel (snaptr), loaded by the frontend relay.
 * Server: the Snapchat Conversions API (v3, Meta-CAPI-compatible shape) mirrors
 * conversions with hashed match keys, deduplicated by a shared event_id.
 *
 * Options: mpc_snapchat_enabled, mpc_snapchat_pixel_id, mpc_snapchat_access_token.
 *
 * @package Mpc\Channels
 */
class SnapchatChannel extends AbstractChannel {

	private static $map = [
		'PageView'         => 'PAGE_VIEW',
		'ViewContent'      => 'VIEW_CONTENT',
		'AddToCart'        => 'ADD_CART',
		'InitiateCheckout' => 'START_CHECKOUT',
		'Purchase'         => 'PURCHASE',
	];

	public function get_id() {
		return 'snapchat';
	}

	public function get_label() {
		return 'Snapchat';
	}

	public function is_enabled() {
		return $this->opt( 'enabled' ) && $this->opt( 'pixel_id' );
	}

	public function browser_config() {
		if ( ! $this->is_enabled() ) {
			return [];
		}
		return [ 'pixel_id' => $this->opt( 'pixel_id' ) ];
	}

	public function handle( Event $event ) {
		$token = $this->opt( 'access_token' );
		if ( ! $token || ! isset( self::$map[ $event->name ] ) ) {
			return;
		}

		$user_data = [
			'client_ip_address' => $event->user['client_ip_address'] ?? '',
			'client_user_agent' => $event->user['client_user_agent'] ?? '',
		];
		if ( ! empty( $event->user['email'] ) ) {
			$user_data['em'] = [ $this->hash( $event->user['email'] ) ];
		}
		if ( ! empty( $event->user['phone'] ) ) {
			$user_data['ph'] = [ $this->hash_phone( $event->user['phone'] ) ];
		}
		if ( ! empty( $event->user['sc_click'] ) ) {
			$user_data['sc_click_id'] = $event->user['sc_click'];
		}
		if ( ! empty( $event->user['scid'] ) ) {
			$user_data['sc_cookie1'] = $event->user['scid'];
		}

		$custom_data = array_filter( [
			'currency'    => $event->currency(),
			'value'       => $event->value() ? (string) $event->value() : '',
			'content_ids' => $event->data['content_ids'] ?? [],
			'num_items'   => $event->data['num_items'] ?? null,
			'order_id'    => $event->data['order_id'] ?? '',
		] );

		$payload = [
			'data' => [ [
				'event_name'       => self::$map[ $event->name ],
				'event_time'       => $event->event_time * 1000, // Snap expects milliseconds.
				'action_source'    => 'WEB',
				'event_id'         => $event->event_id,
				'event_source_url' => $event->source_url ?: home_url( '/' ),
				'user_data'        => array_filter( $user_data ),
				'custom_data'      => $custom_data,
			] ],
		];

		$url = add_query_arg(
			[ 'access_token' => rawurlencode( $token ) ],
			'https://tr.snapchat.com/v3/' . rawurlencode( $this->opt( 'pixel_id' ) ) . '/events'
		);
		list( $status, $body ) = $this->post_json( $url, $payload );
		$this->log( $event, $status, $body, $payload );
	}
}
