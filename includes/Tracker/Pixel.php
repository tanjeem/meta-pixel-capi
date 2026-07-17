<?php
namespace Mpc\Tracker;

class Pixel {
	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', [ $this, 'generate_server_cookies' ] );
		add_action( 'wp_head', [ $this, 'inject_pixel_base' ], 1 );
		add_action( 'wp_footer', [ $this, 'inject_fragment_container' ], 5 );
	}

	public function generate_server_cookies() {
		// Server-Side First-Party Cookie Generation (Safari ITP Beater)
		// We set this via PHP Set-Cookie header so it lives up to 2 years instead of 7 days
		if ( ! isset( $_COOKIE['_fbp'] ) && ! headers_sent() ) {
			$timestamp = round( microtime( true ) * 1000 );
			$random_id = mt_rand( 1000000000, 9999999999 );
			$fbp       = "fb.1.{$timestamp}.{$random_id}";
			
			// Set for 2 years (63072000 seconds)
			setcookie( '_fbp', $fbp, time() + 63072000, '/' );
			$_COOKIE['_fbp'] = $fbp; // Make available immediately in this request
		}
	}

	public function inject_pixel_base() {
		$pixel_id = get_option( 'mpc_pixel_id' );
		if ( ! $pixel_id ) return;
		
		// PageView event ID
		$pageview_event_id = Deduplication::generate_event_id();

		// Read toggle settings for JS
		$enable_scroll = get_option( 'mpc_enable_scroll', 1 );
		$enable_time   = get_option( 'mpc_enable_time_on_page', 1 );
		$enable_outbound = get_option( 'mpc_enable_outbound', 1 );
		
		?>
		<!-- Meta Pixel Code -->
		<script>
		!function(f,b,e,v,n,t,s)
		{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
		n.callMethod.apply(n,arguments):n.queue.push(arguments)};
		if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
		n.queue=[];t=b.createElement(e);t.async=!0;
		t.src=v;s=b.getElementsByTagName(e)[0];
		s.parentNode.insertBefore(t,s)}(window, document,'script',
		'https://connect.facebook.net/en_US/fbevents.js');
		fbq('init', '<?php echo esc_js( $pixel_id ); ?>');

		function mpc_start_meta() {
			if (typeof fbq !== "function") { return; }
			fbq('consent', 'grant');
			fbq('track', 'PageView', {}, {eventID: '<?php echo esc_js( $pageview_event_id ); ?>'});
			mpc_init_observer();
			mpc_track_global_events();
		}

		function mpc_init_observer() {
			function checkEvents() {
				var divs = document.querySelectorAll('.mpc-ajax-event:not(.fired)');
				divs.forEach(function(div) {
					div.classList.add('fired');
					try {
						if (typeof fbq === "function") {
							fbq('track', div.getAttribute('data-event-name'), JSON.parse(div.getAttribute('data-event-data') || '{}'), {eventID: div.getAttribute('data-event-id')});
						}
					} catch (e) {
						console.warn('MPC: failed to fire event', e);
					}
				});
			}
			checkEvents();
			new MutationObserver(checkEvents).observe(document.body, { childList: true, subtree: true });
		}
		
		function mpc_track_global_events() {
			// UTM Parsing (always active)
			var urlParams = new URLSearchParams(window.location.search);
			['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'].forEach(function(param) {
				if (urlParams.has(param)) {
					document.cookie = 'mpc_' + param + '=' + encodeURIComponent(urlParams.get(param)) + '; path=/; max-age=2592000';
				}
			});

			<?php if ( $enable_scroll ) : ?>
			// Scroll Tracking
			var scrolled50 = false, scrolled90 = false;
			window.addEventListener('scroll', function() {
				var scrollTop = window.scrollY;
				var docHeight = document.body.scrollHeight;
				var winHeight = window.innerHeight;
				var scrollPercent = scrollTop / (docHeight - winHeight);
				
				if (scrollPercent > 0.5 && !scrolled50) {
					scrolled50 = true;
					fbq('trackCustom', 'ScrollDepth', { depth: 50 });
				}
				if (scrollPercent > 0.9 && !scrolled90) {
					scrolled90 = true;
					fbq('trackCustom', 'ScrollDepth', { depth: 90 });
				}
			}, {passive: true});
			<?php endif; ?>

			<?php if ( $enable_time ) : ?>
			// Time on Page (60s)
			setTimeout(function() {
				fbq('trackCustom', 'TimeOnPage', { seconds: 60 });
			}, 60000);
			<?php endif; ?>

			<?php if ( $enable_outbound ) : ?>
			// Outbound Links
			document.addEventListener('click', function(e) {
				var link = e.target.closest('a');
				if (link && link.href && link.hostname !== window.location.hostname) {
					fbq('trackCustom', 'OutboundClick', { url: link.href });
				}
			});
			<?php endif; ?>
		}

		function mpc_boot_meta() {
			// Gate on consent when a consent manager requires it; else fire now.
			if (window.mpcConsent && window.mpcConsent.cfg && window.mpcConsent.cfg.required) {
				fbq('consent', 'revoke');
				window.mpcConsent.onGrant('marketing', mpc_start_meta);
			} else {
				mpc_start_meta();
			}
		}

		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', mpc_boot_meta);
		} else {
			mpc_boot_meta();
		}
		</script>
		<!-- End Meta Pixel Code -->
		<?php

		// Dispatch Server PageView
		Capi::get_instance()->send_page_view_event( $pageview_event_id );
	}

	public function inject_fragment_container() {
		echo "<div class='mpc-fragment-events' style='display:none;'></div>";
	}
}
