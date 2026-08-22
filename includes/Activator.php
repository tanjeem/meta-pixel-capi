<?php
namespace Mpc;

class Activator {
	public static function activate() {
		self::create_tables();
		self::schedule_events();
	}

	public static function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$wpdb->prefix}mpc_event_logs (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			event_name varchar(100) NOT NULL,
			event_id varchar(150) NOT NULL,
			event_type varchar(20) NOT NULL,
			payload longtext NULL,
			status varchar(50) NOT NULL,
			response text NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			KEY event_lookup (event_name,event_id)
		) $charset_collate;
		
		CREATE TABLE {$wpdb->prefix}mpc_retry_queue (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			payload text NOT NULL,
			attempts int(11) DEFAULT 0 NOT NULL,
			status varchar(20) DEFAULT 'pending' NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;
		
		CREATE TABLE {$wpdb->prefix}mpc_purchase_sent (
			order_id bigint(20) unsigned NOT NULL,
			event_id varchar(150) NOT NULL,
			source varchar(40) NOT NULL,
			attempts smallint(5) unsigned DEFAULT 1 NOT NULL,
			delivered tinyint(1) DEFAULT 0 NOT NULL,
			claimed_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (order_id)
		) $charset_collate;
		
		CREATE TABLE {$wpdb->prefix}mpc_abandoned_carts (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			session_id varchar(150) NOT NULL,
			email varchar(150) NOT NULL,
			cart_contents longtext NOT NULL,
			status varchar(20) DEFAULT 'pending' NOT NULL,
			last_active datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY session_id (session_id)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	private static function schedule_events() {
		if ( ! wp_next_scheduled( 'mpc_retry_failed_events' ) ) {
			wp_schedule_event( time(), 'hourly', 'mpc_retry_failed_events' );
		}
		if ( ! wp_next_scheduled( 'mpc_scan_abandoned_carts' ) ) {
			wp_schedule_event( time(), 'hourly', 'mpc_scan_abandoned_carts' );
		}
	}
}
