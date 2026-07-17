<?php
namespace Mpc\Tracker;

class Deduplication {
	public static function get_order_event_id( $order_id ) {
		return 'order_' . $order_id;
	}

	public static function generate_event_id() {
		return bin2hex( random_bytes( 16 ) );
	}
}
