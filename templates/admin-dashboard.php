<div class="mpc-wrap">
	<div class="mpc-header">
		<h1>Meta Pixel & CAPI Ultimate</h1>
		<p>Advanced WooCommerce tracking, deduplication, and conversion optimization.</p>
	</div>

	<form method="post" id="mpc-settings-form">
		<?php wp_nonce_field( 'mpc_save_settings', 'mpc_nonce' ); ?>

		<!-- Tab Navigation -->
		<div class="mpc-tabs">
			<button type="button" class="mpc-tab-btn active" data-tab="dashboard">Dashboard</button>
			<button type="button" class="mpc-tab-btn" data-tab="pixel">Pixel Settings</button>
			<button type="button" class="mpc-tab-btn" data-tab="capi">CAPI Settings</button>
			<button type="button" class="mpc-tab-btn" data-tab="events">Event Controls</button>
			<button type="button" class="mpc-tab-btn" data-tab="woo">WooCommerce Rules</button>
			<button type="button" class="mpc-tab-btn" data-tab="debug">Test & Debug</button>
			<button type="button" class="mpc-tab-btn" data-tab="logs">Event Logs</button>
		</div>

		<!-- ═══════════════════════════════════════════ -->
		<!-- TAB 1: DASHBOARD -->
		<!-- ═══════════════════════════════════════════ -->
		<div class="mpc-tab-panel active" data-panel="dashboard">
			<div class="mpc-card">
				<div class="mpc-card-head">
					<span class="dashicons dashicons-dashboard"></span>
					<h3>System Overview</h3>
				</div>
				<div class="mpc-card-body">
					<div class="mpc-status-grid">
						<div class="mpc-status-item">
							<div class="label">Pixel Status</div>
							<div class="value"><?php echo get_option('mpc_pixel_id') ? '<span class="mpc-badge mpc-badge-ok">Active</span>' : '<span class="mpc-badge mpc-badge-warn">Not Set</span>'; ?></div>
						</div>
						<div class="mpc-status-item">
							<div class="label">CAPI Status</div>
							<div class="value"><?php echo get_option('mpc_capi_token') ? '<span class="mpc-badge mpc-badge-ok">Active</span>' : '<span class="mpc-badge mpc-badge-warn">Not Set</span>'; ?></div>
						</div>
						<div class="mpc-status-item">
							<div class="label">Retry Queue</div>
							<div class="value"><span class="mpc-badge mpc-badge-info">Hourly</span></div>
						</div>
						<div class="mpc-status-item">
							<div class="label">Cart Recovery</div>
							<div class="value"><?php echo get_option('mpc_enable_abandoned_cart') ? '<span class="mpc-badge mpc-badge-ok">Active</span>' : '<span class="mpc-badge mpc-badge-warn">Disabled</span>'; ?></div>
						</div>
					</div>
				</div>
			</div>

			<div class="mpc-two-col">
				<div class="mpc-card">
					<div class="mpc-card-head">
						<span class="dashicons dashicons-rss"></span>
						<h3>Catalog Feed</h3>
					</div>
					<div class="mpc-card-body">
						<p style="color: var(--mpc-text-muted); margin-top: 0; font-size: .85rem;">Submit this URL to Facebook Commerce Manager.</p>
						<div class="mpc-copy-box" data-copy="<?php echo esc_url( home_url('/feed/fb-catalog') ); ?>">
							<?php echo esc_url( home_url('/feed/fb-catalog') ); ?>
							<span class="dashicons dashicons-admin-page"></span>
						</div>
					</div>
				</div>
				<div class="mpc-card">
					<div class="mpc-card-head">
						<span class="dashicons dashicons-info-outline"></span>
						<h3>Quick Info</h3>
					</div>
					<div class="mpc-card-body">
						<p style="color: var(--mpc-text-muted); margin: 0 0 8px; font-size: .85rem;">Plugin Version: <strong style="color:#fff;"><?php echo MPC_VERSION; ?></strong></p>
						<p style="color: var(--mpc-text-muted); margin: 0 0 8px; font-size: .85rem;">Graph API: <strong style="color:#fff;">v19.0</strong></p>
						<p style="color: var(--mpc-text-muted); margin: 0; font-size: .85rem;">Deduplication: <strong style="color: var(--mpc-success);">Active (MutationObserver)</strong></p>
					</div>
				</div>
			</div>
		</div>

		<!-- ═══════════════════════════════════════════ -->
		<!-- TAB 2: PIXEL SETTINGS -->
		<!-- ═══════════════════════════════════════════ -->
		<div class="mpc-tab-panel" data-panel="pixel">
			<div class="mpc-card">
				<div class="mpc-card-head">
					<span class="dashicons dashicons-admin-network"></span>
					<h3>Meta Pixel Configuration</h3>
				</div>
				<div class="mpc-card-body">
					<div class="mpc-field">
						<label for="mpc_pixel_id">Meta Pixel ID</label>
						<input type="text" id="mpc_pixel_id" name="mpc_pixel_id" value="<?php echo esc_attr( get_option('mpc_pixel_id') ); ?>" placeholder="e.g. 123456789012345" />
						<small>Find this in your Meta Events Manager → Data Sources → Your Pixel.</small>
					</div>
				</div>
			</div>

			<div class="mpc-card">
				<div class="mpc-card-head">
					<span class="dashicons dashicons-visibility"></span>
					<h3>Browser Behavioral Events</h3>
				</div>
				<div class="mpc-card-body">
					<div class="mpc-switch">
						<div class="mpc-switch-label">Scroll Depth Tracking<small>Fires at 50% and 90% scroll depth.</small></div>
						<label class="mpc-toggle-track"><input type="checkbox" name="mpc_enable_scroll" value="1" <?php checked(1, get_option('mpc_enable_scroll', 1)); ?>><span class="mpc-toggle-slider"></span></label>
					</div>
					<div class="mpc-switch">
						<div class="mpc-switch-label">Time on Page<small>Fires after 60 seconds on a page.</small></div>
						<label class="mpc-toggle-track"><input type="checkbox" name="mpc_enable_time_on_page" value="1" <?php checked(1, get_option('mpc_enable_time_on_page', 1)); ?>><span class="mpc-toggle-slider"></span></label>
					</div>
					<div class="mpc-switch">
						<div class="mpc-switch-label">Outbound Click Tracking<small>Fires when users click links leaving your domain.</small></div>
						<label class="mpc-toggle-track"><input type="checkbox" name="mpc_enable_outbound" value="1" <?php checked(1, get_option('mpc_enable_outbound', 1)); ?>><span class="mpc-toggle-slider"></span></label>
					</div>
				</div>
			</div>
		</div>

		<!-- ═══════════════════════════════════════════ -->
		<!-- TAB 3: CAPI SETTINGS -->
		<!-- ═══════════════════════════════════════════ -->
		<div class="mpc-tab-panel" data-panel="capi">
			<div class="mpc-card">
				<div class="mpc-card-head">
					<span class="dashicons dashicons-cloud"></span>
					<h3>Conversions API (Server-Side)</h3>
				</div>
				<div class="mpc-card-body">
					<div class="mpc-field">
						<label for="mpc_capi_token">Access Token</label>
						<textarea id="mpc_capi_token" name="mpc_capi_token" rows="4" placeholder="EAAI..."><?php echo esc_textarea( get_option('mpc_capi_token') ); ?></textarea>
						<small>Generate this in Meta Events Manager → Settings → Conversions API.</small>
						<button type="button" class="mpc-btn mpc-btn-sm mpc-btn-outline" id="mpc-test-token" style="margin-top: 10px;">Test Token Connection</button>
						<div id="mpc-token-test-result" style="margin-top: 10px; font-size: 0.85rem;"></div>
					</div>
					<div class="mpc-field">
						<label for="mpc_test_code">Test Event Code</label>
						<input type="text" id="mpc_test_code" name="mpc_test_code" value="<?php echo esc_attr( get_option('mpc_test_code') ); ?>" placeholder="TEST12345" />
						<small>Enter your Test Event Code from Meta Events Manager → Test Events tab. Remove after testing.</small>
					</div>
				</div>
			</div>
		</div>

		<!-- ═══════════════════════════════════════════ -->
		<!-- TAB 4: EVENT CONTROLS -->
		<!-- ═══════════════════════════════════════════ -->
		<div class="mpc-tab-panel" data-panel="events">
			<div class="mpc-two-col">
				<div class="mpc-card">
					<div class="mpc-card-head">
						<span class="dashicons dashicons-megaphone"></span>
						<h3>Standard Events</h3>
					</div>
					<div class="mpc-card-body">
						<?php
						$std_events = [
							['name' => 'mpc_ev_pageview',    'label' => 'PageView',          'desc' => 'Fires on every page load.', 'default' => 1],
							['name' => 'mpc_ev_viewcontent', 'label' => 'ViewContent',       'desc' => 'Fires on single product pages.', 'default' => 1],
							['name' => 'mpc_ev_viewcategory','label' => 'ViewCategory',      'desc' => 'Fires on category archive pages.', 'default' => 1],
							['name' => 'mpc_ev_viewcart',    'label' => 'ViewCart',           'desc' => 'Fires on the cart page.', 'default' => 1],
							['name' => 'mpc_ev_addtocart',   'label' => 'AddToCart',          'desc' => 'Fires when an item is added to cart.', 'default' => 1],
							['name' => 'mpc_ev_removecart',  'label' => 'RemoveFromCart',     'desc' => 'Fires when an item is removed from cart.', 'default' => 1],
						];
						foreach ( $std_events as $ev ) : ?>
						<div class="mpc-switch">
							<div class="mpc-switch-label"><?php echo $ev['label']; ?><small><?php echo $ev['desc']; ?></small></div>
							<label class="mpc-toggle-track"><input type="checkbox" name="<?php echo $ev['name']; ?>" value="1" <?php checked(1, get_option($ev['name'], $ev['default'])); ?>><span class="mpc-toggle-slider"></span></label>
						</div>
						<?php endforeach; ?>
					</div>
				</div>
				<div class="mpc-card">
					<div class="mpc-card-head">
						<span class="dashicons dashicons-cart"></span>
						<h3>Conversion Events</h3>
					</div>
					<div class="mpc-card-body">
						<?php
						$conv_events = [
							['name' => 'mpc_ev_checkout',  'label' => 'InitiateCheckout', 'desc' => 'Fires on the checkout page.', 'default' => 1],
							['name' => 'mpc_ev_purchase',  'label' => 'Purchase',          'desc' => 'Fires on order completion.', 'default' => 1],
						];
						foreach ( $conv_events as $ev ) : ?>
						<div class="mpc-switch">
							<div class="mpc-switch-label"><?php echo $ev['label']; ?><small><?php echo $ev['desc']; ?></small></div>
							<label class="mpc-toggle-track"><input type="checkbox" name="<?php echo $ev['name']; ?>" value="1" <?php checked(1, get_option($ev['name'], $ev['default'])); ?>><span class="mpc-toggle-slider"></span></label>
						</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		</div>

		<!-- ═══════════════════════════════════════════ -->
		<!-- TAB 5: WOOCOMMERCE RULES -->
		<!-- ═══════════════════════════════════════════ -->
		<div class="mpc-tab-panel" data-panel="woo">
			<div class="mpc-two-col">
				<div class="mpc-card">
					<div class="mpc-card-head">
						<span class="dashicons dashicons-filter"></span>
						<h3>Purchase Rules</h3>
					</div>
					<div class="mpc-card-body">
						<div class="mpc-switch">
							<div class="mpc-switch-label">Order Status Filtering<small>Only fire Purchase when order is Processing or Completed.</small></div>
							<label class="mpc-toggle-track"><input type="checkbox" name="mpc_purchase_status_filter" value="1" <?php checked(1, get_option('mpc_purchase_status_filter', 1)); ?>><span class="mpc-toggle-slider"></span></label>
						</div>
						<div class="mpc-switch">
							<div class="mpc-switch-label">Append Customer LTV Data<small>Attach lifetime_value, total_orders, and user_role to events.</small></div>
							<label class="mpc-toggle-track"><input type="checkbox" name="mpc_enable_ltv" value="1" <?php checked(1, get_option('mpc_enable_ltv', 1)); ?>><span class="mpc-toggle-slider"></span></label>
						</div>
						<div class="mpc-switch">
							<div class="mpc-switch-label">Automatic Conversion Recovery (ACR)<small>Nightly background job to push missed/failed orders to Meta.</small></div>
							<label class="mpc-toggle-track"><input type="checkbox" name="mpc_enable_acr" value="1" <?php checked(1, get_option('mpc_enable_acr', 1)); ?>><span class="mpc-toggle-slider"></span></label>
						</div>
					</div>
				</div>

				<div class="mpc-card">
					<div class="mpc-card-head">
						<span class="dashicons dashicons-email-alt"></span>
						<h3>Cart Recovery</h3>
					</div>
					<div class="mpc-card-body">
						<div class="mpc-switch">
							<div class="mpc-switch-label">Enable Email Recovery<small>Send recovery emails for abandoned carts.</small></div>
							<label class="mpc-toggle-track"><input type="checkbox" name="mpc_enable_abandoned_cart" value="1" <?php checked(1, get_option('mpc_enable_abandoned_cart', 0)); ?>><span class="mpc-toggle-slider"></span></label>
						</div>
						<div class="mpc-field">
							<label>Email Subject</label>
							<input type="text" name="mpc_recovery_subject" value="<?php echo esc_attr( get_option('mpc_recovery_subject', 'Complete your purchase') ); ?>">
						</div>
						<div class="mpc-field">
							<label>Email Message</label>
							<textarea name="mpc_recovery_message" rows="3"><?php echo esc_textarea( get_option('mpc_recovery_message', 'You left something in your cart! Click below to complete your order.') ); ?></textarea>
							<small>A "Complete Your Order" button is automatically attached.</small>
						</div>
					</div>
				</div>
			</div>

			<div class="mpc-card">
				<div class="mpc-card-head">
					<span class="dashicons dashicons-shield"></span>
					<h3>Order Protection (Blocklist)</h3>
				</div>
				<div class="mpc-card-body">
					<div class="mpc-two-col">
						<div class="mpc-field">
							<label>Blocked Phone Numbers</label>
							<textarea name="mpc_blocked_phones" rows="4" placeholder="01711..."><?php echo esc_textarea( get_option('mpc_blocked_phones') ); ?></textarea>
							<small>One per line. Orders from these numbers will be rejected.</small>
						</div>
						<div class="mpc-field">
							<label>Blocked Emails</label>
							<textarea name="mpc_blocked_emails" rows="4" placeholder="scammer@example.com"><?php echo esc_textarea( get_option('mpc_blocked_emails') ); ?></textarea>
							<small>One per line. Orders from these emails will be rejected.</small>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- ═══════════════════════════════════════════ -->
		<!-- TAB 6: TEST & DEBUG -->
		<!-- ═══════════════════════════════════════════ -->
		<div class="mpc-tab-panel" data-panel="debug">
			<div class="mpc-two-col">
				<div class="mpc-card">
					<div class="mpc-card-head">
						<span class="dashicons dashicons-hammer"></span>
						<h3>Diagnostics</h3>
					</div>
					<div class="mpc-card-body">
						<div class="mpc-switch">
							<div class="mpc-switch-label">WooCommerce<small>Required dependency.</small></div>
							<span class="mpc-badge <?php echo class_exists('WooCommerce') ? 'mpc-badge-ok' : 'mpc-badge-danger'; ?>"><?php echo class_exists('WooCommerce') ? 'Detected' : 'Missing'; ?></span>
						</div>
						<div class="mpc-switch">
							<div class="mpc-switch-label">Pixel ID<small>Required for browser events.</small></div>
							<span class="mpc-badge <?php echo get_option('mpc_pixel_id') ? 'mpc-badge-ok' : 'mpc-badge-warn'; ?>"><?php echo get_option('mpc_pixel_id') ? 'Set' : 'Not Set'; ?></span>
						</div>
						<div class="mpc-switch">
							<div class="mpc-switch-label">CAPI Token<small>Required for server events.</small></div>
							<span class="mpc-badge <?php echo get_option('mpc_capi_token') ? 'mpc-badge-ok' : 'mpc-badge-warn'; ?>"><?php echo get_option('mpc_capi_token') ? 'Set' : 'Not Set'; ?></span>
						</div>
						<div class="mpc-switch">
							<div class="mpc-switch-label">Test Mode<small>Test Event Code status.</small></div>
							<span class="mpc-badge <?php echo get_option('mpc_test_code') ? 'mpc-badge-info' : 'mpc-badge-ok'; ?>"><?php echo get_option('mpc_test_code') ? 'Active' : 'Off (Production)'; ?></span>
						</div>
					</div>
				</div>
				<div class="mpc-card">
					<div class="mpc-card-head">
						<span class="dashicons dashicons-update"></span>
						<h3>Maintenance</h3>
					</div>
					<div class="mpc-card-body">
						<p style="color: var(--mpc-text-muted); margin-top: 0; font-size: .85rem;">Manually trigger the failed-event retry queue or clear old logs.</p>
						<div style="display: flex; gap: 10px; flex-wrap: wrap;">
							<a href="<?php echo esc_url( admin_url('update-core.php?force-check=1') ); ?>" class="mpc-btn mpc-btn-sm mpc-btn-outline" style="text-decoration: none; display: flex; align-items: center;">Check for Plugin Updates</a>
							<button type="button" class="mpc-btn mpc-btn-sm mpc-btn-outline" id="mpc-retry-now">Retry Failed Events</button>
							<button type="button" class="mpc-btn mpc-btn-sm mpc-btn-outline mpc-btn-danger" id="mpc-clear-logs">Clear All Logs</button>
						</div>
						<div id="mpc-debug-msg" style="margin-top: 12px; font-size: .85rem; color: var(--mpc-success);"></div>
					</div>
				</div>
			</div>
		</div>

		<!-- ═══════════════════════════════════════════ -->
		<!-- TAB 7: EVENT LOGS -->
		<!-- ═══════════════════════════════════════════ -->
		<div class="mpc-tab-panel" data-panel="logs">
			<div class="mpc-card">
				<div class="mpc-card-head">
					<span class="dashicons dashicons-editor-table"></span>
					<h3>CAPI Event Log</h3>
				</div>
				<div class="mpc-card-body">
					<p style="color: var(--mpc-text-muted); margin-top: 0; font-size: .85rem;">Last 50 server-side events. Check the <strong>Data Sent</strong> column to verify what PII fields were hashed and included (this directly determines your Event Match Quality score).</p>
					<div class="mpc-table-wrap">
						<table class="mpc-table">
							<thead>
								<tr>
									<th>Event</th>
									<th>Event ID</th>
									<th>Source</th>
									<th>Status</th>
									<th>Data Sent (EMQ Indicators)</th>
									<th>Time</th>
								</tr>
							</thead>
							<tbody id="mpc-log-body">
								<tr><td colspan="6" style="text-align:center; color: var(--mpc-text-dim); padding: 30px;">Loading logs...</td></tr>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>

		<!-- ═══════════════════════════════════════════ -->
		<!-- STICKY SAVE BAR -->
		<!-- ═══════════════════════════════════════════ -->
		<div class="mpc-save-bar">
			<span class="mpc-save-msg" id="mpc-save-msg">✓ Settings saved successfully!</span>
			<button type="submit" class="mpc-btn" id="mpc-save-btn">Save Configuration</button>
		</div>
	</form>
</div>
