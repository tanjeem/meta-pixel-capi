<?php
namespace Mpc\Channels;

use Mpc\Events\Event;

/**
 * Pinterest channel.
 *
 * Browser: the Pinterest Tag (pintrk), loaded by the frontend relay.
 * Server: the Pinterest Conversions API (v5) mirrors conversions with hashed
 * match keys, deduplicated by a shared event_id.
 *
 * Options: mpc_pinterest_enabled, mpc_pinterest_tag_id,
 * mpc_pinterest_ad_account_id, mpc_pinterest_access_token.
 *
 * @package Mpc\Channels
 */
class PinterestChannel extends AbstractChannel {

	private static $map = [
		'PageView'     => 'page_visit',
		'ViewCategory' => 'view_category',
		'AddToCart'    => 'add_to_cart',
		'Purchase'     => 'checkout',
	];

	public function get_id() {
		return 'pinterest';
	}

	public function get_label() {
		return 'Pinterest';
	}

	public function is_enabled() {
		return $this->opt( 'enabled' ) && $this->opt( 'tag_id' );
	}

	public function browser_config() {
		if ( ! $this->is_enabled() ) {
			return [];
		}
		return [ 'tag_id' => $this->opt( 'tag_id' ) ];
	}

	public function handle( Event $event ) {
		$token      = $this->opt( 'access_token' );
		$ad_account = $this->opt( 'ad_account_id' );
		if ( ! $token || ! $ad_account || ! isset( self::$map[ $event->name ] ) ) {
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
		if ( ! empty( $event->user['epik'] ) ) {
			$user_data['click_id'] = $event->user['epik'];
		}

		$contents = [];
		foreach ( $event->items() as $i ) {
			$contents[] = [
				'id'         => (string) $i['id'],
				'item_price' => (string) ( $i['item_price'] ?? 0 ),
				'quantity'   => (int) ( $i['quantity'] ?? 1 ),
			];
		}

		$custom_data = array_filter( [
			'currency'    => $event->currency(),
			'value'       => $event->value() ? (string) $event->value() : '',
			'content_ids' => $event->data['content_ids'] ?? [],
			'contents'    => $contents,
			'num_items'   => $event->data['num_items'] ?? null,
			'order_id'    => $event->data['order_id'] ?? '',
		] );

		$payload = [
			'data' => [ [
				'event_name'       => self::$map[ $event->name ],
				'action_source'    => 'web',
				'event_time'       => $event->event_time,
				'event_id'         => $event->event_id,
				'event_source_url' => $event->source_url ?: home_url( '/' ),
				'user_data'        => array_filter( $user_data ),
				'custom_data'      => $custom_data,
			] ],
		];

		$url = 'https://api.pinterest.com/v5/ad_accounts/' . rawurlencode( $ad_account ) . '/events';
		list( $status, $body ) = $this->post_json( $url, $payload, [ 'Authorization' => 'Bearer ' . $token ] );
		$this->log( $event, $status, $body, $payload );
	}
}
