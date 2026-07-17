<?php
namespace Mpc\Events;

use Mpc\Channels\ChannelInterface;
use Mpc\Consent\ConsentManager;

/**
 * Central event bus.
 *
 * Channels register themselves once; every Event dispatched here is offered to
 * each enabled channel. This is the seam that lets us add tracking platforms
 * without touching the WooCommerce collection logic.
 *
 * @package Mpc\Events
 */
class EventBus {
	private static $instance = null;

	/** @var ChannelInterface[] */
	private $channels = [];

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register( ChannelInterface $channel ) {
		$this->channels[ $channel->get_id() ] = $channel;
	}

	/** @return ChannelInterface[] */
	public function get_channels() {
		return $this->channels;
	}

	/**
	 * Hand an event to every enabled channel. A failure in one channel must
	 * never break another, so each handler is isolated.
	 */
	public function dispatch( Event $event ) {
		foreach ( $this->channels as $channel ) {
			if ( ! $channel->is_enabled() ) {
				continue;
			}
			// Respect GDPR consent for server-side dispatch.
			if ( ! ConsentManager::allows( $channel->consent_category() ) ) {
				continue;
			}
			try {
				$channel->handle( $event );
			} catch ( \Throwable $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[MPC] Channel ' . $channel->get_id() . ' failed: ' . $e->getMessage() );
				}
			}
		}
	}

	/**
	 * Collect browser-side payloads from every enabled channel so the frontend
	 * relay can fire the matching client-side pixels. Returns a map of
	 * channel_id => adapter config the frontend understands.
	 *
	 * @return array
	 */
	public function browser_adapters() {
		$adapters = [];
		foreach ( $this->channels as $channel ) {
			if ( $channel->is_enabled() ) {
				$config = $channel->browser_config();
				if ( ! empty( $config ) ) {
					$adapters[ $channel->get_id() ] = $config;
				}
			}
		}
		return $adapters;
	}
}
