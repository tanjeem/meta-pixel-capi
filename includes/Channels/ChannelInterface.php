<?php
namespace Mpc\Channels;

use Mpc\Events\Event;

/**
 * Contract every tracking platform channel must implement.
 *
 * @package Mpc\Channels
 */
interface ChannelInterface {

	/** Machine id, e.g. 'meta', 'ga4', 'tiktok'. */
	public function get_id();

	/** Human label for the admin UI. */
	public function get_label();

	/** Whether the channel is configured and switched on. */
	public function is_enabled();

	/** Consent category this channel needs: 'marketing' or 'statistics'. */
	public function consent_category();

	/** Handle a server-side event (send to the platform's server API). */
	public function handle( Event $event );

	/**
	 * Config the frontend relay needs to fire this platform's browser pixel,
	 * or an empty array if the channel has no browser side. Must never contain
	 * secrets (API tokens) — only public pixel/measurement ids.
	 *
	 * @return array
	 */
	public function browser_config();
}
