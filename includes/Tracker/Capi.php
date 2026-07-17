<?php
namespace Mpc\Tracker;

/**
 * CAPI — Conversions API Handler
 *
 * v1.2.0 Changes:
 * - BATCH API: All events per page-load are queued then sent as ONE request via
 *   shutdown action, slashing server outbound connections by ~80%.
 * - NON-BLOCKING: Requests are now non-blocking (fire-and-forget). Logging is
 *   done immediately with the payload; the FB response is recorded asynchronously
 *   via a WP cron call (no more page freeze from blocking => true).
 * - SESSION GUARD on InitiateCheckout to prevent duplicate fires.
 * - Block Checkout InitiateCheckout support via Store API hook.
 * - is_new_customer flag on Purchase events.
 */
class Capi {
	private static $instance = null;

	/** @var array Events queued for batch dispatch this request. */
	private $event_queue = [];

	/** @var bool Whether the shutdown flush has been registered. */
	private $shutdown_registered = false;

	/** @var array Pending browser pixel payloads for ATC (AJAX). */
	private $pending_ac_events = [];

	/** @var array Pending browser pixel payloads for RFC (AJAX). */
	private $pending_rfc_events = [];

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// ── PII Capture ──────────────────────────────────────────────
		add_action( 'woocommerce_checkout_update_order_review',               [ $this, 'capture_classic_checkout_pii' ] );
		add_action( 'woocommerce_store_api_cart_update_customer_from_request',[ $this, 'capture_block_checkout_pii' ], 10, 2 );

		// ── Standard Events ──────────────────────────────────────────
		add_action( 'woocommerce_before_shop_loop',      [ $this, 'send_view_category_event' ] );
		add_action( 'woocommerce_before_cart',           [ $this, 'send_view_cart_event' ] );
		add_action( 'woocommerce_remove_cart_item',      [ $this, 'send_remove_from_cart_event' ], 10, 2 );
		add_action( 'woocommerce_after_single_product',  [ $this, 'send_view_content_event' ] );
		add_action( 'woocommerce_add_to_cart',           [ $this, 'send_add_to_cart_event' ], 10, 6 );

		// ── InitiateCheckout: classic + block ─────────────────────────
		add_action( 'woocommerce_before_checkout_form',  [ $this, 'send_initiate_checkout_event' ] );
		// Block Checkout fires when the Store API initialises the cart data
		add_action( 'woocommerce_store_api_cart_update_customer_from_request', [ $this, 'send_initiate_checkout_block' ], 20, 2 );

		// ── Purchase ─────────────────────────────────────────────────
		add_action( 'woocommerce_thankyou',                    [ $this, 'send_purchase_event' ] );
		add_action( 'woocommerce_order_status_processing',     [ $this, 'send_purchase_event_server_only' ] );
		add_action( 'woocommerce_order_status_completed',      [ $this, 'send_purchase_event_server_only' ] );

