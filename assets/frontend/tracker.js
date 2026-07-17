/**
 * MPC multi-platform browser relay.
 *
 * Reads the JSON emitted by Events\Collector, loads each enabled platform's
 * client library, and re-fires every queued canonical event as that platform's
 * own event. Adding a platform = adding one entry to `registry` here plus a PHP
 * channel that returns a browser_config().
 */
(function () {
	'use strict';

	var node = document.getElementById('mpc-bus-data');
	if (!node) { return; }

	var cfg;
	try { cfg = JSON.parse(node.textContent); } catch (e) { return; }

	var adapters = cfg.adapters || {};
	var events = cfg.events || [];

	// ── Shared gtag loader (GA4 + Google Ads share one gtag.js) ──
	var gtagLoaded = false;
	function ensureGtag(id) {
		window.dataLayer = window.dataLayer || [];
		if (!window.gtag) {
			window.gtag = function () { window.dataLayer.push(arguments); };
			window.gtag('js', new Date());
		}
		if (!gtagLoaded && id) {
			var s = document.createElement('script');
			s.async = true;
			s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
			document.head.appendChild(s);
			gtagLoaded = true;
		}
	}

	function ga4Items(data) {
		return (data.contents || []).map(function (c) {
			return { item_id: c.id, item_name: c.name, quantity: c.quantity, price: c.item_price };
		});
	}

	var GA4_MAP = {
		PageView: 'page_view', ViewContent: 'view_item', ViewCategory: 'view_item_list',
		ViewCart: 'view_cart', AddToCart: 'add_to_cart', RemoveFromCart: 'remove_from_cart',
		InitiateCheckout: 'begin_checkout', Purchase: 'purchase'
	};

	var registry = {
		ga4: {
			init: function (c) {
				ensureGtag(c.measurement_id);
				window.gtag('config', c.measurement_id, { send_page_view: false });
			},
			track: function (name, data, eventId, c) {
				var gname = GA4_MAP[name];
				if (!gname) { return; }
				var params = { send_to: c.measurement_id };
				if (data.currency) { params.currency = data.currency; }
				if (typeof data.value !== 'undefined') { params.value = data.value; }
				var items = ga4Items(data);
				if (items.length) { params.items = items; }
				if (name === 'Purchase' && data.order_id) { params.transaction_id = data.order_id; }
				window.gtag('event', gname, params);
			}
		},

		google_ads: {
			init: function (c) {
				ensureGtag(c.conversion_id);
				window.gtag('config', c.conversion_id);
			},
			track: function (name, data, eventId, c) {
				if (name !== 'Purchase') { return; }
				var params = {
					value: data.value,
					currency: data.currency,
					transaction_id: data.order_id
				};
				if (c.purchase_label) {
					params.send_to = c.conversion_id + '/' + c.purchase_label;
				}
				window.gtag('event', 'conversion', params);
			}
		},

		tiktok: {
			init: function (c) {
				if (window.ttq) { return; }
				(function (w, d, t) {
					w.TiktokAnalyticsObject = t;
					var ttq = w[t] = w[t] || [];
					ttq.methods = ['page', 'track', 'identify', 'instances', 'debug', 'on', 'off', 'once', 'ready', 'alias', 'group', 'enableCookie', 'disableCookie', 'holdConsent', 'revokeConsent', 'grantConsent'];
					ttq.setAndDefer = function (a, b) { a[b] = function () { a.push([b].concat(Array.prototype.slice.call(arguments, 0))); }; };
					for (var i = 0; i < ttq.methods.length; i++) { ttq.setAndDefer(ttq, ttq.methods[i]); }
					ttq.load = function (e) {
						var r = 'https://analytics.tiktok.com/i18n/pixel/events.js';
						ttq._i = ttq._i || {}; ttq._i[e] = []; ttq._i[e]._u = r;
						ttq._t = ttq._t || {}; ttq._t[e] = +new Date();
						var s = d.createElement('script'); s.type = 'text/javascript'; s.async = true;
						s.src = r + '?sdkid=' + e + '&lib=' + t;
						var f = d.getElementsByTagName('script')[0]; f.parentNode.insertBefore(s, f);
					};
					ttq.load(c.pixel_code);
					ttq.page();
				})(window, document, 'ttq');
			},
			track: function (name, data, eventId) {
				var map = { ViewContent: 'ViewContent', AddToCart: 'AddToCart', InitiateCheckout: 'InitiateCheckout', Purchase: 'CompletePayment' };
				var tname = map[name];
				if (!tname || !window.ttq) { return; }
				var contents = (data.contents || []).map(function (x) {
					return { content_id: String(x.id), content_type: 'product', content_name: x.name, price: x.item_price, quantity: x.quantity };
				});
				window.ttq.track(tname, { contents: contents, value: data.value, currency: data.currency }, { event_id: eventId });
			}
		},

		pinterest: {
			init: function (c) {
				if (!window.pintrk) {
					(function (e) {
						window.pintrk = function () { window.pintrk.queue.push(Array.prototype.slice.call(arguments)); };
						var n = window.pintrk; n.queue = []; n.version = '3.0';
						var t = document.createElement('script'); t.async = true; t.src = e;
						var r = document.getElementsByTagName('script')[0]; r.parentNode.insertBefore(t, r);
					})('https://s.pinimg.com/ct/core.js');
				}
				window.pintrk('load', c.tag_id);
				window.pintrk('page');
			},
			track: function (name, data) {
				var map = { AddToCart: 'addtocart', Purchase: 'checkout', ViewCategory: 'viewcategory' };
				var pname = map[name];
				if (!pname || !window.pintrk) { return; }
				var line = (data.contents || []).map(function (x) {
					return { product_id: String(x.id), product_price: x.item_price, product_quantity: x.quantity, product_name: x.name };
				});
				var props = { value: data.value, currency: data.currency, order_quantity: data.num_items, line_items: line };
				if (name === 'Purchase' && data.order_id) { props.order_id = String(data.order_id); }
				window.pintrk('track', pname, props);
			}
		},

		snapchat: {
			init: function (c) {
				if (!window.snaptr) {
					(function (e, t, n) {
						if (e.snaptr) { return; }
						var a = e.snaptr = function () { a.handleRequest ? a.handleRequest.apply(a, arguments) : a.queue.push(arguments); };
						a.queue = [];
						var r = t.createElement('script'); r.async = true; r.src = n;
						var u = t.getElementsByTagName('script')[0]; u.parentNode.insertBefore(r, u);
					})(window, document, 'https://sc-static.net/scevent.min.js');
				}
				window.snaptr('init', c.pixel_id);
				window.snaptr('track', 'PAGE_VIEW');
			},
			track: function (name, data) {
				var map = { ViewContent: 'VIEW_CONTENT', AddToCart: 'ADD_CART', InitiateCheckout: 'START_CHECKOUT', Purchase: 'PURCHASE' };
				var sname = map[name];
				if (!sname || !window.snaptr) { return; }
				var ids = (data.contents || []).map(function (x) { return String(x.id); });
				var props = { currency: data.currency, price: data.value, item_ids: ids, number_items: data.num_items };
				if (name === 'Purchase' && data.order_id) { props.transaction_id = String(data.order_id); }
				window.snaptr('track', sname, props);
			}
		}
	};

	// GA4 is analytics; the ad platforms need marketing consent.
	var CATEGORY = { ga4: 'statistics', google_ads: 'marketing', tiktok: 'marketing', pinterest: 'marketing', snapchat: 'marketing' };

	function runAdapter(id) {
		var a = registry[id], c = adapters[id];
		if (!a) { return; }
		try { if (a.init) { a.init(c); } } catch (e) { /* isolate */ }
		events.forEach(function (ev) {
			if (a.track) {
				try { a.track(ev.name, ev.data || {}, ev.event_id, c); } catch (e) { /* isolate */ }
			}
		});
	}

	// Gate each platform on consent when a consent manager is present; otherwise
	// fire immediately (consent not required / not configured).
	Object.keys(adapters).forEach(function (id) {
		if (!registry[id]) { return; }
		var cat = CATEGORY[id] || 'marketing';
		if (window.mpcConsent && window.mpcConsent.cfg && window.mpcConsent.cfg.required) {
			window.mpcConsent.onGrant(cat, function () { runAdapter(id); });
		} else {
			runAdapter(id);
		}
	});
})();
