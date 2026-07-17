<?php
namespace Mpc\Orders;

class Blocklist {
	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'woocommerce_after_checkout_validation', [ $this, 'check_blocklist' ], 10, 2 );
	}

	public function check_blocklist( $data, $errors ) {
		$phone = $data['billing_phone'] ?? '';
		$email = $data['billing_email'] ?? '';

		$blocked_phones = explode( "\n", get_option( 'mpc_blocked_phones', '' ) );
		$blocked_phones = array_map( 'trim', $blocked_phones );

		$blocked_emails = explode( "\n", get_option( 'mpc_blocked_emails', '' ) );
		$blocked_emails = array_map( 'trim', $blocked_emails );

		if ( in_array( trim( $phone ), $blocked_phones ) || in_array( trim( $email ), $blocked_emails ) ) {
			$errors->add( 'validation', 'Sorry, your order cannot be processed at this time.' );
		}
	}
}
