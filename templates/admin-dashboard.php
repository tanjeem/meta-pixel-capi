<div class="wrap mpc-dashboard-wrap">
	<div class="mpc-header">
		<h1>Meta Pixel & CAPI Ultimate</h1>
		<p>Advanced tracking, deduplication, and order protection.</p>
	</div>

	<form method="post" action="options.php" class="mpc-settings-form">
		<?php settings_fields( 'mpc_settings_group' ); ?>
		
		<div class="mpc-grid">
			<!-- Pixel & CAPI Settings -->
			<div class="mpc-card mpc-glass">
				<div class="mpc-card-header">
					<h2><span class="dashicons dashicons-admin-network"></span> Core Settings</h2>
				</div>
				<div class="mpc-card-body">
					<div class="mpc-input-group">
						<label for="mpc_pixel_id">Meta Pixel ID</label>
						<input type="text" id="mpc_pixel_id" name="mpc_pixel_id" value="<?php echo esc_attr( get_option('mpc_pixel_id') ); ?>" placeholder="e.g. 123456789012345" />
					</div>
					<div class="mpc-input-group">
						<label for="mpc_capi_token">Conversions API Token</label>
						<textarea id="mpc_capi_token" name="mpc_capi_token" rows="4" placeholder="EAAI..."><?php echo esc_textarea( get_option('mpc_capi_token') ); ?></textarea>
					</div>
					<div class="mpc-input-group">
						<label for="mpc_test_code">Test Event Code (Optional)</label>
						<input type="text" id="mpc_test_code" name="mpc_test_code" value="<?php echo esc_attr( get_option('mpc_test_code') ); ?>" placeholder="TEST4321" />
					</div>
				</div>
			</div>

			<!-- Cart Recovery -->
			<div class="mpc-card mpc-glass">
				<div class="mpc-card-header">
					<h2><span class="dashicons dashicons-email-alt"></span> Cart Recovery</h2>
				</div>
				<div class="mpc-card-body">
					<div class="mpc-input-group" style="flex-direction: row; align-items: center; gap: 10px;">
						<label class="mpc-toggle">
							<input type="checkbox" name="mpc_enable_abandoned_cart" value="1" <?php checked( 1, get_option( 'mpc_enable_abandoned_cart', 0 ) ); ?>>
							<span class="mpc-slider"></span>
						</label>
						<span style="font-weight: 500; color: #fff;">Enable Email Recovery</span>
					</div>
					<div class="mpc-input-group">
						<label>Email Subject</label>
						<input type="text" name="mpc_recovery_subject" value="<?php echo esc_attr( get_option( 'mpc_recovery_subject', 'Complete your purchase' ) ); ?>">
					</div>
					<div class="mpc-input-group">
						<label>Email Message</label>
						<textarea name="mpc_recovery_message" rows="4"><?php echo esc_textarea( get_option( 'mpc_recovery_message', 'You left something in your cart! Click below to complete your order.' ) ); ?></textarea>
						<small style="color: rgba(255,255,255,0.6); display: block; margin-top: 5px;">A "Complete Your Order" button will be attached below.</small>
					</div>
				</div>
			</div>

			<!-- Order Protection -->
			<div class="mpc-card mpc-glass">
				<div class="mpc-card-header">
					<h2><span class="dashicons dashicons-shield"></span> Order Protection (Blocklist)</h2>
				</div>
				<div class="mpc-card-body">
					<p class="mpc-desc">Prevent specific users from placing orders. Enter one per line.</p>
					<div class="mpc-input-group">
						<label for="mpc_blocked_phones">Blocked Phone Numbers</label>
						<textarea id="mpc_blocked_phones" name="mpc_blocked_phones" rows="4" placeholder="01711..."><?php echo esc_textarea( get_option('mpc_blocked_phones') ); ?></textarea>
					</div>
					<div class="mpc-input-group">
						<label for="mpc_blocked_emails">Blocked Emails</label>
						<textarea id="mpc_blocked_emails" name="mpc_blocked_emails" rows="4" placeholder="scammer@example.com"><?php echo esc_textarea( get_option('mpc_blocked_emails') ); ?></textarea>
					</div>
				</div>
			</div>
			
			<!-- Information & Logs -->
			<div class="mpc-card mpc-glass mpc-span-2">
				<div class="mpc-card-header">
					<h2><span class="dashicons dashicons-rss"></span> Integrations & Info</h2>
				</div>
				<div class="mpc-card-body mpc-info-grid">
					<div class="mpc-info-box">
						<h3>Catalog Feed URL</h3>
						<code class="mpc-copy" data-copy="<?php echo esc_url( home_url('/feed/fb-catalog') ); ?>">
							<?php echo esc_url( home_url('/feed/fb-catalog') ); ?>
							<span class="dashicons dashicons-admin-page"></span>
						</code>
						<p>Submit this URL to Facebook Commerce Manager.</p>
					</div>
					<div class="mpc-info-box">
						<h3>System Status</h3>
						<ul class="mpc-status-list">
							<li>Pixel Injected: <strong><?php echo get_option('mpc_pixel_id') ? '<span class="status-ok">Active</span>' : '<span class="status-warn">Missing</span>'; ?></strong></li>
							<li>CAPI Configured: <strong><?php echo get_option('mpc_capi_token') ? '<span class="status-ok">Active</span>' : '<span class="status-warn">Missing</span>'; ?></strong></li>
							<li>Retry Queue: <strong>Active (Hourly)</strong></li>
							<li>Cart Recovery: <strong><?php echo get_option('mpc_enable_abandoned_cart') ? '<span class="status-ok">Active</span>' : '<span class="status-warn">Disabled</span>'; ?></strong></li>
						</ul>
					</div>
				</div>
			</div>
		</div>

		<div class="mpc-actions">
			<?php submit_button( 'Save Configuration', 'mpc-btn mpc-btn-primary' ); ?>
		</div>
	</form>
</div>
