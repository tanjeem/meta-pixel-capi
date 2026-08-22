<?php
namespace Mpc\Diagnostics;

/**
 * Finds OTHER things on this site that also send events to Meta.
 *
 * Written after an install reported far more Purchase conversions in Events
 * Manager than this plugin's own log could account for. When that happens the
 * duplicate is not coming from here, and the useful question becomes: what else
 * is talking to the same dataset?
 *
 * Three independent checks, because each catches a case the others miss:
 *
 *  1. Active plugins known to send Meta events, and whether any is configured
 *     with the SAME pixel id — a same-pixel match is the strongest local signal.
 *  2. What the site actually renders: every fbq('init', ...) in the real HTML.
 *     Catches pixels hard-coded into a theme, injected by a page builder, or
 *     fired through a tag manager, none of which appear in a plugin list.
 *  3. The dataset itself over the Graph API, best effort — Meta exposes no
 *     supported endpoint listing a dataset's server-side integrations, so this
 *     reports what it can and says plainly what it cannot.
 *
 * @package Mpc\Diagnostics
 */
class ConflictScanner {

	/**
	 * Plugins known to send Meta pixel or Conversions API events, mapped to the
	 * options they keep their pixel id in.
	 */
	const KNOWN_PLUGINS = [
		'facebook-for-woocommerce/facebook-for-woocommerce.php' => [
			'label'   => 'Facebook for WooCommerce (official)',
			'options' => [ 'wc_facebook_pixel_id' ],
			'capi'    => true,
		],
		'pixelyoursite/facebook-pixel-master.php' => [
			'label'   => 'PixelYourSite',
			'options' => [ 'pys_facebook_pixel_id' ],
			'capi'    => true,
		],
		'pixelyoursite-pro/pixelyoursite-pro.php' => [
			'label'   => 'PixelYourSite Pro',
			'options' => [ 'pys_facebook_pixel_id' ],
			'capi'    => true,
		],
		'pixel-manager-for-woocommerce/pixel-manager-for-woocommerce.php' => [
			'label'   => 'Pixel Manager for WooCommerce',
			'options' => [],
			'capi'    => true,
		],
		'duracelltomi-google-tag-manager/duracelltomi-google-tag-manager.php' => [
			'label'   => 'GTM4WP (Google Tag Manager)',
			'options' => [],
			'capi'    => false,
		],
		'facebook-conversion-pixel/facebook-conversion-pixel.php' => [
			'label'   => 'Facebook Conversion Pixel',
			'options' => [],
			'capi'    => false,
		],
	];

	/** Slug fragments that suggest a tracking plugin we do not know by name. */
	const SUSPICIOUS_SLUG_PARTS = [ 'facebook', 'meta-pixel', 'metapixel', 'fb-pixel', 'fbpixel', 'pixel', 'conversion-api', 'conversions-api', 'capi', 'tag-manager', 'gtm' ];

	/**
	 * Run every check.
	 *
	 * @return array
	 */
	public static function scan() {
		$our_pixel = trim( (string) get_option( 'mpc_pixel_id' ) );

		return [
			'our_pixel_id' => $our_pixel,
			'plugins'      => self::scan_plugins( $our_pixel ),
			'rendered'     => self::scan_rendered_html( $our_pixel ),
			'dataset'      => self::scan_dataset(),
		];
	}

	// ── 1. Active plugins ────────────────────────────────────────

	private static function scan_plugins( $our_pixel ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$active = (array) get_option( 'active_plugins', [] );
		if ( is_multisite() ) {
			$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', [] ) ) );
		}
		$all   = get_plugins();
		$self  = plugin_basename( MPC_PLUGIN_FILE );
		$found = [];

		foreach ( $active as $file ) {
			if ( $file === $self ) {
				continue;
			}

			$known = self::KNOWN_PLUGINS[ $file ] ?? null;
			$slug  = strtolower( $file );
			$name  = $all[ $file ]['Name'] ?? $file;

			$matches_keyword = false;
			foreach ( self::SUSPICIOUS_SLUG_PARTS as $part ) {
				if ( strpos( $slug, $part ) !== false || strpos( strtolower( $name ), $part ) !== false ) {
					$matches_keyword = true;
					break;
				}
			}

			if ( ! $known && ! $matches_keyword ) {
				continue;
			}

			$entry = [
				'file'            => $file,
				'name'            => $name,
				'known_sender'    => (bool) $known,
				'label'           => $known['label'] ?? null,
				'sends_capi'      => $known['capi'] ?? null,
				'pixel_ids_found' => [],
				'same_pixel'      => false,
			];

			foreach ( ( $known['options'] ?? [] ) as $option ) {
				$value = get_option( $option );
				if ( is_string( $value ) && $value !== '' ) {
					$entry['pixel_ids_found'][ $option ] = $value;
					if ( $our_pixel !== '' && trim( $value ) === $our_pixel ) {
						$entry['same_pixel'] = true;
					}
				}
			}

			$found[] = $entry;
		}

		return $found;
	}