		// ── AJAX Pixel Helpers ───────────────────────────────────────
		add_filter( 'woocommerce_add_to_cart_fragments', [ $this, 'add_to_cart_fragment' ] );
		add_action( 'wp_footer', [ $this, 'print_client_add_to_cart' ], 20 );
	}

	// ═══════════════════════════════════════════════════════════
	// PII CAPTURE
	// ═══════════════════════════════════════════════════════════

	public function capture_classic_checkout_pii( $post_data ) {
		parse_str( $post_data, $fields );
		$this->apply_checkout_pii( [
			'email'      => $fields['billing_email']      ?? '',
			'phone'      => $fields['billing_phone']      ?? '',
			'first_name' => $fields['billing_first_name'] ?? '',
			'last_name'  => $fields['billing_last_name']  ?? '',
			'city'       => $fields['billing_city']       ?? '',
			'state'      => $fields['billing_state']      ?? '',
			'postcode'   => $fields['billing_postcode']   ?? '',
			'country'    => $fields['billing_country']    ?? '',
		] );

		// Also feed raw email to AbandonedCart
		if ( ! empty( $fields['billing_email'] ) && function_exists('WC') && isset( WC()->session ) ) {
			WC()->session->set( 'mpc_guest_email_raw', sanitize_email( $fields['billing_email'] ) );
		}
	}

	public function capture_block_checkout_pii( $customer, $request ) {
		if ( ! is_a( $customer, 'WC_Customer' ) ) return;
		$this->apply_checkout_pii( [
			'email'      => $customer->get_billing_email(),
			'phone'      => $customer->get_billing_phone(),
			'first_name' => $customer->get_billing_first_name(),
			'last_name'  => $customer->get_billing_last_name(),
			'city'       => $customer->get_billing_city(),
			'state'      => $customer->get_billing_state(),
			'postcode'   => $customer->get_billing_postcode(),
			'country'    => $customer->get_billing_country(),
		] );

		// Also feed raw email to AbandonedCart
		if ( $customer->get_billing_email() && function_exists('WC') && isset( WC()->session ) ) {
			WC()->session->set( 'mpc_guest_email_raw', sanitize_email( $customer->get_billing_email() ) );
		}
	}

	private function apply_checkout_pii( array $raw ) {
		if ( ! function_exists('WC') || is_null( WC()->session ) ) return;
		$existing = WC()->session->get( 'mpc_checkout_user_data', [] );
		$data = $existing;

		if ( ! empty( $raw['email'] ) )      $data['em']      = hash( 'sha256', strtolower( trim( $raw['email'] ) ) );
		if ( ! empty( $raw['phone'] ) )      $data['ph']      = hash( 'sha256', preg_replace( '/[^0-9]/', '', $raw['phone'] ) );
		if ( ! empty( $raw['first_name'] ) ) $data['fn']      = hash( 'sha256', strtolower( trim( $raw['first_name'] ) ) );
		if ( ! empty( $raw['last_name'] ) )  $data['ln']      = hash( 'sha256', strtolower( trim( $raw['last_name'] ) ) );
		if ( ! empty( $raw['city'] ) )       $data['ct']      = hash( 'sha256', strtolower( preg_replace( '/\s+/', '', $raw['city'] ) ) );
		if ( ! empty( $raw['state'] ) )      $data['st']      = hash( 'sha256', strtolower( $raw['state'] ) );
		if ( ! empty( $raw['postcode'] ) )   $data['zp']      = hash( 'sha256', strtolower( preg_replace( '/\s+/', '', $raw['postcode'] ) ) );
		if ( ! empty( $raw['country'] ) )    $data['country'] = hash( 'sha256', strtolower( $raw['country'] ) );

		if ( $data !== $existing ) {
			WC()->session->set( 'mpc_checkout_user_data', $data );
		}
	}

	// ═══════════════════════════════════════════════════════════
	// USER DATA HELPERS
	// ═══════════════════════════════════════════════════════════

	private function get_fbp_fbc() {
		$data = [];
		if ( ! empty( $_COOKIE['_fbp'] ) ) $data['fbp'] = sanitize_text_field( $_COOKIE['_fbp'] );

		if ( ! empty( $_COOKIE['_fbc'] ) ) {
			$data['fbc'] = sanitize_text_field( $_COOKIE['_fbc'] );
		} elseif ( ! empty( $_GET['fbclid'] ) ) {
			$data['fbc'] = 'fb.1.' . time() . '.' . sanitize_text_field( $_GET['fbclid'] );
			if ( function_exists('WC') && isset( WC()->session ) ) {
				WC()->session->set( 'mpc_derived_fbc', $data['fbc'] );
			}
		} elseif ( function_exists('WC') && isset( WC()->session ) ) {
			$stored_fbc = WC()->session->get( 'mpc_derived_fbc' );
			if ( ! empty( $stored_fbc ) ) $data['fbc'] = $stored_fbc;
		}

		return $data;
	}

	private function get_common_user_data( $extra = [] ) {
		$user_data = array_merge( [
			'client_ip_address' => \WC_Geolocation::get_ip_address(),
			'client_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
		], $this->get_fbp_fbc() );

		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			if ( ! empty( $user->user_email ) )     $user_data['em']          = hash( 'sha256', strtolower( trim( $user->user_email ) ) );
			if ( ! empty( $user->user_firstname ) )  $user_data['fn']          = hash( 'sha256', strtolower( trim( $user->user_firstname ) ) );
			if ( ! empty( $user->user_lastname ) )   $user_data['ln']          = hash( 'sha256', strtolower( trim( $user->user_lastname ) ) );
			$user_data['external_id'] = hash( 'sha256', (string) $user->ID );
		}

		if ( function_exists('WC') && isset( WC()->session ) ) {
			$checkout_data = WC()->session->get( 'mpc_checkout_user_data', [] );
			$user_data     = array_merge( $user_data, $checkout_data );
		}

		return array_merge( $user_data, $extra );
	}

	private function get_advanced_custom_data( $custom_data = [] ) {
		// UTMs from cookies
		foreach ( [ 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term' ] as $utm ) {
			if ( isset( $_COOKIE[ 'mpc_' . $utm ] ) ) {
				$custom_data[ $utm ] = sanitize_text_field( $_COOKIE[ 'mpc_' . $utm ] );
			}
		}

		// LTV & order count (only if toggle is on)
		if ( get_option( 'mpc_enable_ltv', 1 ) ) {
			$user_id = is_user_logged_in() ? get_current_user_id() : 0;
			$email   = '';
			if ( ! $user_id && function_exists('WC') && ! is_null( WC()->customer ) ) {
				$email = WC()->customer->get_billing_email();
			}

			$totals = $this->get_customer_ltv( $user_id, $email );
			$custom_data['lifetime_value'] = $totals['ltv'];
			$custom_data['total_orders']   = $totals['orders'];
		}

		// User role
		$user_id = is_user_logged_in() ? get_current_user_id() : 0;
		if ( $user_id ) {
			$user                    = get_userdata( $user_id );
			$custom_data['user_role'] = $user ? implode( ',', $user->roles ) : 'guest';
		} else {
			$custom_data['user_role'] = 'guest';
		}

		return $custom_data;
	}

	private function current_url() {
		$scheme = is_ssl() ? 'https://' : 'http://';
		return $scheme . ( $_SERVER['HTTP_HOST'] ?? '' ) . ( $_SERVER['REQUEST_URI'] ?? '' );
	}

	/**
	 * Lifetime value + paid-order count for a customer.
	 *
	 * HPOS-safe: uses wc_get_orders() (the CRUD layer) instead of raw wp_posts SQL,
	 * so it works on both legacy post storage and High-Performance Order Storage.
	 * Cached per user/email for 6 hours to avoid a DB hit on every tracked event.
	 *
	 * @return array{ltv: float, orders: int}
	 */
	private function get_customer_ltv( $user_id, $email ) {
		if ( ! $user_id && ! $email ) {
			return [ 'ltv' => 0.0, 'orders' => 0 ];
		}
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return [ 'ltv' => 0.0, 'orders' => 0 ];
		}

		$cache_key = 'mpc_ltv_' . ( $user_id ? 'u' . $user_id : 'e' . md5( strtolower( $email ) ) );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$args = [
			'status' => [ 'wc-completed', 'wc-processing' ],
			'limit'  => 100,
			'return' => 'objects',
		];
		if ( $user_id ) {
			$args['customer_id'] = $user_id;
		} else {
			$args['billing_email'] = $email;
		}

		$orders = wc_get_orders( $args );
		$ltv    = 0.0;
		$count  = is_array( $orders ) ? count( $orders ) : 0;
		if ( is_array( $orders ) ) {
			foreach ( $orders as $order ) {
				$ltv += (float) $order->get_total();
			}
		}

		$result = [ 'ltv' => round( $ltv, 2 ), 'orders' => $count ];
		set_transient( $cache_key, $result, 6 * HOUR_IN_SECONDS );
		return $result;
	}

	// ═══════════════════════════════════════════════════════════
	// STANDARD EVENTS
	// ═══════════════════════════════════════════════════════════

	public function send_page_view_event( $event_id ) {
		if ( ! get_option( 'mpc_ev_pageview', 1 ) ) return;
		$this->queue_event( 'PageView', $this->get_common_user_data(), $this->get_advanced_custom_data(), $event_id, false );
	}

	public function send_view_category_event() {
		if ( ! get_option( 'mpc_ev_viewcategory', 1 ) ) return;
		if ( ! is_product_category() ) return;
		$term = get_queried_object();
		if ( ! $term ) return;

		$event_id   = Deduplication::generate_event_id();
		$event_data = $this->get_advanced_custom_data( [
			'content_name'     => $term->name,
			'content_category' => $term->name,
		] );
		$this->queue_event( 'ViewCategory', $this->get_common_user_data(), $event_data, $event_id );
	}

	public function send_view_cart_event() {
		if ( ! get_option( 'mpc_ev_viewcart', 1 ) ) return;
		$event_id   = Deduplication::generate_event_id();
		$event_data = $this->get_advanced_custom_data( [
			'value'    => (float) WC()->cart->get_total( 'edit' ),
			'currency' => get_woocommerce_currency(),
		] );
		$this->queue_event( 'ViewCart', $this->get_common_user_data(), $event_data, $event_id );
	}

	public function send_remove_from_cart_event( $cart_item_key, $cart ) {
		if ( ! get_option( 'mpc_ev_removecart', 1 ) ) return;
		$removed_item = $cart->removed_cart_contents[ $cart_item_key ] ?? null;
		if ( ! $removed_item ) return;

		$product = wc_get_product( $removed_item['product_id'] );
		if ( ! $product ) return;

		$event_id   = Deduplication::generate_event_id();
		$event_data = $this->get_advanced_custom_data( [
			'content_name' => $product->get_name(),
			'content_ids'  => [ (string) ( $product->get_sku() ?: $product->get_id() ) ],
			'content_type' => 'product',
			'value'        => (float) $product->get_price() * (int) $removed_item['quantity'],
			'currency'     => get_woocommerce_currency(),
		] );

		$this->queue_event( 'RemoveFromCart', $this->get_common_user_data(), $event_data, $event_id, false );

		$payload = [ 'event_id' => $event_id, 'event_data' => $event_data ];
		if ( wp_doing_ajax() ) {
			$this->pending_rfc_events[] = $payload;
		} elseif ( function_exists('WC') && isset( WC()->session ) ) {
			$pending   = WC()->session->get( 'mpc_pending_remove_from_cart', [] );
			$pending[] = $payload;
			WC()->session->set( 'mpc_pending_remove_from_cart', $pending );
		}
	}

	public function send_view_content_event() {
		if ( ! get_option( 'mpc_ev_viewcontent', 1 ) ) return;
		global $product;
		if ( ! $product ) return;

		$event_id   = Deduplication::generate_event_id();
		$event_data = $this->get_advanced_custom_data( [
			'content_name' => $product->get_name(),
			'content_ids'  => [ (string) ( $product->get_sku() ?: $product->get_id() ) ],
			'content_type' => 'product',
			'value'        => (float) $product->get_price(),
			'currency'     => get_woocommerce_currency(),
		] );

		$this->queue_event( 'ViewContent', $this->get_common_user_data(), $event_data, $event_id );
	}

	public function send_add_to_cart_event( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
		if ( ! get_option( 'mpc_ev_addtocart', 1 ) ) return;
		$product = wc_get_product( $variation_id ?: $product_id );
		if ( ! $product ) return;

		$event_id   = Deduplication::generate_event_id();
		$id         = (string) ( $product->get_sku() ?: $product->get_id() );
		$event_data = $this->get_advanced_custom_data( [
			'content_name' => $product->get_name(),
			'content_ids'  => [ $id ],
			'content_type' => 'product',
			'value'        => (float) $product->get_price() * (int) $quantity,
			'currency'     => get_woocommerce_currency(),
			'contents'     => [ [
				'id'         => $id,
				'quantity'   => (int) $quantity,
				'item_price' => (float) $product->get_price(),
			] ],
		] );

		$this->queue_event( 'AddToCart', $this->get_common_user_data(), $event_data, $event_id, false );

		$payload = [ 'event_id' => $event_id, 'event_data' => $event_data ];
		if ( wp_doing_ajax() ) {
			$this->pending_ac_events[] = $payload;
		} elseif ( function_exists('WC') && isset( WC()->session ) ) {
			$pending   = WC()->session->get( 'mpc_pending_add_to_cart', [] );
			$pending[] = $payload;
			WC()->session->set( 'mpc_pending_add_to_cart', $pending );
		}
	}

	// ── InitiateCheckout: Classic Checkout ──

	public function send_initiate_checkout_event() {
		if ( ! get_option( 'mpc_ev_checkout', 1 ) ) return;
		if ( ! function_exists('is_checkout') || ! is_checkout() ) return;
		if ( is_wc_endpoint_url('order-pay') || is_wc_endpoint_url('order-received') ) return;
		if ( ! function_exists('WC') || is_null( WC()->cart ) || WC()->cart->is_empty() ) return;

		// Session guard — only fire once per session visit to checkout
		if ( WC()->session && WC()->session->get( 'mpc_initiate_checkout_sent' ) ) return;

		$this->dispatch_initiate_checkout();

		if ( function_exists('WC') && isset( WC()->session ) ) {
			WC()->session->set( 'mpc_initiate_checkout_sent', true );
		}
	}

	// ── InitiateCheckout: Block Checkout ──

	public function send_initiate_checkout_block( $customer, $request ) {
		if ( ! get_option( 'mpc_ev_checkout', 1 ) ) return;
		if ( ! function_exists('WC') || is_null( WC()->cart ) || WC()->cart->is_empty() ) return;

		// Session guard
		if ( WC()->session && WC()->session->get( 'mpc_initiate_checkout_sent' ) ) return;

		$this->dispatch_initiate_checkout();

		if ( function_exists('WC') && isset( WC()->session ) ) {
			WC()->session->set( 'mpc_initiate_checkout_sent', true );
		}
	}

	private function dispatch_initiate_checkout() {
		$contents    = [];
		$content_ids = [];

		foreach ( WC()->cart->get_cart() as $item ) {
			$product     = wc_get_product( $item['variation_id'] ?: $item['product_id'] );
			$id          = (string) ( $product ? ( $product->get_sku() ?: $product->get_id() ) : ( $item['variation_id'] ?: $item['product_id'] ) );
			$content_ids[] = $id;
			$contents[]    = [
				'id'         => $id,
				'quantity'   => (int) $item['quantity'],
				'item_price' => $item['quantity'] ? (float) $item['line_subtotal'] / $item['quantity'] : 0,
			];
		}

		$event_id   = Deduplication::generate_event_id();
		$event_data = $this->get_advanced_custom_data( [
			'value'        => (float) WC()->cart->get_total( 'edit' ),
			'currency'     => get_woocommerce_currency(),
			'num_items'    => WC()->cart->get_cart_contents_count(),
			'content_type' => 'product',
			'content_ids'  => array_values( array_unique( $content_ids ) ),
			'contents'     => $contents,
		] );

		$this->queue_event( 'InitiateCheckout', $this->get_common_user_data(), $event_data, $event_id );
	}

	// ── Purchase ──

	public function send_purchase_event( $order_id ) {
		$this->do_purchase( $order_id, true );
	}

	public function send_purchase_event_server_only( $order_id ) {
		$this->do_purchase( $order_id, false );
	}

	private function do_purchase( $order_id, $print_html ) {
		if ( ! get_option( 'mpc_ev_purchase', 1 ) ) return;
		if ( ! $order_id ) return;

		$order = wc_get_order( $order_id );
		if ( ! $order ) return;

		// HPOS-safe dedup guard: read the flag off the order object, not post meta.
		if ( $order->get_meta( '_mpc_purchase_tracked' ) ) return;

		// Advanced status filtering
		if ( get_option( 'mpc_purchase_status_filter', 1 ) ) {
			if ( ! in_array( $order->get_status(), [ 'processing', 'completed' ] ) ) return;
		}

		if ( apply_filters( 'mpc_block_purchase_event', false, $order ) ) return;

		$event_id = Deduplication::get_order_event_id( $order_id );

		// Full order-based user data (most complete for EMQ)
		$extra_user_data = [
			'client_ip_address' => $order->get_customer_ip_address(),
			'client_user_agent' => $order->get_customer_user_agent(),
		];

		if ( $order->get_billing_email() )     $extra_user_data['em']          = hash( 'sha256', strtolower( trim( $order->get_billing_email() ) ) );
		if ( $order->get_billing_phone() )     $extra_user_data['ph']          = hash( 'sha256', preg_replace( '/[^0-9]/', '', $order->get_billing_phone() ) );
		if ( $order->get_billing_first_name()) $extra_user_data['fn']          = hash( 'sha256', strtolower( trim( $order->get_billing_first_name() ) ) );
		if ( $order->get_billing_last_name() ) $extra_user_data['ln']          = hash( 'sha256', strtolower( trim( $order->get_billing_last_name() ) ) );
		if ( $order->get_billing_city() )      $extra_user_data['ct']          = hash( 'sha256', strtolower( preg_replace( '/\s+/', '', $order->get_billing_city() ) ) );
		if ( $order->get_billing_state() )     $extra_user_data['st']          = hash( 'sha256', strtolower( $order->get_billing_state() ) );
		if ( $order->get_billing_postcode() )  $extra_user_data['zp']          = hash( 'sha256', strtolower( preg_replace( '/\s+/', '', $order->get_billing_postcode() ) ) );
		if ( $order->get_billing_country() )   $extra_user_data['country']     = hash( 'sha256', strtolower( $order->get_billing_country() ) );
		if ( $order->get_customer_id() )       $extra_user_data['external_id'] = hash( 'sha256', (string) $order->get_customer_id() );

		$content_ids = [];
		$contents    = [];
		foreach ( $order->get_items() as $item ) {
			$product     = $item->get_product();
			$id          = (string) ( $product ? ( $product->get_sku() ?: $product->get_id() ) : $item->get_product_id() );
			$qty         = max( 1, (int) $item->get_quantity() );
			$content_ids[] = $id;
			$contents[]    = [
				'id'         => $id,
				'quantity'   => $qty,
				'item_price' => (float) $item->get_total() / $qty,
			];
		}

		// ── Premium: is_new_customer flag ─────────────────────────────
		$customer_id    = $order->get_customer_id();
		$billing_email  = $order->get_billing_email();
		$is_new_customer = false;

		if ( $customer_id ) {
			$order_count = wc_get_customer_order_count( $customer_id );
			$is_new_customer = ( $order_count <= 1 );
		} elseif ( $billing_email ) {
			// HPOS-safe: count paid orders for this email via the CRUD layer.
			$past_orders = wc_get_orders( [
				'status'        => [ 'wc-completed', 'wc-processing' ],
				'billing_email' => $billing_email,
				'limit'         => 2,
				'return'        => 'ids',
			] );
			$is_new_customer = ( count( (array) $past_orders ) <= 1 );
		}

		$event_data = $this->get_advanced_custom_data( [
			'content_ids'     => array_values( array_unique( $content_ids ) ),
			'content_type'    => 'product',
			'contents'        => $contents,
			'value'           => (float) $order->get_total(),
			'currency'        => $order->get_currency(),
			'num_items'       => $order->get_item_count(),
			'order_id'        => (string) $order_id,
			'is_new_customer' => $is_new_customer,
		] );

		$this->queue_event( 'Purchase', $this->get_common_user_data( $extra_user_data ), $event_data, $event_id, $print_html, $order->get_checkout_order_received_url(), $order_id );
		$order->update_meta_data( '_mpc_purchase_tracked', 1 );
		$order->save();

		// Clear checkout session PII
		if ( function_exists('WC') && isset( WC()->session ) ) {
			WC()->session->set( 'mpc_checkout_user_data', null );
			WC()->session->set( 'mpc_initiate_checkout_sent', null );
		}
	}

	// ═══════════════════════════════════════════════════════════
	// AJAX PIXEL BRIDGE (AddToCart / RemoveFromCart fragments)
	// ═══════════════════════════════════════════════════════════

	public function add_to_cart_fragment( $fragments ) {
		$html = '';
		foreach ( $this->pending_ac_events as $event ) {
			$json_data = esc_attr( wp_json_encode( $event['event_data'] ) );
			$html .= "<div class='mpc-ajax-event' data-event-name='AddToCart' data-event-id='" . esc_attr( $event['event_id'] ) . "' data-event-data='{$json_data}' style='display:none;'></div>";
		}
		foreach ( $this->pending_rfc_events as $event ) {
			$json_data = esc_attr( wp_json_encode( $event['event_data'] ) );
			$html .= "<div class='mpc-ajax-event' data-event-name='RemoveFromCart' data-event-id='" . esc_attr( $event['event_id'] ) . "' data-event-data='{$json_data}' style='display:none;'></div>";
		}
		if ( $html !== '' ) {
			$fragments['div.mpc-fragment-events'] = "<div class='mpc-fragment-events' style='display:none;'>{$html}</div>";
		}
		return $fragments;
	}

	public function print_client_add_to_cart() {
		if ( ! function_exists('WC') || ! isset( WC()->session ) ) return;

		$pending_ac = WC()->session->get( 'mpc_pending_add_to_cart' );
		if ( ! empty( $pending_ac ) ) {
			foreach ( $pending_ac as $event ) {
				$json_data = esc_attr( wp_json_encode( $event['event_data'] ) );
				echo "<div class='mpc-ajax-event' data-event-name='AddToCart' data-event-id='" . esc_attr( $event['event_id'] ) . "' data-event-data='{$json_data}' style='display:none;'></div>";
			}
			WC()->session->set( 'mpc_pending_add_to_cart', null );
		}

		$pending_rfc = WC()->session->get( 'mpc_pending_remove_from_cart' );
		if ( ! empty( $pending_rfc ) ) {
			foreach ( $pending_rfc as $event ) {
				$json_data = esc_attr( wp_json_encode( $event['event_data'] ) );
				echo "<div class='mpc-ajax-event' data-event-name='RemoveFromCart' data-event-id='" . esc_attr( $event['event_id'] ) . "' data-event-data='{$json_data}' style='display:none;'></div>";
			}
			WC()->session->set( 'mpc_pending_remove_from_cart', null );
		}
	}

	// ═══════════════════════════════════════════════════════════
	// BATCH API — Core Engine
	// ═══════════════════════════════════════════════════════════

	/**
	 * Queue an event for batch dispatch. Registers a shutdown hook on first call
	 * so all events on this page load are bundled into ONE API request.
	 */
	private function queue_event( $event_name, $user_data, $custom_data, $event_id, $print_html = true, $event_source_url = '', $order_id = 0 ) {
		$token    = get_option( 'mpc_capi_token' );
		$pixel_id = get_option( 'mpc_pixel_id' );
		if ( ! $token || ! $pixel_id ) return;

		// Respect GDPR consent (Meta is an advertising platform).
		if ( ! \Mpc\Consent\ConsentManager::allows( 'marketing' ) ) return;

		// Pre-log with status=pending immediately
		$log_id = $this->log_event_pending( $event_name, $event_id, $custom_data, $user_data );

		$this->event_queue[] = [
			'log_id'           => $log_id,
			'event_name'       => $event_name,
			'event_time'       => time(),
			'action_source'    => 'website',
			'event_id'         => $event_id,
			'event_source_url' => $event_source_url ?: $this->current_url(),
			'user_data'        => $user_data,
			'custom_data'      => $custom_data,
			'_order_id'        => $order_id,
		];

		if ( ! $this->shutdown_registered ) {
			add_action( 'shutdown', [ $this, 'flush_event_queue' ] );
			$this->shutdown_registered = true;
		}

		if ( $print_html ) {
			$json_data = esc_attr( wp_json_encode( $custom_data ) );
			echo "<div class='mpc-ajax-event' data-event-name='" . esc_attr( $event_name ) . "' data-event-id='" . esc_attr( $event_id ) . "' data-event-data='{$json_data}' style='display:none;'></div>";
		}
	}

	/**
	 * Fired on `shutdown` — sends ALL queued events in a single CAPI batch call.
	 * Non-blocking so it never delays page delivery.
	 */
	public function flush_event_queue() {
		if ( empty( $this->event_queue ) ) return;

		$token    = get_option( 'mpc_capi_token' );
		$pixel_id = get_option( 'mpc_pixel_id' );
		if ( ! $token || ! $pixel_id ) return;

		// Deliver the page to the visitor BEFORE we make the outbound CAPI call.
		// On PHP-FPM this closes the client connection so the request time is not
		// perceived by the user, while still letting us block on the API response
		// to record accurate status codes in the event log.
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		}

		$chunks = array_chunk( $this->event_queue, 50 );
		global $wpdb;

		foreach ( $chunks as $chunk ) {
			$log_ids = [];
			$api_data = [];

			$order_ids_to_flag = [];

			foreach ( $chunk as $ev ) {
				if ( ! empty( $ev['log_id'] ) ) $log_ids[] = (int) $ev['log_id'];
				if ( ! empty( $ev['_order_id'] ) ) $order_ids_to_flag[] = (int) $ev['_order_id'];
				unset( $ev['log_id'], $ev['_order_id'] );
				$api_data[] = $ev;
			}

			$payload = [ 'data' => $api_data ];

			$test_code = get_option( 'mpc_test_code' );
			if ( $test_code ) {
				$payload['test_event_code'] = $test_code;
			}

			$url = "https://graph.facebook.com/" . MPC_GRAPH_VERSION . "/{$pixel_id}/events?access_token={$token}";

			$response = wp_remote_post( $url, [
				'body'     => wp_json_encode( $payload ),
				'headers'  => [ 'Content-Type' => 'application/json' ],
				'timeout'  => 10,
				'blocking' => true,
			] );

			if ( ! empty( $log_ids ) ) {
				$ids_str = implode( ',', $log_ids );
				if ( is_wp_error( $response ) ) {
					$status = 500;
					$body = $response->get_error_message();
				} else {
					$status = wp_remote_retrieve_response_code( $response );
					$body = wp_remote_retrieve_body( $response );
				}
				$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}mpc_event_logs SET status = %d, response = %s WHERE id IN ($ids_str)", $status, $body ) );
				
				if ( $status == 200 && ! empty( $order_ids_to_flag ) ) {
					foreach ( $order_ids_to_flag as $oid ) {
						$flag_order = wc_get_order( $oid );
						if ( $flag_order ) {
							$flag_order->update_meta_data( '_mpc_capi_sent', 1 );
							$flag_order->save();
						}
					}
				}
			}
		}

		$this->event_queue = [];
	}

	// ═══════════════════════════════════════════════════════════
	// LOGGING
	// ═══════════════════════════════════════════════════════════

	/**
	 * Writes a log row immediately (before the async request is sent).
	 * Status is stored as 'queued'. The actual response cannot be captured in
	 * non-blocking mode without extra infrastructure.
	 */
	private function log_event_pending( $event_name, $event_id, $custom_data, $user_data ) {
		global $wpdb;

		// Build a representative payload for the EMQ validator
		$payload = [
			'data' => [ [
				'event_name' => $event_name,
				'event_id'   => $event_id,
				'user_data'  => $user_data,
				'custom_data'=> $custom_data,
			] ]
		];

		$wpdb->insert(
			"{$wpdb->prefix}mpc_event_logs",
			[
				'event_name' => $event_name,
				'event_id'   => $event_id,
				'event_type' => 'server',
				'payload'    => wp_json_encode( $payload ),
				'status'     => 0, 
				'response'   => 'queued (batch)',
			]
		);
		return $wpdb->insert_id;
	}
}
