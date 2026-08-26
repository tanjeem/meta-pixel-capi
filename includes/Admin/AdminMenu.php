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
		add_action( 'wp_ajax_mpc_purchase_audit', [ $this, 'ajax_purchase_audit' ] );
		add_action( 'wp_ajax_mpc_export_diagnostics', [ $this, 'ajax_export_diagnostics' ] );
		add_action( 'wp_ajax_mpc_conflict_scan', [ $this, 'ajax_conflict_scan' ] );
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
		update_option( 'mpc_filter_bots', isset( $_POST['mpc_filter_bots'] ) ? 1 : 0 );
		update_option( 'mpc_log_retention_days', max( 0, min( 3650, (int) ( $_POST['mpc_log_retention_days'] ?? 30 ) ) ) );

		// Cart Recovery
		update_option( 'mpc_enable_abandoned_cart', isset( $_POST['mpc_enable_abandoned_cart'] ) ? 1 : 0 );
		update_option( 'mpc_recovery_subject', sanitize_text_field( $_POST['mpc_recovery_subject'] ?? 'Complete your purchase' ) );
		update_option( 'mpc_recovery_message', sanitize_textarea_field( $_POST['mpc_recovery_message'] ?? '' ) );

		// Blocklist
		update_option( 'mpc_blocked_phones', sanitize_textarea_field( $_POST['mpc_blocked_phones'] ?? '' ) );
		update_option( 'mpc_blocked_emails', sanitize_textarea_field( $_POST['mpc_blocked_emails'] ?? '' ) );

		// ── Integrations: Google Analytics 4 ──
		update_option( 'mpc_ga4_enabled', isset( $_POST['mpc_ga4_enabled'] ) ? 1 : 0 );
		update_option( 'mpc_ga4_measurement_id', sanitize_text_field( $_POST['mpc_ga4_measurement_id'] ?? '' ) );
		update_option( 'mpc_ga4_api_secret', sanitize_text_field( $_POST['mpc_ga4_api_secret'] ?? '' ) );

		// ── Integrations: Google Ads ──
		update_option( 'mpc_google_ads_enabled', isset( $_POST['mpc_google_ads_enabled'] ) ? 1 : 0 );
		update_option( 'mpc_google_ads_conversion_id', sanitize_text_field( $_POST['mpc_google_ads_conversion_id'] ?? '' ) );
		update_option( 'mpc_google_ads_purchase_label', sanitize_text_field( $_POST['mpc_google_ads_purchase_label'] ?? '' ) );

		// ── Integrations: TikTok ──
		update_option( 'mpc_tiktok_enabled', isset( $_POST['mpc_tiktok_enabled'] ) ? 1 : 0 );
		update_option( 'mpc_tiktok_pixel_code', sanitize_text_field( $_POST['mpc_tiktok_pixel_code'] ?? '' ) );
		update_option( 'mpc_tiktok_access_token', sanitize_text_field( $_POST['mpc_tiktok_access_token'] ?? '' ) );

		// ── Integrations: Pinterest ──
		update_option( 'mpc_pinterest_enabled', isset( $_POST['mpc_pinterest_enabled'] ) ? 1 : 0 );
		update_option( 'mpc_pinterest_tag_id', sanitize_text_field( $_POST['mpc_pinterest_tag_id'] ?? '' ) );
		update_option( 'mpc_pinterest_ad_account_id', sanitize_text_field( $_POST['mpc_pinterest_ad_account_id'] ?? '' ) );
		update_option( 'mpc_pinterest_access_token', sanitize_text_field( $_POST['mpc_pinterest_access_token'] ?? '' ) );

		// ── Integrations: Snapchat ──
		update_option( 'mpc_snapchat_enabled', isset( $_POST['mpc_snapchat_enabled'] ) ? 1 : 0 );
		update_option( 'mpc_snapchat_pixel_id', sanitize_text_field( $_POST['mpc_snapchat_pixel_id'] ?? '' ) );
		update_option( 'mpc_snapchat_access_token', sanitize_text_field( $_POST['mpc_snapchat_access_token'] ?? '' ) );

		// ── Consent & Privacy ──
		update_option( 'mpc_consent_required', isset( $_POST['mpc_consent_required'] ) ? 1 : 0 );
		update_option( 'mpc_consent_mode_v2', isset( $_POST['mpc_consent_mode_v2'] ) ? 1 : 0 );
		$provider = sanitize_text_field( $_POST['mpc_consent_provider'] ?? 'none' );
		update_option( 'mpc_consent_provider', in_array( $provider, [ 'none', 'wp_consent_api', 'cookie' ], true ) ? $provider : 'none' );
		update_option( 'mpc_consent_cookie_name', sanitize_text_field( $_POST['mpc_consent_cookie_name'] ?? '' ) );
		update_option( 'mpc_consent_cookie_value', sanitize_text_field( $_POST['mpc_consent_cookie_value'] ?? '' ) );

		wp_send_json_success( ['message' => 'Settings saved successfully!'] );
	}

	public function ajax_retry_queue() {
		if ( ! current_user_can('manage_options') ) wp_send_json_error();
		check_ajax_referer( 'mpc_save_settings', 'mpc_nonce' );
		\Mpc\Tracker\RetryQueue::get_instance()->process_queue();
		wp_send_json_success( ['message' => 'Retry queue processed.'] );
	}

	public function ajax_clear_logs() {
		if ( ! current_user_can('manage_options') ) wp_send_json_error();
		check_ajax_referer( 'mpc_save_settings', 'mpc_nonce' );
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

		$url = "https://graph.facebook.com/" . MPC_GRAPH_VERSION . "/{$pixel_id}?access_token={$token}&fields=name,creation_time";
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

	/**
	 * Purchase send audit.
	 *
	 * Answers one question directly: has any order had its Purchase sent to Meta
	 * more than once? Reads the existing event log, so it covers history recorded
	 * before this screen existed, and joins the claim table for the triggering
	 * hook where that is available.
	 */
	public function ajax_purchase_audit() {
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();
		check_ajax_referer( 'mpc_save_settings', 'mpc_nonce' );

		global $wpdb;

		$days = isset( $_POST['days'] ) ? max( 1, min( 90, (int) $_POST['days'] ) ) : 14;

		// created_at is written by the database's own CURRENT_TIMESTAMP, so compare
		// against NOW() — both are in the database server's timezone, which is not
		// necessarily the site's.
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT event_id,
			        COUNT(*) AS sends,
			        SUM(CASE WHEN CAST(status AS UNSIGNED) BETWEEN 200 AND 299 THEN 1 ELSE 0 END) AS accepted,
			        MIN(created_at) AS first_sent,
			        MAX(created_at) AS last_sent
			 FROM {$wpdb->prefix}mpc_event_logs
			 WHERE event_name = 'Purchase' AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
			 GROUP BY event_id
			 ORDER BY sends DESC, MAX(created_at) DESC
			 LIMIT 200",
			$days
		) );

		$orders     = count( (array) $rows );
		$total      = 0;
		$duplicated = 0;
		foreach ( (array) $rows as $r ) {
			$total += (int) $r->sends;
			if ( (int) $r->sends > 1 ) $duplicated++;
		}

		// Per-order timing: which hook fired, how stale the timestamp was, and
		// whether the browser pixel was ever printed. These three answer both of
		// Meta's Purchase complaints — "timestamp too far in the past" and a
		// pixel-to-CAPI ratio under 25% — and they usually share one cause:
		// woocommerce_thankyou never running, so the first hook to fire is a much
		// later status transition carrying the original order time.
		$db_offset = (int) $wpdb->get_var( 'SELECT TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), NOW())' );
		$timing    = [];
		if ( function_exists( 'wc_get_order' ) ) {
			foreach ( array_slice( (array) $rows, 0, 50 ) as $r ) {
				if ( ! preg_match( '/^order_(\d+)$/', (string) $r->event_id, $m ) ) continue;
				$o = wc_get_order( (int) $m[1] );
				if ( ! $o || ! $o->get_date_created() ) continue;
				$sent_utc = strtotime( $r->first_sent . ' UTC' ) - $db_offset;
				$timing[ $r->event_id ] = [
					'lag_minutes'   => (int) round( ( $sent_utc - $o->get_date_created()->getTimestamp() ) / 60 ),
					'pixel_printed' => (bool) $o->get_meta( '_mpc_purchase_pixel_printed' ),
				];
			}
		}

		// Triggering hook per order, when the claim table is present (2.2.4+).
		$sources    = [];
		$claim_table = $wpdb->prefix . 'mpc_purchase_sent';
		$has_claims  = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $claim_table ) ) === $claim_table );
		if ( $has_claims ) {
			$claims = $wpdb->get_results( "SELECT order_id, source, attempts, delivered FROM {$claim_table}" );
			foreach ( (array) $claims as $c ) {
				$sources[ 'order_' . $c->order_id ] = $c;
			}
		}

		ob_start();
		if ( empty( $rows ) ) : ?>
			<tr><td colspan="8" style="text-align:center; color: var(--mpc-text-dim); padding: 30px;">No Purchase events logged in this window.</td></tr>
		<?php else :
			foreach ( $rows as $r ) :
				$dupe   = ( (int) $r->sends > 1 );
				$claim  = $sources[ $r->event_id ] ?? null;
				$source = $claim ? $claim->source : '—';
				$t      = $timing[ $r->event_id ] ?? null;
		?>
			<tr>
				<td><strong><?php echo esc_html( $r->event_id ); ?></strong></td>
				<td>
					<?php if ( $dupe ) : ?>
						<span class="mpc-badge mpc-badge-danger"><?php echo (int) $r->sends; ?> sends</span>
					<?php else : ?>
						<span class="mpc-badge mpc-badge-ok">1 send</span>
					<?php endif; ?>
				</td>
				<td><?php echo (int) $r->accepted; ?></td>
				<td style="font-size:.75rem; color: var(--mpc-text-dim);"><?php echo esc_html( $source ); ?></td>
				<td>
					<?php if ( null === $t ) : ?>
						<span style="color: var(--mpc-text-dim);">—</span>
					<?php elseif ( $t['lag_minutes'] <= 5 ) : ?>
						<span class="mpc-badge mpc-badge-ok"><?php echo (int) $t['lag_minutes']; ?> min</span>
					<?php elseif ( $t['lag_minutes'] <= 60 ) : ?>
						<span class="mpc-badge mpc-badge-warn"><?php echo (int) $t['lag_minutes']; ?> min</span>
					<?php else : ?>
						<span class="mpc-badge mpc-badge-danger"><?php echo esc_html( number_format_i18n( round( $t['lag_minutes'] / 60, 1 ), 1 ) ); ?> hrs</span>
					<?php endif; ?>
				</td>
				<td>
					<?php if ( null === $t ) : ?>
						<span style="color: var(--mpc-text-dim);">—</span>
					<?php elseif ( $t['pixel_printed'] ) : ?>
						<span class="mpc-badge mpc-badge-ok">Yes</span>
					<?php else : ?>
						<span class="mpc-badge mpc-badge-danger">No</span>
					<?php endif; ?>
				</td>
				<td style="font-size:.75rem;"><?php echo esc_html( $r->first_sent ); ?></td>
			</tr>
		<?php endforeach; endif;
		$html = ob_get_clean();

		$measured   = count( $timing );
		$no_pixel   = 0;
		$stale      = 0;
		foreach ( $timing as $t ) {
			if ( ! $t['pixel_printed'] ) $no_pixel++;
			if ( $t['lag_minutes'] > 60 ) $stale++;
		}

		wp_send_json_success( [
			'html'       => $html,
			'orders'     => $orders,
			'total'      => $total,
			'duplicated' => $duplicated,
			'days'       => $days,
			'measured'   => $measured,
			'no_pixel'   => $no_pixel,
			'stale'      => $stale,
		] );
	}

	/**
	 * Download a diagnostic export as JSON.
	 *
	 * Deliberately carries NO personal data and NO credentials: only per-order
	 * send counts, HTTP statuses, timestamps and configuration booleans. Event
	 * payloads (which hold hashed PII, IPs and user agents) and the access token
	 * are never included, so the file is safe to hand to a third party.
	 */
	/**
	 * Render the conflict scan: what else on this site sends events to Meta.
	 */
	public function ajax_conflict_scan() {
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();
		check_ajax_referer( 'mpc_save_settings', 'mpc_nonce' );

		$scan     = \Mpc\Diagnostics\ConflictScanner::scan();
		$warnings = [];
		$notes    = [];

		foreach ( $scan['plugins'] as $p ) {
			if ( $p['same_pixel'] ) {
				$warnings[] = sprintf(
					'<strong>%s</strong> is active and configured with the <strong>same Pixel ID</strong>. Both plugins are sending events to the same dataset — this double-counts every conversion.',
					esc_html( $p['name'] )
				);
			} elseif ( $p['known_sender'] ) {
				$warnings[] = sprintf(
					'<strong>%s</strong> is active. It sends Meta events%s. Confirm it is not pointed at the same dataset.',
					esc_html( $p['name'] ),
					! empty( $p['sends_capi'] ) ? ' <em>including server-side (CAPI)</em>' : ''
				);
			} else {
				$notes[] = sprintf( 'Possible tracking plugin active: <strong>%s</strong>.', esc_html( $p['name'] ) );
			}
		}

		$r = $scan['rendered'];
		if ( ! empty( $r['error'] ) ) {
			$notes[] = 'Could not fetch the site HTML to check for extra pixels: ' . esc_html( $r['error'] );
		}
		if ( ! empty( $r['extra_pixel_ids'] ) ) {
			$warnings[] = 'Extra Pixel ID(s) rendered on the site: <strong>' . esc_html( implode( ', ', $r['extra_pixel_ids'] ) ) . '</strong>. Something other than this plugin is putting a pixel on your pages.';
		}
		if ( ! empty( $r['duplicate_init_of_our_pixel'] ) ) {
			$warnings[] = 'Your Pixel ID is initialised <strong>more than once per page</strong>. Browser events are firing twice.';
		}
		if ( ! empty( $r['gtm_containers'] ) ) {
			$notes[] = 'Google Tag Manager container(s) detected: <strong>' . esc_html( implode( ', ', $r['gtm_containers'] ) ) . '</strong>. A GTM tag can send Meta events without appearing in the plugin list.';
		}

		$d = $scan['dataset'];
		if ( ! empty( $d['error'] ) ) {
			$notes[] = 'Dataset lookup: ' . esc_html( $d['error'] );
		} elseif ( ! empty( $d['queried'] ) ) {
			$notes[] = sprintf( 'Dataset <strong>%s</strong> (%s) reachable with the configured token.', esc_html( (string) $d['name'] ), esc_html( $scan['our_pixel_id'] ) );
		}
		$notes[] = esc_html( $d['note'] );

		wp_send_json_success( [
			'warnings' => $warnings,
			'notes'    => $notes,
			'clean'    => empty( $warnings ),
			'raw'      => $scan,
		] );
	}

	public function ajax_export_diagnostics() {
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden', '', [ 'response' => 403 ] );
		check_ajax_referer( 'mpc_save_settings', 'mpc_nonce' );

		global $wpdb;
		$days = isset( $_REQUEST['days'] ) ? max( 1, min( 90, (int) $_REQUEST['days'] ) ) : 30;

		// ── Purchase sends per order, from the event log ──
		$audit = $wpdb->get_results( $wpdb->prepare(
			"SELECT event_id,
			        COUNT(*) AS sends,
			        SUM(CASE WHEN CAST(status AS UNSIGNED) BETWEEN 200 AND 299 THEN 1 ELSE 0 END) AS accepted,
			        GROUP_CONCAT(status ORDER BY id) AS statuses,
			        MIN(created_at) AS first_sent,
			        MAX(created_at) AS last_sent
			 FROM {$wpdb->prefix}mpc_event_logs
			 WHERE event_name = 'Purchase' AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
			 GROUP BY event_id
			 ORDER BY MIN(created_at) ASC",
			$days
		), ARRAY_A );

		// ── Purchase sends per calendar day (send time, database timezone) ──
		$sends_by_day = $wpdb->get_results( $wpdb->prepare(
			"SELECT DATE(created_at) AS day, COUNT(*) AS sends, COUNT(DISTINCT event_id) AS distinct_orders
			 FROM {$wpdb->prefix}mpc_event_logs
			 WHERE event_name = 'Purchase' AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
			 GROUP BY DATE(created_at) ORDER BY day ASC",
			$days
		), ARRAY_A );

		// ── Real WooCommerce order counts per day, for comparison ──
		// Every status, not just processing/completed — stores with a COD or
		// courier workflow keep orders in custom statuses, and filtering to the
		// two core paid ones reported zero orders on days that plainly had them.
		$orders_by_day      = [];
		$paid_orders_by_day = [];
		$orders_by_status   = [];
		$orders_capped      = false;
		$orders_cap         = 2000;
		$paid_statuses      = [ 'processing', 'completed' ];
		if ( function_exists( 'wc_get_orders' ) ) {
			$order_ids = wc_get_orders( [
				'status'       => 'any',
				'date_created' => '>' . ( time() - $days * DAY_IN_SECONDS ),
				'limit'        => $orders_cap,
				'return'       => 'ids',
			] );
			$orders_capped = ( count( (array) $order_ids ) >= $orders_cap );
			foreach ( (array) $order_ids as $oid ) {
				$o = wc_get_order( $oid );
				if ( ! $o || ! $o->get_date_created() ) continue;
				$day    = $o->get_date_created()->date( 'Y-m-d' );
				$status = $o->get_status();

				$orders_by_day[ $day ]     = ( $orders_by_day[ $day ] ?? 0 ) + 1;
				$orders_by_status[ $status ] = ( $orders_by_status[ $status ] ?? 0 ) + 1;
				if ( in_array( $status, $paid_statuses, true ) ) {
					$paid_orders_by_day[ $day ] = ( $paid_orders_by_day[ $day ] ?? 0 ) + 1;
				}
			}
			ksort( $orders_by_day );
			ksort( $paid_orders_by_day );
			arsort( $orders_by_status );
		}

		// ── How stale is event_time when we send? ──
		//
		// Meta flags Purchase events whose event_time is far in the past. Since
		// 2.2.1 event_time is the order's creation time, so the staleness is
		// exactly the delay between the order being placed and the Purchase hook
		// firing. Measure it rather than guess at it: a large lag means Purchase
		// is firing on a later status transition, not at checkout.
		$send_lag = [];
		if ( function_exists( 'wc_get_order' ) ) {
			foreach ( array_slice( (array) $audit, -40 ) as $row ) {
				if ( ! preg_match( '/^order_(\d+)$/', (string) $row['event_id'], $m ) ) continue;
				$o = wc_get_order( (int) $m[1] );
				if ( ! $o || ! $o->get_date_created() ) continue;

				$created_utc = (int) $o->get_date_created()->getTimestamp();
				$sent_utc    = strtotime( $row['first_sent'] . ' UTC' );
				// first_sent is in the database's timezone; convert via its offset.
				$db_offset   = (int) $wpdb->get_var( 'SELECT TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), NOW())' );
				$sent_utc    = $sent_utc - $db_offset;

				$send_lag[] = [
					'order_id'          => (int) $m[1],
					'status'            => $o->get_status(),
					'order_created_utc' => gmdate( 'Y-m-d H:i:s', $created_utc ),
					'first_sent_utc'    => gmdate( 'Y-m-d H:i:s', $sent_utc ),
					'lag_minutes'       => (int) round( ( $sent_utc - $created_utc ) / 60 ),
				];
			}
		}

		// ── Claim table, when present ──
		$claims      = [];
		$claim_table = $wpdb->prefix . 'mpc_purchase_sent';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $claim_table ) ) === $claim_table ) {
			$claims = $wpdb->get_results(
				"SELECT order_id, source, attempts, delivered, claimed_at FROM {$claim_table} ORDER BY order_id DESC LIMIT 500",
				ARRAY_A
			);
		}

		$export = [
			'generated_at_utc' => gmdate( 'c' ),
			'window_days'      => $days,
			'environment'      => [
				'plugin_version' => MPC_VERSION,
				'wp_version'     => get_bloginfo( 'version' ),
				'wc_version'     => defined( 'WC_VERSION' ) ? WC_VERSION : null,
				'php_version'    => PHP_VERSION,
				'hpos_enabled'   => class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
					? \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()
					: null,
				'site_timezone'  => wp_timezone_string(),
				// The log's created_at uses the DATABASE clock, not the site's.
				// Both are reported so timestamps can be lined up with Meta's.
				'db_now'         => $wpdb->get_var( 'SELECT NOW()' ),
				'db_utc_now'     => $wpdb->get_var( 'SELECT UTC_TIMESTAMP()' ),
			],
			'settings' => [
				'purchase_event_enabled' => (bool) get_option( 'mpc_ev_purchase', 1 ),
				'purchase_status_filter' => (bool) get_option( 'mpc_purchase_status_filter', 1 ),
				'recovery_cron_enabled'  => (bool) get_option( 'mpc_enable_acr', 1 ),
				'filter_bots'            => (bool) get_option( 'mpc_filter_bots', 1 ),
				'consent_required'       => (bool) get_option( 'mpc_consent_required', 0 ),
				'consent_provider'       => get_option( 'mpc_consent_provider', 'none' ),
				'pixel_id_set'           => (bool) get_option( 'mpc_pixel_id' ),
				'capi_token_set'         => (bool) get_option( 'mpc_capi_token' ),
				'test_event_code_set'    => (bool) get_option( 'mpc_test_code' ),
			],
			'cron' => [
				'next_recovery_run'  => wp_next_scheduled( 'mpc_nightly_recovery_cron' ),
				'next_retry_run'     => wp_next_scheduled( 'mpc_retry_failed_events' ),
				'wp_cron_disabled'   => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			],
			'event_log' => [
				'total_rows'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}mpc_event_logs" ),
				'purchase_rows' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}mpc_event_logs WHERE event_name = 'Purchase'" ),
				'oldest_row'    => $wpdb->get_var( "SELECT MIN(created_at) FROM {$wpdb->prefix}mpc_event_logs" ),
				'newest_row'    => $wpdb->get_var( "SELECT MAX(created_at) FROM {$wpdb->prefix}mpc_event_logs" ),
				'retention_days'          => \Mpc\Tracker\LogPruner::retention_days(),
				'purchase_retention_days' => \Mpc\Tracker\LogPruner::PURCHASE_RETENTION_DAYS,
				'next_prune_run'          => wp_next_scheduled( 'mpc_prune_event_logs' ),
			],
			'purchase_sends_by_day' => $sends_by_day,
			'orders_by_day_all_statuses' => $orders_by_day,
			'orders_by_day_paid_only'    => $paid_orders_by_day,
			'orders_by_status'           => $orders_by_status,
			'orders_truncated'           => $orders_capped,
			'purchase_audit'        => $audit,
			'claims'                => $claims,
			'purchase_send_lag'     => $send_lag,
			'conflict_scan'         => \Mpc\Diagnostics\ConflictScanner::scan(),
		];

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=mpc-diagnostics-' . gmdate( 'Ymd-Hi' ) . '.json' );
		echo wp_json_encode( $export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit;
	}

	public function ajax_fetch_logs_html() {
		if ( ! current_user_can('manage_options') ) wp_send_json_error();
		check_ajax_referer( 'mpc_save_settings', 'mpc_nonce' );

		global $wpdb;
		$logs = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}mpc_event_logs ORDER BY id DESC LIMIT 50" );
		
		ob_start();
		if ( empty($logs) ) : ?>
			<tr><td colspan="6" style="text-align:center; color: var(--mpc-text-dim); padding: 30px;">No events logged yet. Events will appear here once your Pixel ID and CAPI Token are configured.</td></tr>
		<?php else :
			$type_labels = [ 'server' => 'Meta', 'ga4' => 'GA4', 'google_ads' => 'Google Ads', 'tiktok' => 'TikTok', 'pinterest' => 'Pinterest', 'snapchat' => 'Snapchat' ];
			foreach ( $logs as $log ) :
				$user_data_raw = json_decode( $log->payload ?? '{}', true );
				$ud = $user_data_raw['data'][0]['user_data'] ?? [];
				$fields = ['em'=>'Email','ph'=>'Phone','fn'=>'First Name','ln'=>'Last Name','ct'=>'City','st'=>'State','zp'=>'ZIP','country'=>'Country','fbp'=>'FBP','fbc'=>'FBC','external_id'=>'ExtID'];
				$type = $log->event_type;
				$type_label = $type_labels[ $type ] ?? ucfirst( (string) $type );
				$is_ok = ( (int) $log->status >= 200 && (int) $log->status < 300 );
		?>
		<tr data-type="<?php echo esc_attr( $type ); ?>">
			<td><strong><?php echo esc_html($log->event_name); ?></strong></td>
			<td style="font-size:.75rem; color: var(--mpc-text-dim); word-break: break-all; min-width:180px;"><?php echo esc_html( $log->event_id ); ?></td>
			<td><span class="mpc-badge mpc-badge-info"><?php echo esc_html( $type_label ); ?></span></td>
			<td>
				<?php if ( $is_ok ) : ?>
					<span class="mpc-badge mpc-badge-ok"><?php echo esc_html( $log->status ); ?> OK</span>
				<?php else : ?>
					<span class="mpc-badge mpc-badge-danger"><?php echo esc_html($log->status); ?></span>
				<?php endif; ?>
			</td>
			<td>
				<?php if ( $type === 'server' ) : ?>
				<div class="mpc-chip-row">
					<?php foreach ( $fields as $key => $label ) :
						if ( ! empty( $ud[$key] ) ) : ?>
							<span class="mpc-chip mpc-chip-ok">✓ <?php echo $label; ?></span>
						<?php else : ?>
							<span class="mpc-chip mpc-chip-miss">✗ <?php echo $label; ?></span>
						<?php endif;
					endforeach; ?>
				</div>
				<?php else : ?>
					<span style="font-size:.78rem; color: var(--mpc-text-dim);">—</span>
				<?php endif; ?>
			</td>
			<td style="font-size: .8rem; color: var(--mpc-text-dim); white-space: nowrap;"><?php echo esc_html($log->created_at); ?></td>
		</tr>
		<?php endforeach;
		endif;
		$html = ob_get_clean();
		wp_send_json_success( ['html' => $html] );
	}
}
