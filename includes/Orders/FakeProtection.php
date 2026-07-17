<?php
namespace Mpc\Orders;

class FakeProtection {
	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'woocommerce_after_checkout_validation', [ $this, 'validate_checkout' ], 10, 2 );
		add_filter( 'mpc_block_purchase_event', [ $this, 'block_fake_event' ], 10, 2 );
	}

	public function validate_checkout( $data, $errors ) {
		$phone = $data['billing_phone'] ?? '';
		$email = $data['billing_email'] ?? '';

		// Basic Fake Data Check (e.g., repeating numbers)
		if ( preg_match( '/^(.)\1{5,}$/', preg_replace( '/[^0-9]/', '', $phone ) ) ) {
			$errors->add( 'validation', 'Please enter a valid phone number.' );
		}
	}

	public function block_fake_event( $block, $order ) {
		// Do not send events for failed/cancelled orders initially
		if ( in_array( $order->get_status(), [ 'failed', 'cancelled', 'refunded' ] ) ) {
			return true;
		}
		
		// If custom meta flag for fake is set
		if ( $order->get_meta( '_mpc_is_fake' ) === 'yes' ) {
			return true;
		}

		return $block;
	}
}
