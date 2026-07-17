<?php
namespace Mpc\Admin;

class AdminMenu {
	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', [ $this, 'register_menus' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	public function register_menus() {
		add_menu_page(
			'Meta Pixel & CAPI',
			'Pixel & CAPI',
			'manage_options',
			'mpc-settings',
			[ $this, 'render_settings_page' ],
			'dashicons-chart-area',
			56
		);
	}

	public function register_settings() {
		register_setting( 'mpc_settings_group', 'mpc_pixel_id' );
		register_setting( 'mpc_settings_group', 'mpc_capi_token' );
		register_setting( 'mpc_settings_group', 'mpc_test_code' );
		register_setting( 'mpc_settings_group', 'mpc_blocked_phones' );
		register_setting( 'mpc_settings_group', 'mpc_blocked_emails' );
	}

	public function enqueue_assets( $hook ) {
		if ( $hook !== 'toplevel_page_mpc-settings' ) {
			return;
		}
		if ( isset( $_POST['mpc_test_code'] ) ) {
			update_option( 'mpc_pixel_id', sanitize_text_field( $_POST['mpc_pixel_id'] ) );
			update_option( 'mpc_capi_token', sanitize_textarea_field( $_POST['mpc_capi_token'] ) );
			update_option( 'mpc_test_code', sanitize_text_field( $_POST['mpc_test_code'] ) );
			
			// Fake Protection
			update_option( 'mpc_enable_fake_protection', isset( $_POST['mpc_enable_fake_protection'] ) ? 1 : 0 );
			
			// Blocklist
			update_option( 'mpc_blocklist_emails', sanitize_textarea_field( $_POST['mpc_blocklist_emails'] ?? '' ) );
			update_option( 'mpc_blocklist_phones', sanitize_textarea_field( $_POST['mpc_blocklist_phones'] ?? '' ) );

			// Cart Recovery
			update_option( 'mpc_enable_abandoned_cart', isset( $_POST['mpc_enable_abandoned_cart'] ) ? 1 : 0 );
			update_option( 'mpc_recovery_subject', sanitize_text_field( $_POST['mpc_recovery_subject'] ?? 'Complete your purchase' ) );
			update_option( 'mpc_recovery_message', sanitize_textarea_field( $_POST['mpc_recovery_message'] ?? 'You left something in your cart! Click below to complete your order.' ) );

			wp_send_json_success( ['message' => 'Settings saved successfully!'] );
		}
		wp_enqueue_style( 'mpc-admin-css', MPC_PLUGIN_URL . 'assets/admin/css/admin.css', [], MPC_VERSION );
		wp_enqueue_script( 'mpc-admin-js', MPC_PLUGIN_URL . 'assets/admin/js/admin.js', ['jquery'], MPC_VERSION, true );
	}

	public function render_settings_page() {
		include MPC_PLUGIN_DIR . 'templates/admin-dashboard.php';
	}
}
