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
			<button type="button" class="mpc-tab-btn" data-tab="integrations">Integrations</button>
			<button type="button" class="mpc-tab-btn" data-tab="consent">Consent & Privacy</button>
			<button type="button" class="mpc-tab-btn" data-tab="woo">WooCommerce Rules</button>
			<button type="button" class="mpc-tab-btn" data-tab="debug">Test & Debug</button>
			<button type="button" class="mpc-tab-btn" data-tab="logs">Event Logs</button>
		</div>

		<!-- ═══════════════════════════════════════════ -->
		<!-- TAB 1: DASHBOARD -->
		<!-- ═══════════════════════════════════════════ -->
		<div class="mpc-tab-panel active" data-panel="dashboard">
			<?php
			// ── Compute setup health + channel status ──
			$pixel_set   = (bool) get_option( 'mpc_pixel_id' );
			$capi_set    = (bool) get_option( 'mpc_capi_token' );
			$purchase_on = (bool) get_option( 'mpc_ev_purchase', 1 );

			$channels_status = [
				[ 'name' => 'Meta',       'on' => $pixel_set,                                                                'browser' => $pixel_set, 'server' => $capi_set ],
				[ 'name' => 'GA4',        'on' => get_option('mpc_ga4_enabled') && get_option('mpc_ga4_measurement_id'),      'browser' => true, 'server' => (bool) get_option('mpc_ga4_api_secret') ],
				[ 'name' => 'Google Ads', 'on' => get_option('mpc_google_ads_enabled') && get_option('mpc_google_ads_conversion_id'), 'browser' => true, 'server' => false ],
				[ 'name' => 'TikTok',     'on' => get_option('mpc_tiktok_enabled') && get_option('mpc_tiktok_pixel_code'),     'browser' => true, 'server' => (bool) get_option('mpc_tiktok_access_token') ],
				[ 'name' => 'Pinterest',  'on' => get_option('mpc_pinterest_enabled') && get_option('mpc_pinterest_tag_id'),   'browser' => true, 'server' => (bool) ( get_option('mpc_pinterest_ad_account_id') && get_option('mpc_pinterest_access_token') ) ],
				[ 'name' => 'Snapchat',   'on' => get_option('mpc_snapchat_enabled') && get_option('mpc_snapchat_pixel_id'),   'browser' => true, 'server' => (bool) get_option('mpc_snapchat_access_token') ],
			];
			$active_platforms = count( array_filter( $channels_status, function ( $c ) { return $c['on']; } ) );

			$checks = [
				[ 'done' => $pixel_set,             'title' => 'Meta Pixel ID configured',        'desc' => 'The foundation for all Meta browser tracking.' ],
				[ 'done' => $capi_set,              'title' => 'Meta Conversions API connected',  'desc' => 'Server-side tracking that beats ad blockers & iOS.' ],
				[ 'done' => $purchase_on,           'title' => 'Purchase event enabled',           'desc' => 'Your most important conversion signal.' ],
				[ 'done' => $active_platforms > 1,  'title' => 'Additional platform connected',    'desc' => 'GA4, Google Ads, TikTok, Pinterest or Snapchat.' ],
			];
			$done  = count( array_filter( $checks, function ( $c ) { return $c['done']; } ) );
			$score = (int) round( $done / count( $checks ) * 100 );
			$score_color = $score >= 75 ? 'var(--mpc-success-text)' : ( $score >= 40 ? 'var(--mpc-warning-text)' : 'var(--mpc-danger-text)' );
			?>

			<div class="mpc-overview">
				<div class="mpc-health">
					<div class="mpc-ring" style="--val: <?php echo esc_attr( $score ); ?>; --mpc-score-color: <?php echo esc_attr( $score_color ); ?>;">
						<div class="mpc-ring-inner">
							<b><?php echo esc_html( $score ); ?>%</b>
							<span>Setup</span>
						</div>
					</div>
					<h3>Tracking Health</h3>
					<p><?php echo esc_html( $active_platforms ); ?> platform<?php echo $active_platforms === 1 ? '' : 's'; ?> active</p>
				</div>

				<div class="mpc-card" style="margin-bottom:0;">
					<div class="mpc-card-head">
						<span class="dashicons dashicons-yes-alt"></span>
						<h3>Setup Checklist</h3>
					</div>
					<div class="mpc-card-body">
						<ul class="mpc-checklist">
							<?php foreach ( $checks as $c ) : ?>
							<li class="mpc-check-item">
								<span class="mpc-check-mark <?php echo $c['done'] ? 'done' : 'todo'; ?>"><?php echo $c['done'] ? '✓' : '!'; ?></span>
								<span class="mpc-check-body">
									<strong><?php echo esc_html( $c['title'] ); ?></strong>
									<small><?php echo esc_html( $c['desc'] ); ?></small>
								</span>
							</li>
							<?php endforeach; ?>
						</ul>
					</div>
				</div>
			</div>

			<div class="mpc-card">
				<div class="mpc-card-head">
					<span class="dashicons dashicons-networking"></span>
					<h3>Platform Status</h3>
				</div>
				<div class="mpc-card-body">
					<div class="mpc-channel-grid">
						<?php foreach ( $channels_status as $ch ) : ?>
						<div class="mpc-channel <?php echo $ch['on'] ? 'on' : ''; ?>">
							<div class="mpc-channel-top">
								<span class="mpc-channel-name"><?php echo esc_html( $ch['name'] ); ?></span>
								<span class="mpc-dot <?php echo $ch['on'] ? 'live' : ''; ?>"></span>
							</div>
							<div class="mpc-channel-meta">
								<?php if ( $ch['on'] ) : ?>
									<span class="mpc-tag <?php echo $ch['browser'] ? 'active' : ''; ?>">Browser</span>
									<span class="mpc-tag <?php echo $ch['server'] ? 'active' : ''; ?>">Server</span>
								<?php else : ?>
									<span class="mpc-tag">Off</span>
								<?php endif; ?>
							</div>
						</div>
						<?php endforeach; ?>
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
						<p style="color: var(--mpc-text-muted); margin: 0 0 8px; font-size: .85rem;">Plugin Version: <strong style="color: var(--mpc-text);"><?php echo esc_html( MPC_VERSION ); ?></strong></p>
						<p style="color: var(--mpc-text-muted); margin: 0 0 8px; font-size: .85rem;">Graph API: <strong style="color: var(--mpc-text);"><?php echo esc_html( MPC_GRAPH_VERSION ); ?></strong></p>
						<p style="color: var(--mpc-text-muted); margin: 0; font-size: .85rem;">Deduplication: <strong style="color: var(--mpc-success-text);">Active (MutationObserver)</strong></p>
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
		<!-- TAB: INTEGRATIONS (multi-platform channels) -->
		<!-- ═══════════════════════════════════════════ -->
		<div class="mpc-tab-panel" data-panel="integrations">
			<div class="mpc-card">
				<div class="mpc-card-head">
					<span class="dashicons dashicons-chart-line"></span>
					<h3>Google Analytics 4</h3>
				</div>
				<div class="mpc-card-body">
					<div class="mpc-switch">
						<div class="mpc-switch-label">Enable GA4 Tracking<small>Fires GA4 events in the browser (gtag) and server-side (Measurement Protocol).</small></div>
						<label class="mpc-toggle-track"><input type="checkbox" name="mpc_ga4_enabled" value="1" <?php checked(1, get_option('mpc_ga4_enabled', 0)); ?>><span class="mpc-toggle-slider"></span></label>
					</div>
					<div class="mpc-field">
						<label for="mpc_ga4_measurement_id">Measurement ID</label>
						<input type="text" id="mpc_ga4_measurement_id" name="mpc_ga4_measurement_id" value="<?php echo esc_attr( get_option('mpc_ga4_measurement_id') ); ?>" placeholder="G-XXXXXXXXXX" />
						<small>Found in GA4 → Admin → Data Streams → your web stream.</small>
					</div>
					<div class="mpc-field">
						<label for="mpc_ga4_api_secret">Measurement Protocol API Secret <span style="opacity:.6;">(optional — enables server-side)</span></label>
						<input type="text" id="mpc_ga4_api_secret" name="mpc_ga4_api_secret" value="<?php echo esc_attr( get_option('mpc_ga4_api_secret') ); ?>" placeholder="xxxxxxxx" />
						<small>GA4 → Admin → Data Streams → Measurement Protocol API secrets. Leave blank to track browser-only.</small>
					</div>
				</div>
			</div>

			<div class="mpc-card">
				<div class="mpc-card-head">
					<span class="dashicons dashicons-money-alt"></span>
					<h3>Google Ads</h3>
				</div>
				<div class="mpc-card-body">
					<div class="mpc-switch">
						<div class="mpc-switch-label">Enable Google Ads Conversion<small>Fires a Google Ads conversion on Purchase (browser).</small></div>
						<label class="mpc-toggle-track"><input type="checkbox" name="mpc_google_ads_enabled" value="1" <?php checked(1, get_option('mpc_google_ads_enabled', 0)); ?>><span class="mpc-toggle-slider"></span></label>
					</div>
					<div class="mpc-field">
						<label for="mpc_google_ads_conversion_id">Conversion ID</label>
						<input type="text" id="mpc_google_ads_conversion_id" name="mpc_google_ads_conversion_id" value="<?php echo esc_attr( get_option('mpc_google_ads_conversion_id') ); ?>" placeholder="AW-XXXXXXXXX" />
						<small>Your Google Ads conversion account id (starts with AW-).</small>
					</div>
					<div class="mpc-field">
						<label for="mpc_google_ads_purchase_label">Purchase Conversion Label</label>
						<input type="text" id="mpc_google_ads_purchase_label" name="mpc_google_ads_purchase_label" value="<?php echo esc_attr( get_option('mpc_google_ads_purchase_label') ); ?>" placeholder="abCdEfGhIj" />
						<small>The label from your Purchase conversion action in Google Ads.</small>
					</div>
				</div>
			</div>

			<div class="mpc-card">
				<div class="mpc-card-head">
					<span class="dashicons dashicons-video-alt3"></span>
					<h3>TikTok</h3>
				</div>
				<div class="mpc-card-body">
					<div class="mpc-switch">
						<div class="mpc-switch-label">Enable TikTok Tracking<small>TikTok Pixel (browser) + Events API (server-side).</small></div>
						<label class="mpc-toggle-track"><input type="checkbox" name="mpc_tiktok_enabled" value="1" <?php checked(1, get_option('mpc_tiktok_enabled', 0)); ?>><span class="mpc-toggle-slider"></span></label>
					</div>
					<div class="mpc-field">
						<label for="mpc_tiktok_pixel_code">Pixel Code</label>
						<input type="text" id="mpc_tiktok_pixel_code" name="mpc_tiktok_pixel_code" value="<?php echo esc_attr( get_option('mpc_tiktok_pixel_code') ); ?>" placeholder="e.g. CABCD1EFGH2IJK3LMN" />
						<small>TikTok Events Manager → your pixel → Pixel Code.</small>
					</div>
					<div class="mpc-field">
						<label for="mpc_tiktok_access_token">Events API Access Token <span style="opacity:.6;">(optional — enables server-side)</span></label>
						<input type="text" id="mpc_tiktok_access_token" name="mpc_tiktok_access_token" value="<?php echo esc_attr( get_option('mpc_tiktok_access_token') ); ?>" placeholder="TikTok Events API token" />
						<small>Generate under Events API → Generate Access Token. Leave blank to track browser-only.</small>
					</div>
				</div>
			</div>

			<div class="mpc-card">
				<div class="mpc-card-head">
					<span class="dashicons dashicons-pinterest"></span>
					<h3>Pinterest</h3>
				</div>
				<div class="mpc-card-body">
					<div class="mpc-switch">
						<div class="mpc-switch-label">Enable Pinterest Tracking<small>Pinterest Tag (browser) + Conversions API (server-side).</small></div>
						<label class="mpc-toggle-track"><input type="checkbox" name="mpc_pinterest_enabled" value="1" <?php checked(1, get_option('mpc_pinterest_enabled', 0)); ?>><span class="mpc-toggle-slider"></span></label>
					</div>
					<div class="mpc-field">
						<label for="mpc_pinterest_tag_id">Tag ID</label>
						<input type="text" id="mpc_pinterest_tag_id" name="mpc_pinterest_tag_id" value="<?php echo esc_attr( get_option('mpc_pinterest_tag_id') ); ?>" placeholder="e.g. 2612345678901" />
						<small>Pinterest Ads → Conversions → Tag Manager.</small>
					</div>
					<div class="mpc-field">
						<label for="mpc_pinterest_ad_account_id">Ad Account ID <span style="opacity:.6;">(optional — for server-side)</span></label>
						<input type="text" id="mpc_pinterest_ad_account_id" name="mpc_pinterest_ad_account_id" value="<?php echo esc_attr( get_option('mpc_pinterest_ad_account_id') ); ?>" placeholder="Ad account id" />
					</div>
					<div class="mpc-field">
						<label for="mpc_pinterest_access_token">Conversions API Token <span style="opacity:.6;">(optional)</span></label>
						<input type="text" id="mpc_pinterest_access_token" name="mpc_pinterest_access_token" value="<?php echo esc_attr( get_option('mpc_pinterest_access_token') ); ?>" placeholder="Pinterest CAPI token" />
						<small>Both Ad Account ID and Token are required for server-side events.</small>
					</div>
				</div>
			</div>

			<div class="mpc-card">
				<div class="mpc-card-head">
					<span class="dashicons dashicons-format-chat"></span>
					<h3>Snapchat</h3>
				</div>
				<div class="mpc-card-body">
					<div class="mpc-switch">
						<div class="mpc-switch-label">Enable Snapchat Tracking<small>Snap Pixel (browser) + Conversions API (server-side).</small></div>
						<label class="mpc-toggle-track"><input type="checkbox" name="mpc_snapchat_enabled" value="1" <?php checked(1, get_option('mpc_snapchat_enabled', 0)); ?>><span class="mpc-toggle-slider"></span></label>
					</div>
					<div class="mpc-field">
						<label for="mpc_snapchat_pixel_id">Pixel ID</label>
						<input type="text" id="mpc_snapchat_pixel_id" name="mpc_snapchat_pixel_id" value="<?php echo esc_attr( get_option('mpc_snapchat_pixel_id') ); ?>" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" />
						<small>Snapchat Ads → Events Manager → Pixel.</small>
					</div>
					<div class="mpc-field">
						<label for="mpc_snapchat_access_token">Conversions API Token <span style="opacity:.6;">(optional — enables server-side)</span></label>
						<input type="text" id="mpc_snapchat_access_token" name="mpc_snapchat_access_token" value="<?php echo esc_attr( get_option('mpc_snapchat_access_token') ); ?>" placeholder="Snapchat CAPI token" />
						<small>Leave blank to track browser-only.</small>
					</div>
				</div>
			</div>
		</div>

		<!-- ═══════════════════════════════════════════ -->
		<!-- TAB: CONSENT & PRIVACY -->
		<!-- ═══════════════════════════════════════════ -->
		<div class="mpc-tab-panel" data-panel="consent">
			<?php $consent_provider = get_option( 'mpc_consent_provider', 'none' ); ?>
			<div class="mpc-card">
				<div class="mpc-card-head">
					<span class="dashicons dashicons-shield"></span>
					<h3>Consent Gating (GDPR)</h3>
				</div>
				<div class="mpc-card-body">
					<div class="mpc-switch">
						<div class="mpc-switch-label">Require Consent Before Tracking<small>When on, no browser pixel or server event fires until the visitor grants consent.</small></div>
						<label class="mpc-toggle-track"><input type="checkbox" name="mpc_consent_required" value="1" <?php checked(1, get_option('mpc_consent_required', 0)); ?>><span class="mpc-toggle-slider"></span></label>
					</div>
					<div class="mpc-switch">
						<div class="mpc-switch-label">Google Consent Mode v2<small>Emit Google's consent signals (default denied → update on grant) so Google's modelling works before consent.</small></div>
						<label class="mpc-toggle-track"><input type="checkbox" name="mpc_consent_mode_v2" value="1" <?php checked(1, get_option('mpc_consent_mode_v2', 0)); ?>><span class="mpc-toggle-slider"></span></label>
					</div>

					<div class="mpc-section-label" style="margin-top:20px;">Consent Source</div>
					<div class="mpc-radio-group">
						<label class="mpc-radio">
							<input type="radio" name="mpc_consent_provider" value="none" <?php checked('none', $consent_provider); ?>>
							<span class="mpc-radio-text"><strong>None</strong><small>No gating — track all visitors (legacy behaviour).</small></span>
						</label>
						<label class="mpc-radio">
							<input type="radio" name="mpc_consent_provider" value="wp_consent_api" <?php checked('wp_consent_api', $consent_provider); ?>>
							<span class="mpc-radio-text"><strong>WP Consent API (recommended)</strong><small>Auto-integrates with CookieYes, Complianz, Borlabs, Cookiebot and other Consent-API-compatible plugins.</small></span>
						</label>
						<label class="mpc-radio">
							<input type="radio" name="mpc_consent_provider" value="cookie" <?php checked('cookie', $consent_provider); ?>>
							<span class="mpc-radio-text"><strong>Custom Cookie</strong><small>Grant consent when a specific cookie is present / matches a value.</small></span>
						</label>
					</div>
				</div>
			</div>

			<div class="mpc-card">
				<div class="mpc-card-head">
					<span class="dashicons dashicons-admin-network"></span>
					<h3>Custom Cookie Rule</h3>
				</div>
				<div class="mpc-card-body">
					<p style="color: var(--mpc-text-muted); margin-top: 0; font-size: .85rem;">Only used when Consent Source is set to “Custom Cookie”.</p>
					<div class="mpc-field">
						<label for="mpc_consent_cookie_name">Cookie Name</label>
						<input type="text" id="mpc_consent_cookie_name" name="mpc_consent_cookie_name" value="<?php echo esc_attr( get_option('mpc_consent_cookie_name') ); ?>" placeholder="e.g. marketing_consent" />
					</div>
					<div class="mpc-field">
						<label for="mpc_consent_cookie_value">Required Value <span style="opacity:.6;">(leave blank = any value)</span></label>
						<input type="text" id="mpc_consent_cookie_value" name="mpc_consent_cookie_value" value="<?php echo esc_attr( get_option('mpc_consent_cookie_value') ); ?>" placeholder="e.g. yes" />
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
					<p style="color: var(--mpc-text-muted); margin-top: 0; font-size: .85rem;">Last 50 server-side events across all platforms. Check the <strong>Data Sent</strong> column to verify what PII fields were hashed and included (this directly determines your Meta Event Match Quality score).</p>
					<div class="mpc-log-toolbar">
						<button type="button" class="mpc-filter-pill active" data-filter="all">All</button>
						<button type="button" class="mpc-filter-pill" data-filter="server">Meta</button>
						<button type="button" class="mpc-filter-pill" data-filter="ga4">GA4</button>
						<button type="button" class="mpc-filter-pill" data-filter="tiktok">TikTok</button>
						<button type="button" class="mpc-filter-pill" data-filter="pinterest">Pinterest</button>
						<button type="button" class="mpc-filter-pill" data-filter="snapchat">Snapchat</button>
					</div>
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
