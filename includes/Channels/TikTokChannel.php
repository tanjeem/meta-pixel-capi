<?php
namespace Mpc\Channels;

use Mpc\Events\Event;

/**
 * TikTok channel.
 *
 * Browser: the TikTok Pixel (ttq), loaded by the frontend relay.
 * Server: the TikTok Events API v1.3 mirrors conversions with hashed match keys,
 * deduplicated against the browser pixel by a shared event_id.
 *
 * Options: mpc_tiktok_enabled, mpc_tiktok_pixel_code, mpc_tiktok_access_token.
 *
 * @package Mpc\Channels
 */
class TikTokChannel extends AbstractChannel {

	private static $map = [
		'ViewContent'      => 'ViewContent',
		'AddToCart'        => 'AddToCart',
		'InitiateCheckout' => 'InitiateCheckout',
		'Purchase'         => 'CompletePayment',
	];

	public function get_id() {
		return 'tiktok';
	}

	public function get_label() {
		return 'TikTok';
	}

	public function is_enabled() {
		return $this->opt( 'enabled' ) && $this->opt( 'pixel_code' );
	}

	public function browser_config() {
		if ( ! $this->is_enabled() ) {
			return [];
		}
		return [ 'pixel_code' => $this->opt( 'pixel_code' ) ];
	}

	public function handle( Event $event ) {
		$token = $this->opt( 'access_token' );
		if ( ! $token || ! isset( self::$map[ $event->name ] ) ) {
			return;
		}

		$user = array_filter( [
			'email'       => $this->hash( $event->user['email'] ?? '' ),
			'phone'       => $this->hash_phone( $event->user['phone'] ?? '' ),
			'external_id' => isset( $event->user['external_id'] ) ? $this->hash( $event->user['external_id'] ) : '',
			'ttp'         => $event->user['ttp'] ?? '',
			'ttclid'      => $event->user['ttclid'] ?? '',
			'ip'          => $event->user['client_ip_address'] ?? '',
			'user_agent'  => $event->user['client_user_agent'] ?? '',
		] );

		$contents = [];
		foreach ( $event->items() as $i ) {
			$contents[] = [
				'content_id'   => (string) $i['id'],
				'content_type' => 'product',
				'content_name' => $i['name'] ?? '',
				'price'        => (float) ( $i['item_price'] ?? 0 ),
				'quantity'     => (int) ( $i['quantity'] ?? 1 ),
			];
		}

		$data = [
			'event'      => self::$map[ $event->name ],
			'event_time' => $event->event_time,
			'event_id'   => $event->event_id,
			'user'       => $user,
			'page'       => [ 'url' => $event->source_url ?: home_url( '/' ) ],
			'properties' => array_filter( [
				'contents' => $contents,
				'currency' => $event->currency(),
				'value'    => $event->value(),
			] ),
		];

		$payload = [
			'event_source'    => 'web',
			'event_source_id' => $this->opt( 'pixel_code' ),
			'data'            => [ $data ],
		];

		list( $status, $body ) = $this->post_json(
			'https://business-api.tiktok.com/open_api/v1.3/event/track/',
			$payload,
			[ 'Access-Token' => $token ]
		);
		$this->log( $event, $status, $body, $payload );
	}
}