	// ── 2. What the site actually renders ────────────────────────

	private static function scan_rendered_html( $our_pixel ) {
		$urls = [ home_url( '/' ) ];
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$shop = wc_get_page_permalink( 'shop' );
			if ( $shop ) {
				$urls[] = $shop;
			}
		}

		$result = [
			'checked_urls'      => [],
			'pixel_ids_in_html' => [],
			'fbq_init_count'    => 0,
			'facebook_script_refs' => 0,
			'gtm_containers'    => [],
			'error'             => null,
		];

		foreach ( $urls as $url ) {
			$response = wp_remote_get( $url, [
				'timeout'    => 15,
				'user-agent' => 'MPC-ConflictScanner/1.0',
				'headers'    => [ 'Cache-Control' => 'no-cache' ],
			] );

			if ( is_wp_error( $response ) ) {
				$result['error'] = $response->get_error_message();
				continue;
			}

			$body = (string) wp_remote_retrieve_body( $response );
			$result['checked_urls'][] = $url;

			// fbq('init', '123...') / fbq("init","123...")
			if ( preg_match_all( '/fbq\s*\(\s*[\'"]init[\'"]\s*,\s*[\'"]([0-9]{5,25})[\'"]/i', $body, $m ) ) {
				$result['fbq_init_count'] += count( $m[1] );
				foreach ( $m[1] as $id ) {
					$result['pixel_ids_in_html'][ $id ] = ( $result['pixel_ids_in_html'][ $id ] ?? 0 ) + 1;
				}
			}

			$result['facebook_script_refs'] += substr_count( $body, 'connect.facebook.net' );

			if ( preg_match_all( '/GTM-[A-Z0-9]{4,10}/', $body, $g ) ) {
				foreach ( array_unique( $g[0] ) as $container ) {
					$result['gtm_containers'][ $container ] = true;
				}
			}
		}

		$result['gtm_containers'] = array_keys( $result['gtm_containers'] );

		// This plugin renders exactly one init per page. More inits than pages
		// checked, or an id that is not ours, means something else is adding one.
		$result['extra_pixel_ids'] = [];
		foreach ( $result['pixel_ids_in_html'] as $id => $count ) {
			if ( $our_pixel === '' || (string) $id !== $our_pixel ) {
				$result['extra_pixel_ids'][] = (string) $id;
			}
		}
		$pages = max( 1, count( $result['checked_urls'] ) );
		$result['duplicate_init_of_our_pixel'] = ( $our_pixel !== '' && ( $result['pixel_ids_in_html'][ $our_pixel ] ?? 0 ) > $pages );

		return $result;
	}

	// ── 3. The dataset at Meta (best effort) ─────────────────────

	private static function scan_dataset() {
		$pixel = get_option( 'mpc_pixel_id' );
		$token = get_option( 'mpc_capi_token' );

		$out = [
			'queried'             => false,
			'name'                => null,
			'owner_ad_account_id' => null,
			'note'                => 'Meta exposes no supported endpoint listing a dataset\'s server-side integrations, so partner and CAPI Gateway connections cannot be enumerated from here. Check Events Manager -> your dataset -> Settings for those.',
			'error'               => null,
		];

		if ( ! $pixel || ! $token ) {
			$out['error'] = 'Pixel ID or access token not configured.';
			return $out;
		}

		$url = 'https://graph.facebook.com/' . MPC_GRAPH_VERSION . '/' . rawurlencode( $pixel )
			. '?fields=name,creation_time,owner_ad_account'
			. '&access_token=' . rawurlencode( $token );

		$response = wp_remote_get( $url, [ 'timeout' => 15 ] );
		if ( is_wp_error( $response ) ) {
			$out['error'] = $response->get_error_message();
			return $out;
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			$out['error'] = 'Unreadable response from the Graph API.';
			return $out;
		}
		if ( isset( $body['error'] ) ) {
			$out['error'] = $body['error']['message'] ?? 'Graph API error.';
			return $out;
		}

		$out['queried']             = true;
		$out['name']                = $body['name'] ?? null;
		$out['creation_time']       = $body['creation_time'] ?? null;
		$out['owner_ad_account_id'] = $body['owner_ad_account']['account_id'] ?? null;

		return $out;
	}
}
