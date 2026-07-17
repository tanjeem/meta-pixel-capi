<?php
/**
 * Uninstall handler for Meta Pixel & CAPI Ultimate.
 *
 * Runs only when the user deletes the plugin from the WordPress admin.
 * Removes all options, custom tables, scheduled events and transients created
 * by the plugin so nothing is left behind.
 *
 * @package Mpc
 */

// Exit if uninstall is not called from WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// ── Options ──────────────────────────────────────────────────────────────
$options = [
	'mpc_plugin_version',
	'mpc_pixel_id',
	'mpc_capi_token',
	'mpc_test_code',
	'mpc_enable_scroll',
	'mpc_enable_time_on_page',
	'mpc_enable_outbound',
	'mpc_ev_pageview',
	'mpc_ev_viewcontent',
	'mpc_ev_viewcategory',
	'mpc_ev_viewcart',
	'mpc_ev_addtocart',
	'mpc_ev_removecart',
	'mpc_ev_checkout',
	'mpc_ev_purchase',
	'mpc_purchase_status_filter',
	'mpc_enable_ltv',
	'mpc_enable_acr',
	'mpc_enable_abandoned_cart',
	'mpc_recovery_subject',
	'mpc_recovery_message',
	'mpc_blocked_phones',
	'mpc_blocked_emails',
	// Integrations (multi-platform channels).
	'mpc_ga4_enabled',
	'mpc_ga4_measurement_id',
	'mpc_ga4_api_secret',
	'mpc_google_ads_enabled',
	'mpc_google_ads_conversion_id',
	'mpc_google_ads_purchase_label',
	'mpc_tiktok_enabled',
	'mpc_tiktok_pixel_code',
	'mpc_tiktok_access_token',
	'mpc_pinterest_enabled',
	'mpc_pinterest_tag_id',
	'mpc_pinterest_ad_account_id',
	'mpc_pinterest_access_token',
	'mpc_snapchat_enabled',
	'mpc_snapchat_pixel_id',
	'mpc_snapchat_access_token',
];
foreach ( $options as $option ) {
	delete_option( $option );
}

// ── Custom tables ────────────────────────────────────────────────────────
$tables = [
	$wpdb->prefix . 'mpc_event_logs',
	$wpdb->prefix . 'mpc_retry_queue',
	$wpdb->prefix . 'mpc_abandoned_carts',
];
foreach ( $tables as $table ) {
	// Table name cannot be parameterised; it is built from a trusted prefix.
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL
}

// ── Scheduled cron events ────────────────────────────────────────────────
foreach ( [ 'mpc_retry_failed_events', 'mpc_scan_abandoned_carts', 'mpc_nightly_recovery_cron' ] as $hook ) {
	wp_clear_scheduled_hook( $hook );
}

// ── Transients (cached LTV lookups) ──────────────────────────────────────
$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_mpc_ltv_%' OR option_name LIKE '_transient_timeout_mpc_ltv_%'"
); // phpcs:ignore WordPress.DB.PreparedSQL
