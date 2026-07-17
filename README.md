# Meta Pixel & CAPI Ultimate for WooCommerce

Multi-platform, server-side-first conversion tracking for WooCommerce. Fire every
key shopping event in the browser **and** on the server, deduplicated by a shared
event id, across six ad platforms — with GDPR consent gating built in.

> **Version 2.0.0** · Requires WordPress 5.8+, WooCommerce, PHP 7.4+ · HPOS compatible

---

## Why it exists

iOS restrictions, ad blockers, and browser tracking-prevention (Safari ITP) quietly
destroy browser-only pixel data. This plugin pairs each browser pixel with a
server-side API call sharing one `event_id`, so the ad platform deduplicates them
and you keep the coverage of server-side tracking without double-counting.

## Platforms

| Platform | Browser | Server-side |
|---|---|---|
| Meta (Facebook/Instagram) | Meta Pixel | Conversions API (batched, non-blocking) |
| Google Analytics 4 | gtag | Measurement Protocol |
| Google Ads | gtag conversion | — (Enhanced Conversions via Google's auto-collection) |
| TikTok | TikTok Pixel | Events API v1.3 |
| Pinterest | Pinterest Tag | Conversions API v5 |
| Snapchat | Snap Pixel | Conversions API v3 |

Each platform is toggled independently from **Integrations**; server-side tracking
activates when you add that platform's API token.

## Key features

- Shared **event bus** — one WooCommerce listener feeds every platform.
- Strict browser/server **deduplication** via a shared event id.
- **GDPR consent gating** — WP Consent API (CookieYes, Complianz, Borlabs, Cookiebot)
  or a custom cookie rule, plus **Google Consent Mode v2** signals. Gates all six
  platforms, browser and server.
- **High Match Quality** — hashed customer PII, click ids (`fbp/fbc`, `_ga`, `gclid`,
  `ttp/ttclid`, `epik`, `scid`), IP, user agent, external id.
- **Automatic Conversion Recovery** — nightly re-send of any Purchase that never
  reached Meta.
- Facebook **catalog feed**, abandoned-cart emails, fake-order protection, blocklist.
- Redesigned dashboard: tracking-health score, setup checklist, live platform status,
  per-platform event log.
- **HPOS** (custom order tables) and **Cart & Checkout Blocks** compatible.

## Architecture

```
Shopper action
  └─ Events\Collector          (hooks WooCommerce once, builds a normalized Event)
       ├─ server ─ Events\EventBus.dispatch()  ─ consent-gated ─ Channels\*
       └─ browser ─ assets/frontend/tracker.js ─ consent-gated ─ platform pixels
```

- `includes/Events/` — `Event`, `EventBus`, `Collector`, `WooData`.
- `includes/Channels/` — one class per platform (`ChannelInterface` + `AbstractChannel`).
- `includes/Consent/ConsentManager.php` — consent detection + gating + Consent Mode v2.
- `includes/Tracker/` — the original Meta Pixel + CAPI path (kept dedicated).

**Adding a platform** = one channel class (`browser_config()` + `handle()`) + one
adapter entry in `tracker.js`. No WooCommerce hook code required. Third-party add-ons
can register channels via the `mpc_register_channels` action.

## Installation

1. Copy the plugin folder into `wp-content/plugins/` (or upload the built zip).
2. Activate it. WooCommerce must be active.
3. Open **Pixel & CAPI** → enter your Meta Pixel ID + CAPI token, then add any other
   platforms under **Integrations**.
4. If you serve the EU, configure **Consent & Privacy**.

## Building a release

```bash
./build.sh          # produces dist/meta-pixel-capi.zip (dev files excluded)
```

## Testing / QA checklist

This plugin talks to six external APIs; validate on a staging store before selling:

- **Meta** → Events Manager → Test Events (use the Test Event Code field)
- **GA4** → DebugView · **Google Ads** → Tag Assistant
- **TikTok** → Events Manager → Test Events
- **Pinterest** → Conversions → event history
- **Snapchat** → Events Manager → event diagnostics
- Toggle consent on and confirm nothing fires until consent is granted.

## Selling it — licensing & updates

This repo ships under **GPLv2+** (WordPress requires GPL-compatible licensing; you
can still sell GPL software). For a commercial product you need payments, license-key
activation, and update delivery. Rather than reinvent that, integrate a proven layer:

- **[Freemius](https://freemius.com/)** — payments + licensing + updates + a freemium
  funnel, purpose-built for WordPress plugins. Fastest path to selling.
- **Easy Digital Downloads + Software Licensing** — self-hosted licensing/updates.
- **Lemon Squeezy / Paddle** — merchant-of-record checkout; pair with a license check.

The auto-updater currently uses [plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker)
pointed at GitHub releases — fine for direct customers, or swap it for your licensing
provider's update endpoint.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
