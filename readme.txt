=== Meta Pixel & CAPI Ultimate for WooCommerce ===
Contributors: tanjeem
Tags: facebook pixel, conversions api, woocommerce, meta pixel, capi
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 2.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Advanced Meta (Facebook) Pixel + Conversions API for WooCommerce with server-side tracking, strict deduplication, and higher Event Match Quality.

== Description ==

Meta Pixel & CAPI Ultimate is a server-side-first tracking solution for WooCommerce stores that want accurate conversion data despite iOS restrictions, ad blockers, and browser tracking-prevention (Safari ITP).

Every standard event is fired **both** in the browser (Meta Pixel) and on the server (Conversions API) using a shared `event_id`, so Meta can deduplicate reliably and you get the coverage of server-side tracking without double-counting.

**Core features**

* Browser Pixel + server-side Conversions API for all key WooCommerce events (PageView, ViewContent, ViewCategory, ViewCart, AddToCart, RemoveFromCart, InitiateCheckout, Purchase).
* Strict browser/server deduplication via a shared event ID.
* Batched, non-blocking CAPI dispatch — events are bundled into a single request per page load and sent without delaying page delivery.
* Classic **and** Block (Cart & Checkout Blocks) checkout support.
* High Match Quality: hashed customer PII (email, phone, name, address), `fbp`/`fbc` cookies, IP, user agent, external ID.
* Server-side first-party `_fbp` cookie generation to survive Safari ITP.
* Advanced signals: customer lifetime value, total orders, new-vs-returning customer flag, UTM parameters.
* Automatic Conversion Recovery — a nightly job re-sends any Purchase that never reached Meta.
* Retry queue for failed CAPI requests.
* Facebook Catalog RSS feed for Commerce Manager / dynamic ads.
* Abandoned cart recovery emails.
* Fake-order protection and phone/email blocklist.
* Event log dashboard with per-event Match Quality indicators and a token connection tester.

**Multi-platform tracking (beyond Meta)**

A shared event engine mirrors your WooCommerce conversions to additional ad platforms — browser pixel + server-side API, deduplicated by a shared event id:

* Google Analytics 4 (gtag + Measurement Protocol)
* Google Ads (conversion tracking)
* TikTok (Pixel + Events API)
* Pinterest (Tag + Conversions API)
* Snapchat (Pixel + Conversions API)

Each platform is enabled independently from the Integrations tab; server-side tracking activates when you add that platform's API token.

**High-Performance Order Storage (HPOS) compatible.**

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/meta-pixel-capi`, or install through the WordPress Plugins screen.
2. Activate the plugin through the "Plugins" screen in WordPress.
3. Go to **Pixel & CAPI** in the admin menu.
4. Enter your Meta Pixel ID and a Conversions API access token (from Events Manager → Settings).
5. Use **Test Token Connection** to confirm the credentials work, then save.

== Frequently Asked Questions ==

= Does this require WooCommerce? =

Yes. The plugin is built specifically for WooCommerce stores.

= Is it compatible with High-Performance Order Storage (HPOS)? =

Yes. All order data is read and written through the WooCommerce CRUD layer, and the plugin declares HPOS and Cart & Checkout Blocks compatibility.

= Will events be double-counted in Meta? =

No. Browser and server events share an `event_id` so Meta deduplicates them automatically.

= Where do I get a Conversions API token? =

In Meta Events Manager, open your dataset/pixel, go to Settings → Conversions API → Generate access token.

== Changelog ==

= 2.2.0 =
* Improved: Advanced Matching on the browser pixel. The Meta Pixel now sends hashed customer data (email, phone, name, city, state, ZIP, country, external id) in the browser too — previously only the server (CAPI) carried it, which halved the coverage of every customer parameter. On the thank-you page the full order is used for the richest Purchase match. Raw PII is never exposed — values are pre-hashed.

= 2.1.0 =
* Improved: Event Match Quality. fbp, fbc and a persistent first-party visitor id are now saved onto the order at checkout and re-attached to server-side Purchase sends (order-status hooks + recovery cron), so those events no longer lose fbp/fbc when browser cookies are unavailable.
* Improved: external_id is now sent for guests too (via a persistent first-party visitor id), not only logged-in users — raising external_id coverage across all events.

= 2.0.0 =
* New: multi-platform tracking engine — Google Analytics 4, Google Ads, TikTok, Pinterest and Snapchat, each with browser pixel + server-side API, deduplicated by a shared event id.
* New: shared event bus architecture so tracking platforms can be added without touching WooCommerce collection logic.
* New: Consent & Privacy — GDPR consent gating via the WP Consent API (CookieYes, Complianz, Borlabs, Cookiebot…) or a custom cookie rule, plus Google Consent Mode v2 signals. All six platforms are gated, browser and server.
* New: redesigned dashboard with a tracking-health score, setup checklist, live platform-status grid, and per-platform event-log filtering.
* Fixed: white-on-white text on the dashboard Quick Info card.
* Improved: event log now records and labels every platform, and treats any 2xx response as success (GA4 returns 204).
* Includes all 1.4.0 stability and security fixes.

= 1.4.0 =
* Added: HPOS (custom order tables) and Cart & Checkout Blocks compatibility declarations.
* Fixed: all order lookups (LTV, new-customer, recovery cron, dedup flags) now use the WooCommerce CRUD layer instead of raw wp_posts SQL, so they work on HPOS stores.
* Fixed: abandoned-cart recovery window now compares timestamps in UTC, so emails send correctly on non-UTC sites.
* Security: added CSRF nonce verification to the clear-logs, retry-queue and fetch-logs AJAX endpoints.
* Performance: customer lifetime-value lookups are cached, removing a database query on every tracked event.
* Changed: Meta Graph API upgraded to v21.0 and centralised in a single constant.
* Added: uninstall routine that removes all plugin options, tables, cron events and transients.

= 1.3.0 =
* Premium UI redesign and server-side first-party cookie generator.

= 1.2.0 =
* CAPI Batch API, non-blocking dispatch, and premium features.

= 1.1.0 =
* Dashboard redesign with tabbed UI, event toggles, and Match Quality validator.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 2.0.0 =
Major release: adds GA4, Google Ads, TikTok, Pinterest and Snapchat tracking, GDPR consent gating with Google Consent Mode v2, and a redesigned dashboard. Your existing Meta setup is unchanged.

= 1.4.0 =
Important compatibility and security update: adds HPOS support, fixes order tracking on modern WooCommerce stores, and hardens admin AJAX endpoints. Recommended for all users.
