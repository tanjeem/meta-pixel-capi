<?php
namespace Mpc\Events;

/**
 * Normalized, platform-agnostic tracking event.
 *
 * The Collector builds ONE of these per user action and hands it to the
 * EventBus. Each channel (Meta, GA4, TikTok, …) maps it to its own event
 * names and hashing rules. User data is stored RAW (un-hashed) here so every
 * channel can hash it according to that platform's spec.
 *
 * @package Mpc\Events
 */
class Event {

	/** Canonical event name (Meta-style vocabulary used as the internal standard). */
	public $name;

	/** Shared deduplication id used across browser + server for a single logical event. */
	public $event_id;

	/** Unix timestamp of the event. */
	public $event_time;

	/** Fully-qualified URL where the event occurred. */
	public $source_url;

	/** 'website' | 'system' (server-generated, e.g. background purchase re-send). */
	public $action_source = 'website';

	/**
	 * Raw (un-hashed) user data. Keys:
	 * email, phone, first_name, last_name, city, state, zip, country,
	 * external_id, client_ip_address, client_user_agent, fbp, fbc.
	 *
	 * @var array
	 */
	public $user = [];

	/**
	 * Commerce / custom data. Keys (all optional):
	 * value, currency, num_items, content_type, order_id,
	 * content_ids (string[]), contents (array of {id, quantity, item_price, name}),
	 * content_name, content_category, search_string, is_new_customer.
	 *
	 * @var array
	 */
	public $data = [];

	/** Whether this event should also be mirrored to the browser (via the frontend relay). */
	public $for_browser = true;

	public function __construct( $name, array $user = [], array $data = [], $event_id = null ) {
		$this->name       = $name;
		$this->user       = $user;
		$this->data       = $data;
		$this->event_id   = $event_id ?: bin2hex( random_bytes( 16 ) );
		$this->event_time = time();
	}

	/**
	 * The line items in a normalized shape.
	 *
	 * @return array[] each: [ id, quantity, item_price, name ]
	 */
	public function items() {
		return isset( $this->data['contents'] ) && is_array( $this->data['contents'] ) ? $this->data['contents'] : [];
	}

	public function value() {
		return isset( $this->data['value'] ) ? (float) $this->data['value'] : 0.0;
	}

	public function currency() {
		return $this->data['currency'] ?? ( function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD' );
	}

	/**
	 * The browser-safe subset of this event as a plain array. Never includes
	 * raw PII — only commerce data and the shared event_id, so it is safe to
	 * print into page HTML for the frontend relay.
	 *
	 * @return array
	 */
	public function to_browser_payload() {
		return [
			'name'     => $this->name,
			'event_id' => $this->event_id,
			'data'     => $this->data,
		];
	}
}
