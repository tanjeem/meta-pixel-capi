<?php
namespace Mpc\Events;

/**
 * The single WooCommerce listener for the multi-platform event bus.
 *
 * Hooks each shopper action ONCE, builds a normalized Event, sends it to the
 * server-side channels via the EventBus, and queues a browser payload for the
 * frontend relay (tracker.js) to fire each platform's client-side pixel.
 *
 * The Meta path (Tracker\Capi) still runs on its own for backward-compatibility;
 * this collector drives the newer channels registered on the bus.
 *
 * @package Mpc\Events
 */
class Collector {
	private static $instance = null;

	/** Browser payloads queued for this page load. */
	private $browser_queue = [];

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function boot() {
		return self::get_instance();
	}

	private function __construct() {
		// Register on `init` (not `wp`) so hooks are present on AJAX add-to-cart
		// requests too, where the main query — and the `wp` action — never runs.
		add_action( 'init', [ $this, 'maybe_register_hooks' ], 20 );
	}

	public function maybe_register_hooks() {
		$bus = EventBus::get_instance();
		$has_enabled = false;
		foreach ( $bus->get_channels() as $channel ) {
			if ( $channel->is_enabled() ) {
				$has_enabled = true;
				break;
			}
		}
		if ( ! $has_enabled ) {
			return;
		}

		// Raw checkout PII capture (for server-side match keys).
		add_action( 'woocommerce_checkout_update_order_review', [ $this, 'capture_classic_pii' ] );
		add_action( 'woocommerce_store_api_cart_update_customer_from_request', [ $this, 'capture_block_pii' ], 10, 2 );

		// Standard events.
		add_action( 'wp_footer', [ $this, 'emit_page_view' ], 8 );
		add_action( 'woocommerce_after_single_product', [ $this, 'emit_view_content' ] );
		add_action( 'woocommerce_before_shop_loop', [ $this, 'emit_view_category' ] );
		add_action( 'woocommerce_before_cart', [ $this, 'emit_view_cart' ] );
		add_action( 'woocommerce_add_to_cart', [ $this, 'emit_add_to_cart' ], 20, 6 );
		add_action( 'woocommerce_before_checkout_form', [ $this, 'emit_initiate_checkout' ] );
		add_action( 'woocommerce_thankyou', [ $this, 'emit_purchase' ] );

		// Browser relay output.
		add_action( 'wp_footer', [ $this, 'print_browser_relay' ], 30 );
	}

	// ── PII capture ──────────────────────────────────────────────

	public function capture_classic_pii( $post_data ) {
		parse_str( $post_data, $fields );
		$this->store_raw_pii( [
			'email'      => $fields['billing_email']      ?? '',
			'phone'      => $fields['billing_phone']      ?? '',
			'first_name' => $fields['billing_first_name'] ?? '',
			'last_name'  => $fields['billing_last_name']  ?? '',
			'city'       => $fields['billing_city']       ?? '',
			'state'      => $fields['billing_state']      ?? '',
			'zip'        => $fields['billing_postcode']   ?? '',
			'country'    => $fields['billing_country']    ?? '',
		] );
	}

	public function capture_block_pii( $customer, $request ) {
		if ( ! is_a( $customer, 'WC_Customer' ) ) {
			return;
		}
		$this->store_raw_pii( [
			'email'      => $customer->get_billing_email(),
			'phone'      => $customer->get_billing_phone(),
			'first_name' => $customer->get_billing_first_name(),
			'last_name'  => $customer->get_billing_last_name(),
			'city'       => $customer->get_billing_city(),
			'state'      => $customer->get_billing_state(),
			'zip'        => $customer->get_billing_postcode(),
			'country'    => $customer->get_billing_country(),
		] );
	}

	private function store_raw_pii( array $raw ) {
		if ( ! function_exists( 'WC' ) || is_null( WC()->session ) ) {
			return;
		}
		$existing = WC()->session->get( 'mpc_raw_checkout_user_data', [] );
		$raw      = array_filter( $raw );
		if ( empty( $raw ) ) {
			return;
		}
		WC()->session->set( 'mpc_raw_checkout_user_data', array_merge( (array) $existing, $raw ) );
	}

	// ── Event emitters ───────────────────────────────────────────

	public function emit_page_view() {
		if ( is_admin() ) {
			return;
		}
		$event = new Event( 'PageView', WooData::current_user_data(), [] );
		$this->emit( $event, false ); // browser only
	}

	public function emit_view_content() {
		global $product;
		if ( ! $product ) {
			return;
		}
		$id    = (string) ( $product->get_sku() ?: $product->get_id() );
		$event = new Event( 'ViewContent', WooData::current_user_data(), [
			'content_name' => $product->get_name(),
			'content_ids'  => [ $id ],
			'content_type' => 'product',
			'value'        => (float) $product->get_price(),
			'currency'     => get_woocommerce_currency(),
			'contents'     => [ [ 'id' => $id, 'quantity' => 1, 'item_price' => (float) $product->get_price(), 'name' => $product->get_name() ] ],
		] );
		$this->emit( $event );
	}

	public function emit_view_category() {
		if ( ! is_product_category() ) {
			return;
		}
		$term = get_queried_object();
		if ( ! $term ) {
			return;
		}
		$event = new Event( 'ViewCategory', WooData::current_user_data(), [
			'content_name'     => $term->name,
			'content_category' => $term->name,
		] );
		$this->emit( $event );
	}

