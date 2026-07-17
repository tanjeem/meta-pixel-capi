<?php
namespace Mpc\Consent;

/**
 * Consent / GDPR gating for all tracking (Meta + bus channels).
 *
 * Supports three sources, chosen in settings:
 *   - none            : consent not required (default — preserves legacy behaviour).
 *   - wp_consent_api  : integrates with the WP Consent API standard, which
 *                       CookieYes, Complianz, Borlabs, Cookiebot etc. implement.
 *   - cookie          : a named cookie must equal a given value.
 *
 * Also emits Google Consent Mode v2 default/update signals when enabled, so
 * Google's own modelling works even before consent is granted.
 *
 * @package Mpc\Consent
 */
class ConsentManager {
	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Tell WP Consent API plugins that we honour consent.
		add_filter( 'wp_consent_api_registered_' . plugin_basename( MPC_PLUGIN_FILE ), '__return_true' );

		// The consent bootstrap must run before any pixel — priority 0 in <head>.
		add_action( 'wp_head', [ $this, 'print_consent_bootstrap' ], 0 );
	}

	public static function is_required() {
		$provider = get_option( 'mpc_consent_provider', 'none' );
		return get_option( 'mpc_consent_required', 0 ) && $provider !== 'none';
	}

	/**
	 * Server-side consent check for a category ('marketing' | 'statistics').
	 *
	 * Background/admin contexts (cron recovery, admin order-status changes) are
	 * always allowed: those are server-side conversions of completed orders, not
	 * browser tracking. Front-end requests carry the consent cookie and are gated.
	 */
	public static function allows( $category = 'marketing' ) {
		if ( ! self::is_required() ) {
			return true;
		}
		if ( wp_doing_cron() || is_admin() ) {
			return true;
		}

		$provider = get_option( 'mpc_consent_provider', 'none' );

		if ( $provider === 'wp_consent_api' ) {
			if ( function_exists( 'wp_has_consent' ) ) {
				return (bool) wp_has_consent( $category );
			}
			return false; // API not available but required -> deny.
		}

		if ( $provider === 'cookie' ) {
			$name  = get_option( 'mpc_consent_cookie_name', '' );
			$value = get_option( 'mpc_consent_cookie_value', '' );
			if ( ! $name ) {
				return false;
			}
			return isset( $_COOKIE[ $name ] ) && ( $value === '' || $_COOKIE[ $name ] === $value );
		}

		return false;
	}

	/** Config the front-end consent bootstrap + Consent Mode v2 default. */
	public function print_consent_bootstrap() {
		if ( is_admin() ) {
			return;
		}
		$required = self::is_required();

		// Only act when we are the consent authority (gating enabled). Otherwise a
		// site's own CMP owns the Consent Mode signals and we must not fight it.
		if ( ! $required ) {
			return;
		}
		$consent_mode = (bool) get_option( 'mpc_consent_mode_v2', 0 );

		$config = [
			'required'    => $required,
			'provider'    => get_option( 'mpc_consent_provider', 'none' ),
			'cookieName'  => get_option( 'mpc_consent_cookie_name', '' ),
			'cookieValue' => get_option( 'mpc_consent_cookie_value', '' ),
			'consentMode' => $consent_mode,
		];
		?>
<script>
/* MPC consent bootstrap */
(function () {
	var CFG = <?php echo wp_json_encode( $config ); ?>;
	if (CFG.consentMode) {
		window.dataLayer = window.dataLayer || [];
		if (!window.gtag) { window.gtag = function () { window.dataLayer.push(arguments); }; }
		window.gtag('consent', 'default', {
			ad_storage: 'denied', analytics_storage: 'denied',
			ad_user_data: 'denied', ad_personalization: 'denied',
			wait_for_update: 500
		});
	}
	var mpcConsent = {
		cfg: CFG,
		_cbs: [],
		granted: function (category) {
			if (!CFG.required) { return true; }
			if (CFG.provider === 'cookie') {
				if (!CFG.cookieName) { return false; }
				var m = document.cookie.match(new RegExp('(?:^|; )' + CFG.cookieName.replace(/([.*+?^${}()|[\]\\])/g, '\\$1') + '=([^;]*)'));
				if (!m) { return false; }
				return CFG.cookieValue === '' ? true : decodeURIComponent(m[1]) === CFG.cookieValue;
			}
			if (CFG.provider === 'wp_consent_api') {
				return (typeof window.wp_has_consent === 'function') ? !!window.wp_has_consent(category) : false;
			}
			return false;
		},
		_pushGoogleUpdate: function () {
			if (!CFG.consentMode || !window.gtag) { return; }
			var ad = this.granted('marketing') ? 'granted' : 'denied';
			var an = this.granted('statistics') ? 'granted' : 'denied';
			window.gtag('consent', 'update', {
				ad_storage: ad, analytics_storage: an,
				ad_user_data: ad, ad_personalization: ad
			});
		},
		onGrant: function (category, cb) {
			if (this.granted(category)) { cb(); } else { this._cbs.push({ c: category, cb: cb }); }
		},
		recheck: function () {
			this._pushGoogleUpdate();
			var self = this;
			this._cbs = this._cbs.filter(function (x) {
				if (self.granted(x.c)) { try { x.cb(); } catch (e) {} return false; }
				return true;
			});
		}
	};
	window.mpcConsent = mpcConsent;
	mpcConsent._pushGoogleUpdate();
	// React to consent changes from the CMP.
	document.addEventListener('wp_listen_for_consent_change', function () { mpcConsent.recheck(); });
	document.addEventListener('mpc_consent_granted', function () { mpcConsent.recheck(); });
})();
</script>
		<?php
	}
}
