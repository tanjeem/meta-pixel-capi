=== Meta Pixel & CAPI Ultimate for WooCommerce ===
Contributors: tanjeem
Tags: facebook pixel, conversions api, woocommerce, meta pixel, capi
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 2.2.7
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

= 2.2.7 =
* Added: **Duplicate Source Scan** on the Event Logs tab. If Meta reports more conversions than this plugin actually sent, the extra events come from somewhere else — this finds it. Checks active plugins known to send Meta events (flagging any configured with the same Pixel ID), fetches your real site HTML to catch extra `fbq('init')` calls from a theme, page builder or hard-coded snippet, and detects Google Tag Manager containers that can fire Meta tags without appearing in the plugin list. Results are included in the diagnostic export.
* Added: event log pruning. The log previously had no retention at all and grew without limit — real installs reached hundreds of thousands of rows. A nightly pass now deletes ordinary events past a configurable window (default 30 days, set it to 0 to keep everything) in safe batches. Purchase events are exempt and kept for a year so the Purchase Send Audit keeps working.
* Fixed: the diagnostic export counted only `processing` and `completed` orders, reporting zero orders on days that plainly had them — stores with a COD or courier workflow keep orders in custom statuses. It now reports all statuses, paid-only, and a per-status breakdown.

= 2.2.6 =
* Added: a **Download diagnostic export** button beside the Purchase Send Audit. Produces a JSON file putting real WooCommerce orders per day next to Purchase events actually sent per day, plus per-order send counts and statuses, claim rows, cron state and the environment (including both the site timezone and the database clock, which is what the event log's timestamps actually use). Contains no customer data and no access token — credentials appear only as booleans — so it is safe to share when asking for help.

= 2.2.5 =
* Fixed: `do_purchase()` queued the Purchase and only afterwards wrote the `_mpc_purchase_tracked` marker via `$order->save()`. On the order-status hooks that save runs inside `WC_Order::status_transition()`, which WooCommerce wraps in a try/catch that only logs — so a failing save silently lost the marker while the event had already been queued and still sent. The thank-you page, the `completed` transition and the nightly recovery pass then each saw an untracked order and sent it again. The claim row taken in 2.2.4 already closed this; the marker write is now also wrapped so it can never abort the rest of the status transition and break other plugins listening on it.
* Fixed: recovery re-sends are now claimed atomically. WordPress serialises cron with a 60-second transient lock, so a slow recovery pass could be joined by a second concurrent one that had already read the same state and re-sent the same orders. The retry slot is now taken by a conditional UPDATE that only one caller can win.

= 2.2.4 =
* Fixed: the browser pixel never fired Purchase, so Meta showed the event as "Conversions API" only with no pixel/CAPI redundancy. Payment gateways move an order to processing during the checkout request, so the order-status hook always ran before the thank-you page and set the shared dedup flag — making `woocommerce_thankyou` skip rendering the browser event. The browser copy now has its own once-per-order guard and shares the `order_<id>` event ID with the server send, so Meta deduplicates the pair and still counts the purchase exactly once.
* Fixed: duplicate Purchase conversions are now prevented by the database rather than by a flag. A new `mpc_purchase_sent` table keys on the order id, and a send is only made by the caller that wins an atomic INSERT IGNORE against it. No hook path, repeated page load, status transition or concurrent request can produce a second Purchase for the same order. The order-meta flag and event-log row remain as cheap fast paths ahead of the claim.
* Fixed: Automatic Conversion Recovery is now bounded to 3 attempts per order and reads delivery state from the claim table, so an order whose send keeps failing is abandoned instead of being re-sent every night.
* Changed: Automatic Conversion Recovery re-sends via an explicit `resend_purchase_event()` instead of deleting `_mpc_purchase_tracked`, which previously stripped the marker from orders whose send had actually succeeded.
* Added: an `event_lookup (event_name, event_id)` index on the event log, which the new guard queries once per purchase.

= 2.2.3 =
* Improved: Automatic Conversion Recovery now decides whether an order still needs sending from the event log's real HTTP status (recorded via a reliable direct query) instead of the shutdown-written order flag. It recovers genuinely failed sends while never re-sending a purchase Meta already received — the full safety net without the duplicate conversions.

= 2.2.2 =
* Fixed: duplicate Purchase conversions. The Automatic Conversion Recovery cron relied on a flag (_mpc_capi_sent) written during shutdown, which can silently fail to persist even when the event sent successfully — causing the cron to re-send purchases every night and inflate reported conversions. The cron now trusts the reliable, request-time _mpc_purchase_tracked marker and only recovers orders that were never tracked at all. This resolves Meta reporting more purchases than the store actually received.

= 2.2.1 =
* Fixed: Purchase event_time (Meta "creationTime" diagnostic). Purchase events now use the order's actual creation time instead of the moment the event was sent, so server-side sends from order-status hooks and the recovery cron carry the correct timestamp and stay consistent across browser and server. event_time is clamped to Meta's valid range (not future, within 7 days).

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
