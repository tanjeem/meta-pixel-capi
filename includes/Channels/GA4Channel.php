<?php
namespace Mpc\Channels;

use Mpc\Events\Event;

/**
 * Google Analytics 4 channel.
 *
 * Browser: gtag.js (loaded by the frontend relay) fires recommended events.
 * Server: the Measurement Protocol mirrors conversions server-side for
 * resilience against ad blockers, deduplicated by client_id + event.
 *
 * Options: mpc_ga4_enabled, mpc_ga4_measurement_id (G-XXXX), mpc_ga4_api_secret.
 *
 * @package Mpc\Channels
 */
class GA4Channel extends AbstractChannel {

	private static $map = [
		'ViewContent'      => 'view_item',
		'ViewCategory'     => 'view_item_list',
		'ViewCart'         => 'view_cart',
		'AddToCart'        => 'add_to_cart',
		'RemoveFromCart'   => 'remove_from_cart',
		'InitiateCheckout' => 'begin_checkout',
		'Purchase'         => 'purchase',
	];

	public function get_id() {
		return 'ga4';
	}

	public function get_label() {
		return 'Google Analytics 4';
	}

	public function is_enabled() {
		return $this->opt( 'enabled' ) && $this->opt( 'measurement_id' );
	}

	/** GA4 is analytics, not advertising. */
	public function consent_category() {
		return 'statistics';
	}

	public function browser_config() {
		if ( ! $this->is_enabled() ) {
			return [];
		}
		return [ 'measurement_id' => $this->opt( 'measurement_id' ) ];
	}

	public function handle( Event $event ) {
		// Server-side Measurement Protocol requires an API secret; without it we
		// still track fully in the browser via gtag.
		$secret = $this->opt( 'api_secret' );
		if ( ! $secret ) {
			return;
		}
		if ( ! isset( self::$map[ $event->name ] ) ) {
			return;
		}

		$client_id = $event->user['ga_client_id'] ?? $this->fallback_client_id();

		$params = [];
		if ( $event->currency() ) {
			$params['currency'] = $event->currency();
		}
		if ( $event->value() ) {
			$params['value'] = $event->value();
		}
		$items = $this->ga_items( $event );
		if ( $items ) {
			$params['items'] = $items;
		}
		if ( $event->name === 'Purchase' && ! empty( $event->data['order_id'] ) ) {
			$params['transaction_id'] = (string) $event->data['order_id'];
		}

		$payload = [
			'client_id' => $client_id,
			'events'    => [ [ 'name' => self::$map[ $event->name ], 'params' => $params ] ],
		];

		$url = add_query_arg( [
			'measurement_id' => rawurlencode( $this->opt( 'measurement_id' ) ),
			'api_secret'     => rawurlencode( $secret ),
		], 'https://www.google-analytics.com/mp/collect' );

		list( $status, $body ) = $this->post_json( $url, $payload );
		// MP returns 204 No Content on success.
		$this->log( $event, $status, $body ?: 'ga4 mp sent', $payload );
	}

	private function ga_items( Event $event ) {
		$items = [];
		foreach ( $event->items() as $i ) {
			$items[] = [
				'item_id'   => $i['id'],
				'item_name' => $i['name'] ?? '',
				'quantity'  => (int) ( $i['quantity'] ?? 1 ),
				'price'     => (float) ( $i['item_price'] ?? 0 ),
			];
		}
		return $items;
	}

	private function fallback_client_id() {
		return wp_rand( 100000000, 999999999 ) . '.' . time();
	}
}
