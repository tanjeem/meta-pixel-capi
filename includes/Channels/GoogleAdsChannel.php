<?php
namespace Mpc\Channels;

use Mpc\Events\Event;

/**
 * Google Ads conversion channel.
 *
 * Browser-only: gtag fires a `conversion` event on Purchase, scoped to the
 * configured conversion id + label. Enhanced Conversions (hashed first-party
 * data) is handled by Google Ads' own automatic collection when enabled in the
 * Ads UI; the server-side Ads API path requires OAuth and is out of scope here.
 *
 * Options: mpc_google_ads_enabled, mpc_google_ads_conversion_id (AW-XXXXXXXXX),
 * mpc_google_ads_purchase_label.
 *
 * @package Mpc\Channels
 */
class GoogleAdsChannel extends AbstractChannel {

	public function get_id() {
		return 'google_ads';
	}

	public function get_label() {
		return 'Google Ads';
	}

	public function is_enabled() {
		return $this->opt( 'enabled' ) && $this->opt( 'conversion_id' );
	}

	public function browser_config() {
		if ( ! $this->is_enabled() ) {
			return [];
		}
		return [
			'conversion_id' => $this->opt( 'conversion_id' ),
			'purchase_label' => $this->opt( 'purchase_label' ),
		];
	}

	/** No server-side dispatch — Google Ads conversions fire in the browser. */
	public function handle( Event $event ) {
	}
}
