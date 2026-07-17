<?php
namespace Mpc\Marketing;

class AbandonedCart {
	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Hook to cart updates and checkout field updates to capture carts
		add_action( 'woocommerce_cart_updated', [ $this, 'capture_cart' ] );
		add_action( 'woocommerce_checkout_update_order_review', [ $this, 'capture_cart' ] );
		add_action( 'woocommerce_store_api_cart_update_customer_from_request', [ $this, 'capture_cart' ] );
		
		// Hook to successful orders to mark recovered
		add_action( 'woocommerce_thankyou', [ $this, 'mark_recovered' ] );
		
		// Hook to cron
		add_action( 'mpc_scan_abandoned_carts', [ $this, 'scan_and_send' ] );
		
		// Recovery Link endpoint
		add_action( 'init', [ $this, 'handle_recovery_link' ] );
	}

	public function capture_cart() {
		if ( ! function_exists('WC') || is_null( WC()->session ) || is_null( WC()->cart ) ) return;
		if ( WC()->cart->is_empty() ) return;
		
		$email = '';
		if ( is_user_logged_in() ) {
			$email = wp_get_current_user()->user_email;
		} else {
			$email = WC()->session->get( 'mpc_guest_email_raw', '' );
			if ( empty( $email ) ) {
				$customer = WC()->customer;
				if ( $customer && $customer->get_billing_email() ) {
					$email = $customer->get_billing_email();
				}
			}
		}
		
		if ( empty( $email ) ) return;

		global $wpdb;
		$session_id = WC()->session->get_customer_id();
		$cart_contents = wp_json_encode( WC()->cart->get_cart() );
		
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, status FROM {$wpdb->prefix}mpc_abandoned_carts WHERE session_id = %s", $session_id ) );
		
		// Store timestamps in UTC (GMT) so the recovery scan window matches regardless
		// of the site or database server timezone. See scan_and_send().
		$now_utc = current_time( 'mysql', true );

		if ( $existing ) {
			if ( $existing->status === 'recovered' ) {
				// If they are building a new cart after a previous purchase, reset to pending
				$wpdb->update(
					"{$wpdb->prefix}mpc_abandoned_carts",
					[ 'cart_contents' => $cart_contents, 'status' => 'pending', 'last_active' => $now_utc ],
					[ 'id' => $existing->id ]
				);
			} else {
				$wpdb->update(
					"{$wpdb->prefix}mpc_abandoned_carts",
					[ 'cart_contents' => $cart_contents, 'last_active' => $now_utc ],
					[ 'id' => $existing->id ]
				);
			}
		} else {
			$wpdb->insert(
				"{$wpdb->prefix}mpc_abandoned_carts",
				[
					'session_id' => $session_id,
					'email' => $email,
					'cart_contents' => $cart_contents,
					'status' => 'pending',
					'last_active' => $now_utc
				]
			);
		}
	}

	public function mark_recovered( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) return;
		
		$email = $order->get_billing_email();
		if ( ! $email ) return;
		
		global $wpdb;
		$wpdb->update(
			"{$wpdb->prefix}mpc_abandoned_carts",
			[ 'status' => 'recovered' ],
			[ 'email' => $email ]
		);
	}

	public function scan_and_send() {
		$is_enabled = get_option( 'mpc_enable_abandoned_cart', false );
		if ( ! $is_enabled ) return;

		global $wpdb;
		// Find carts pending for more than 1 hour (but less than 24h to avoid old spam)
		$carts = $wpdb->get_results( "
			SELECT * FROM {$wpdb->prefix}mpc_abandoned_carts
			WHERE status = 'pending'
			AND last_active < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR)
			AND last_active > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 24 HOUR)
		" );

		if ( empty( $carts ) ) return;

		$subject = get_option( 'mpc_recovery_subject', 'Complete your purchase' );
		$message_tmpl = get_option( 'mpc_recovery_message', 'You left something in your cart! Click below to complete your order.' );

		foreach ( $carts as $cart ) {
			$recovery_url = add_query_arg( [ 'mpc_recover' => $cart->session_id ], wc_get_checkout_url() );
			
			$cart_items_html = '<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">';
			$contents = json_decode( $cart->cart_contents, true );
			if ( is_array( $contents ) ) {
				foreach ( $contents as $item ) {
					$product = wc_get_product( $item['variation_id'] ?: $item['product_id'] );
					if ( ! $product ) continue;
					
					$image = $product->get_image( [ 64, 64 ], [ 'style' => 'border-radius: 5px; vertical-align: middle;' ] );
					$name = $product->get_name();
					$price = wc_price( $product->get_price() );
					$qty = $item['quantity'];

					$cart_items_html .= '<tr>';
					$cart_items_html .= '<td style="padding: 10px; border-bottom: 1px solid #eee; width: 80px;">' . $image . '</td>';
					$cart_items_html .= '<td style="padding: 10px; border-bottom: 1px solid #eee;"><strong>' . esc_html( $name ) . '</strong><br>Qty: ' . (int) $qty . '</td>';
					$cart_items_html .= '<td style="padding: 10px; border-bottom: 1px solid #eee; text-align: right;">' . $price . '</td>';
					$cart_items_html .= '</tr>';
				}
			}
			$cart_items_html .= '</table>';

			$body = "<html><body style='font-family: sans-serif; color: #333;'>";
			$body .= "<div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 8px;'>";
			$body .= "<h2 style='text-align: center; color: #000;'>" . esc_html( $subject ) . "</h2>";
			$body .= "<p style='text-align: center;'>" . esc_html( $message_tmpl ) . "</p>";
			$body .= $cart_items_html;
			$body .= "<p style='text-align: center; margin-top: 30px;'><a href='" . esc_url( $recovery_url ) . "' style='padding: 12px 24px; background: #007bff; color: #fff; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>Complete Your Order</a></p>";
			$body .= "</div>";
			$body .= "</body></html>";
			
			$headers = array('Content-Type: text/html; charset=UTF-8');
			
			wp_mail( $cart->email, $subject, $body, $headers );
			
			$wpdb->update(
				"{$wpdb->prefix}mpc_abandoned_carts",
				[ 'status' => 'abandoned' ],
				[ 'id' => $cart->id ]
			);
		}
	}

	public function handle_recovery_link() {
		if ( isset( $_GET['mpc_recover'] ) ) {
			$session_id = sanitize_text_field( $_GET['mpc_recover'] );
			global $wpdb;
			$cart = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}mpc_abandoned_carts WHERE session_id = %s", $session_id ) );
			
			if ( $cart && function_exists('WC') && isset(WC()->cart) ) {
				WC()->cart->empty_cart();
				$contents = json_decode( $cart->cart_contents, true );
				if ( is_array( $contents ) ) {
					foreach ( $contents as $item ) {
						WC()->cart->add_to_cart( 
							$item['product_id'], 
							$item['quantity'], 
							$item['variation_id'] ?? 0, 
							$item['variation'] ?? [], 
							$item['cart_item_data'] ?? [] 
						);
					}
				}
			}
			// Let it redirect naturally to checkout since we are hooking into init and URL is checkout URL
		}
	}
}