	public function emit_view_cart() {
		$items = WooData::cart_items();
		$event = new Event( 'ViewCart', WooData::current_user_data(), [
			'value'        => (float) WC()->cart->get_total( 'edit' ),
			'currency'     => get_woocommerce_currency(),
			'content_type' => 'product',
			'content_ids'  => WooData::ids_from_items( $items ),
			'contents'     => $items,
		] );
		$this->emit( $event );
	}

	public function emit_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
		$product = wc_get_product( $variation_id ?: $product_id );
		if ( ! $product ) {
			return;
		}
		$id    = (string) ( $product->get_sku() ?: $product->get_id() );
		$event = new Event( 'AddToCart', WooData::current_user_data(), [
			'content_name' => $product->get_name(),
			'content_ids'  => [ $id ],
			'content_type' => 'product',
			'value'        => (float) $product->get_price() * (int) $quantity,
			'currency'     => get_woocommerce_currency(),
			'contents'     => [ [ 'id' => $id, 'quantity' => (int) $quantity, 'item_price' => (float) $product->get_price(), 'name' => $product->get_name() ] ],
		] );
		$this->emit( $event );
	}

	public function emit_initiate_checkout() {
		if ( ! function_exists( 'WC' ) || is_null( WC()->cart ) || WC()->cart->is_empty() ) {
			return;
		}
		if ( WC()->session && WC()->session->get( 'mpc_bus_ic_sent' ) ) {
			return;
		}
		$items = WooData::cart_items();
		$event = new Event( 'InitiateCheckout', WooData::current_user_data(), [
			'value'        => (float) WC()->cart->get_total( 'edit' ),
			'currency'     => get_woocommerce_currency(),
			'num_items'    => WC()->cart->get_cart_contents_count(),
			'content_type' => 'product',
			'content_ids'  => WooData::ids_from_items( $items ),
			'contents'     => $items,
		] );
		$this->emit( $event );
		if ( WC()->session ) {
			WC()->session->set( 'mpc_bus_ic_sent', true );
		}
	}

	public function emit_purchase( $order_id ) {
		if ( ! $order_id ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		if ( $order->get_meta( '_mpc_bus_purchase_tracked' ) ) {
			return;
		}

		$items = WooData::order_items( $order );
		$event = new Event( 'Purchase', WooData::order_user_data( $order ), [
			'value'           => (float) $order->get_total(),
			'currency'        => $order->get_currency(),
			'num_items'       => $order->get_item_count(),
			'order_id'        => (string) $order_id,
			'content_type'    => 'product',
			'content_ids'     => WooData::ids_from_items( $items ),
			'contents'        => $items,
			'is_new_customer' => $this->is_new_customer( $order ),
		] );
		$this->emit( $event );

		$order->update_meta_data( '_mpc_bus_purchase_tracked', 1 );
		$order->save();

		if ( WC()->session ) {
			WC()->session->set( 'mpc_bus_ic_sent', null );
			WC()->session->set( 'mpc_raw_checkout_user_data', null );
		}
	}

	private function is_new_customer( $order ) {
		if ( $order->get_customer_id() ) {
			return wc_get_customer_order_count( $order->get_customer_id() ) <= 1;
		}
		if ( $order->get_billing_email() ) {
			$past = wc_get_orders( [
				'status'        => [ 'wc-completed', 'wc-processing' ],
				'billing_email' => $order->get_billing_email(),
				'limit'         => 2,
				'return'        => 'ids',
			] );
			return count( (array) $past ) <= 1;
		}
		return false;
	}

	// ── Dispatch + browser relay ─────────────────────────────────

	private function emit( Event $event, $server = true ) {
		if ( $server ) {
			EventBus::get_instance()->dispatch( $event );
		}
		if ( ! $event->for_browser ) {
			return;
		}

		$payload = $event->to_browser_payload();
		// On AJAX (e.g. add-to-cart) there is no footer to render into, so stash
		// the browser payload in the session and drain it on the next page load.
		if ( wp_doing_ajax() && function_exists( 'WC' ) && ! is_null( WC()->session ) ) {
			$pending   = WC()->session->get( 'mpc_bus_pending_browser', [] );
			$pending[] = $payload;
			WC()->session->set( 'mpc_bus_pending_browser', $pending );
		} else {
			$this->browser_queue[] = $payload;
		}
	}

	public function print_browser_relay() {
		if ( is_admin() ) {
			return;
		}
		$adapters = EventBus::get_instance()->browser_adapters();
		if ( empty( $adapters ) ) {
			return;
		}

		$queue = $this->browser_queue;
		if ( function_exists( 'WC' ) && ! is_null( WC()->session ) ) {
			$pending = WC()->session->get( 'mpc_bus_pending_browser', [] );
			if ( ! empty( $pending ) ) {
				$queue = array_merge( (array) $pending, $queue );
				WC()->session->set( 'mpc_bus_pending_browser', null );
			}
		}

		$config = [
			'adapters' => $adapters,
			'events'   => $queue,
		];
		// JSON_HEX_TAG prevents any `</script>` in product data from breaking out of the tag.
		echo "\n<script id=\"mpc-bus-data\" type=\"application/json\">" . wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP ) . "</script>\n";
		echo '<script src="' . esc_url( MPC_PLUGIN_URL . 'assets/frontend/tracker.js?ver=' . MPC_VERSION ) . '" async></script>' . "\n";
	}
}
