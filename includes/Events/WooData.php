<?php
namespace Mpc\Events;

/**
 * Extracts normalized (raw, un-hashed) user + commerce data from WooCommerce
 * carts, orders and the current request. Shared by the Collector so every
 * channel receives identically-shaped data.
 *
 * @package Mpc\Events
 */
class WooData {

	/** Raw user data for the current visitor (logged-in + checkout session + click ids). */
	public static function current_user_data() {
		$user = [
			'client_ip_address' => class_exists( '\WC_Geolocation' ) ? \WC_Geolocation::get_ip_address() : ( $_SERVER['REMOTE_ADDR'] ?? '' ),
			'client_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
		];

		if ( is_user_logged_in() ) {
			$wp_user = wp_get_current_user();
			if ( ! empty( $wp_user->user_email ) )     $user['email']       = $wp_user->user_email;
			if ( ! empty( $wp_user->user_firstname ) )  $user['first_name'] = $wp_user->user_firstname;
			if ( ! empty( $wp_user->user_lastname ) )   $user['last_name']  = $wp_user->user_lastname;
			$user['external_id'] = (string) $wp_user->ID;
		}

		// Checkout PII captured into the session by the Meta path is reused here
		// when available, but stored RAW under a parallel key set.
		if ( function_exists( 'WC' ) && ! is_null( WC()->session ) ) {
			$raw = WC()->session->get( 'mpc_raw_checkout_user_data', [] );
			if ( is_array( $raw ) ) {
				$user = array_merge( $user, $raw );
			}
		}

		return array_merge( $user, self::click_ids() );
	}

	/** Advertising click identifiers from cookies / query string. */
	public static function click_ids() {
		$ids = [];

		// Meta
		if ( ! empty( $_COOKIE['_fbp'] ) ) $ids['fbp'] = sanitize_text_field( wp_unslash( $_COOKIE['_fbp'] ) );
		if ( ! empty( $_COOKIE['_fbc'] ) ) $ids['fbc'] = sanitize_text_field( wp_unslash( $_COOKIE['_fbc'] ) );

		// Google Analytics client id (from the _ga cookie: GA1.2.XXXX.YYYY -> XXXX.YYYY)
		if ( ! empty( $_COOKIE['_ga'] ) ) {
			$parts = explode( '.', sanitize_text_field( wp_unslash( $_COOKIE['_ga'] ) ) );
			if ( count( $parts ) >= 4 ) {
				$ids['ga_client_id'] = $parts[2] . '.' . $parts[3];
			}
		}
		if ( ! empty( $_GET['gclid'] ) )   $ids['gclid']  = sanitize_text_field( wp_unslash( $_GET['gclid'] ) );

		// TikTok
		if ( ! empty( $_COOKIE['_ttp'] ) ) $ids['ttp']    = sanitize_text_field( wp_unslash( $_COOKIE['_ttp'] ) );
		if ( ! empty( $_GET['ttclid'] ) )  $ids['ttclid'] = sanitize_text_field( wp_unslash( $_GET['ttclid'] ) );

		// Pinterest
		if ( ! empty( $_GET['epik'] ) )    $ids['epik']   = sanitize_text_field( wp_unslash( $_GET['epik'] ) );

		// Snapchat
		if ( ! empty( $_COOKIE['_scid'] ) )   $ids['scid']    = sanitize_text_field( wp_unslash( $_COOKIE['_scid'] ) );
		if ( ! empty( $_GET['ScCid'] ) )      $ids['sc_click'] = sanitize_text_field( wp_unslash( $_GET['ScCid'] ) );

		return $ids;
	}

	/** Normalized line items from the active cart. */
	public static function cart_items() {
		$items = [];
		if ( ! function_exists( 'WC' ) || is_null( WC()->cart ) ) {
			return $items;
		}
		foreach ( WC()->cart->get_cart() as $line ) {
			$product = wc_get_product( $line['variation_id'] ?: $line['product_id'] );
			if ( ! $product ) {
				continue;
			}
			$qty = (int) $line['quantity'];
			$items[] = [
				'id'         => (string) ( $product->get_sku() ?: $product->get_id() ),
				'quantity'   => $qty,
				'item_price' => $qty ? (float) $line['line_subtotal'] / $qty : (float) $product->get_price(),
				'name'       => $product->get_name(),
			];
		}
		return $items;
	}

	/** Normalized line items from an order. */
	public static function order_items( $order ) {
		$items = [];
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			$qty     = max( 1, (int) $item->get_quantity() );
			$items[] = [
				'id'         => (string) ( $product ? ( $product->get_sku() ?: $product->get_id() ) : $item->get_product_id() ),
				'quantity'   => $qty,
				'item_price' => (float) $item->get_total() / $qty,
				'name'       => $item->get_name(),
			];
		}
		return $items;
	}

	/** Raw user data from a completed order (most complete match set). */
	public static function order_user_data( $order ) {
		$user = [
			'client_ip_address' => $order->get_customer_ip_address(),
			'client_user_agent' => $order->get_customer_user_agent(),
		];
		if ( $order->get_billing_email() )      $user['email']       = $order->get_billing_email();
		if ( $order->get_billing_phone() )      $user['phone']       = $order->get_billing_phone();
		if ( $order->get_billing_first_name() ) $user['first_name']  = $order->get_billing_first_name();
		if ( $order->get_billing_last_name() )  $user['last_name']   = $order->get_billing_last_name();
		if ( $order->get_billing_city() )       $user['city']        = $order->get_billing_city();
		if ( $order->get_billing_state() )      $user['state']       = $order->get_billing_state();
		if ( $order->get_billing_postcode() )   $user['zip']         = $order->get_billing_postcode();
		if ( $order->get_billing_country() )    $user['country']     = $order->get_billing_country();
		if ( $order->get_customer_id() )        $user['external_id'] = (string) $order->get_customer_id();
		return array_merge( $user, self::click_ids() );
	}

	/** Content ids from normalized items. */
	public static function ids_from_items( array $items ) {
		return array_values( array_unique( array_map( function ( $i ) {
			return $i['id'];
		}, $items ) ) );
	}
}
