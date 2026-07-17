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
		add_action( 'wp_ajax_mpc_save_settings', [ $this, 'ajax_save_settings' ] );
		add_action( 'wp_ajax_mpc_retry_queue', [ $this, 'ajax_retry_queue' ] );
		add_action( 'wp_ajax_mpc_clear_logs', [ $this, 'ajax_clear_logs' ] );
		add_action( 'wp_ajax_mpc_test_token', [ $this, 'ajax_test_token' ] );
		add_action( 'wp_ajax_mpc_fetch_logs_html', [ $this, 'ajax_fetch_logs_html' ] );
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

	public function enqueue_assets( $hook ) {
		if ( $hook !== 'toplevel_page_mpc-settings' ) return;
		wp_enqueue_style( 'mpc-admin-css', MPC_PLUGIN_URL . 'assets/admin/css/admin.css', [], MPC_VERSION );
		wp_enqueue_script( 'mpc-admin-js', MPC_PLUGIN_URL . 'assets/admin/js/admin.js', ['jquery'], MPC_VERSION, true );
	}

	public function render_settings_page() {
		include MPC_PLUGIN_DIR . 'templates/admin-dashboard.php';
	}

	public function ajax_save_settings() {
		if ( ! current_user_can('manage_options') ) wp_send_json_error();
		check_ajax_referer( 'mpc_save_settings', 'mpc_nonce' );

		// Core
		update_option( 'mpc_pixel_id', sanitize_text_field( $_POST['mpc_pixel_id'] ?? '' ) );
		update_option( 'mpc_capi_token', sanitize_textarea_field( $_POST['mpc_capi_token'] ?? '' ) );
		update_option( 'mpc_test_code', sanitize_text_field( $_POST['mpc_test_code'] ?? '' ) );

		// Pixel Behavioral
		update_option( 'mpc_enable_scroll', isset( $_POST['mpc_enable_scroll'] ) ? 1 : 0 );
		update_option( 'mpc_enable_time_on_page', isset( $_POST['mpc_enable_time_on_page'] ) ? 1 : 0 );
		update_option( 'mpc_enable_outbound', isset( $_POST['mpc_enable_outbound'] ) ? 1 : 0 );

		// Event Toggles
		$events = [
			'mpc_ev_pageview', 'mpc_ev_viewcontent', 'mpc_ev_viewcategory',
			'mpc_ev_viewcart', 'mpc_ev_addtocart', 'mpc_ev_removecart',
			'mpc_ev_checkout', 'mpc_ev_purchase'
		];
		foreach ( $events as $ev ) {
			update_option( $ev, isset( $_POST[$ev] ) ? 1 : 0 );
		}

		// WooCommerce Rules
		update_option( 'mpc_purchase_status_filter', isset( $_POST['mpc_purchase_status_filter'] ) ? 1 : 0 );
		update_option( 'mpc_enable_ltv', isset( $_POST['mpc_enable_ltv'] ) ? 1 : 0 );
		update_option( 'mpc_enable_acr', isset( $_POST['mpc_enable_acr'] ) ? 1 : 0 );

		// Cart Recovery
		update_option( 'mpc_enable_abandoned_cart', isset( $_POST['mpc_enable_abandoned_cart'] ) ? 1 : 0 );
		update_option( 'mpc_recovery_subject', sanitize_text_field( $_POST['mpc_recovery_subject'] ?? 'Complete your purchase' ) );
		update_option( 'mpc_recovery_message', sanitize_textarea_field( $_POST['mpc_recovery_message'] ?? '' ) );

		// Blocklist
		update_option( 'mpc_blocked_phones', sanitize_textarea_field( $_POST['mpc_blocked_phones'] ?? '' ) );
		update_option( 'mpc_blocked_emails', sanitize_textarea_field( $_POST['mpc_blocked_emails'] ?? '' ) );

		wp_send_json_success( ['message' => 'Settings saved successfully!'] );
	}

	public function ajax_retry_queue() {
		if ( ! current_user_can('manage_options') ) wp_send_json_error();
		\Mpc\Tracker\RetryQueue::get_instance()->process_queue();
		wp_send_json_success( ['message' => 'Retry queue processed.'] );
	}

	public function ajax_clear_logs() {
		if ( ! current_user_can('manage_options') ) wp_send_json_error();
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}mpc_event_logs" );
		wp_send_json_success( ['message' => 'All event logs cleared.'] );
	}

	public function ajax_test_token() {
		if ( ! current_user_can('manage_options') ) wp_send_json_error();
		check_ajax_referer( 'mpc_save_settings', 'mpc_nonce' );

		$pixel_id = sanitize_text_field( $_POST['pixel_id'] ?? '' );
		$token = sanitize_textarea_field( $_POST['token'] ?? '' );

		if ( ! $pixel_id || ! $token ) {
			wp_send_json_error( ['message' => 'Missing Pixel ID or Token.'] );
		}

		$url = "https://graph.facebook.com/v19.0/{$pixel_id}?access_token={$token}&fields=name,creation_time";
		$response = wp_remote_get( $url, [ 'timeout' => 15 ] );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( ['message' => $response->get_error_message()] );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code === 200 && ! empty( $body['name'] ) ) {
			$creation_time = !empty($body['creation_time']) ? gmdate('Y-m-d', strtotime($body['creation_time'])) : 'Unknown';
			wp_send_json_success( [
				'name' => esc_html( $body['name'] ),
				'creation_time' => $creation_time
			] );
		} else {
			$error_msg = $body['error']['message'] ?? 'Invalid response from Meta API.';
			$error_code = $body['error']['code'] ?? 0;
			
			// CAPI tokens generated from Events Manager often lack read access to the Pixel node itself.
			if ( $error_code == 100 && strpos( $error_msg, 'Missing Permission' ) !== false ) {
				wp_send_json_success( [
					'name' => 'Hidden by Meta (Token is valid for sending events)',
					'creation_time' => 'Unknown'
				] );
			} else {
				wp_send_json_error( ['message' => esc_html( $error_msg )] );
			}
		}
	}

	public function ajax_fetch_logs_html() {
		if ( ! current_user_can('manage_options') ) wp_send_json_error();

		global $wpdb;
		$logs = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}mpc_event_logs ORDER BY id DESC LIMIT 50" );
		
		ob_start();
		if ( empty($logs) ) : ?>
			<tr><td colspan="6" style="text-align:center; color: var(--mpc-text-dim); padding: 30px;">No events logged yet. Events will appear here once your Pixel ID and CAPI Token are configured.</td></tr>
		<?php else :
			foreach ( $logs as $log ) :
				$payload = json_decode( $log->response, true );
				$user_data_raw = json_decode( $log->payload ?? '{}', true );
				$ud = $user_data_raw['data'][0]['user_data'] ?? [];
				$fields = ['em'=>'Email','ph'=>'Phone','fn'=>'First Name','ln'=>'Last Name','ct'=>'City','st'=>'State','zp'=>'ZIP','country'=>'Country','fbp'=>'FBP','fbc'=>'FBC','external_id'=>'ExtID'];
		?>
		<tr>
			<td><strong><?php echo esc_html($log->event_name); ?></strong></td>
			<td style="font-size:.75rem; color: var(--mpc-text-dim); word-break: break-all; min-width:180px;"><?php echo esc_html( $log->event_id ); ?></td>
			<td><span class="mpc-badge mpc-badge-info">Server</span></td>
			<td>
				<?php if ( $log->status == 200 ) : ?>
					<span class="mpc-badge mpc-badge-ok">200 OK</span>
				<?php else : ?>
					<span class="mpc-badge mpc-badge-danger"><?php echo esc_html($log->status); ?></span>
				<?php endif; ?>
			</td>
			<td>
				<div class="mpc-chip-row">
					<?php foreach ( $fields as $key => $label ) :
						if ( ! empty( $ud[$key] ) ) : ?>
							<span class="mpc-chip mpc-chip-ok">✓ <?php echo $label; ?></span>
						<?php else : ?>
							<span class="mpc-chip mpc-chip-miss">✗ <?php echo $label; ?></span>
						<?php endif;
					endforeach; ?>
				</div>
			</td>
			<td style="font-size: .8rem; color: var(--mpc-text-dim); white-space: nowrap;"><?php echo esc_html($log->created_at); ?></td>
		</tr>
		<?php endforeach;
		endif;
		$html = ob_get_clean();
		wp_send_json_success( ['html' => $html] );
	}
}
