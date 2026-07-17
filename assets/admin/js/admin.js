jQuery(document).ready(function($) {

	// ── Tab Switching ──
	$('.mpc-tab-btn').on('click', function() {
		var tab = $(this).data('tab');
		$('.mpc-tab-btn').removeClass('active');
		$(this).addClass('active');
		$('.mpc-tab-panel').removeClass('active');
		$('.mpc-tab-panel[data-panel="' + tab + '"]').addClass('active');
		
		if (tab === 'logs') {
			mpc_fetch_logs();
		}
	});

	// ── Log filtering by platform ──
	var mpcLogFilter = 'all';
	function mpc_apply_log_filter() {
		$('#mpc-log-body tr').each(function() {
			var t = $(this).data('type');
			if (mpcLogFilter === 'all' || t === undefined || t === mpcLogFilter) {
				$(this).show();
			} else {
				$(this).hide();
			}
		});
	}
	$(document).on('click', '.mpc-filter-pill', function() {
		$('.mpc-filter-pill').removeClass('active');
		$(this).addClass('active');
		mpcLogFilter = $(this).data('filter');
		mpc_apply_log_filter();
	});

	// ── Auto-Refresh Event Logs ──
	function mpc_fetch_logs() {
		$.post(ajaxurl, {
			action: 'mpc_fetch_logs_html',
			mpc_nonce: $('#mpc-settings-form input[name="mpc_nonce"]').val()
		}, function(res) {
			if (res.success && res.data.html) {
				$('#mpc-log-body').html(res.data.html);
				mpc_apply_log_filter();
			}
		});
	}
	
	// Initial fetch and poll every 10 seconds
	mpc_fetch_logs();
	setInterval(function() {
		if ($('.mpc-tab-panel[data-panel="logs"]').hasClass('active')) {
			mpc_fetch_logs();
		}
	}, 10000);

	// ── AJAX Save ──
	$('#mpc-settings-form').on('submit', function(e) {
		e.preventDefault();
		var $btn = $('#mpc-save-btn');
		var $msg = $('#mpc-save-msg');
		$btn.text('Saving...').prop('disabled', true);

		$.ajax({
			url: ajaxurl,
			method: 'POST',
			data: $(this).serialize() + '&action=mpc_save_settings',
			success: function(res) {
				if (res.success) {
					$msg.addClass('visible').text('✓ ' + res.data.message);
					setTimeout(function() { $msg.removeClass('visible'); }, 3000);
				} else {
					$msg.addClass('visible').css('color', '#ef4444').text('✗ Save failed.');
					setTimeout(function() { $msg.removeClass('visible').css('color', ''); }, 3000);
				}
			},
			error: function() {
				$msg.addClass('visible').css('color', '#ef4444').text('✗ Network error.');
				setTimeout(function() { $msg.removeClass('visible').css('color', ''); }, 3000);
			},
			complete: function() {
				$btn.text('Save Configuration').prop('disabled', false);
			}
		});
	});

	// ── Copy to Clipboard ──
	$('.mpc-copy-box').on('click', function() {
		var text = $(this).data('copy');
		var $icon = $(this).find('.dashicons');
		navigator.clipboard.writeText(text).then(function() {
			$icon.removeClass('dashicons-admin-page').addClass('dashicons-yes-alt').css('color', '#22c55e');
			setTimeout(function() {
				$icon.removeClass('dashicons-yes-alt').addClass('dashicons-admin-page').css('color', '');
			}, 2000);
		});
	});

	// ── Retry Queue ──
	$('#mpc-retry-now').on('click', function() {
		var $msg = $('#mpc-debug-msg');
		$(this).text('Retrying...').prop('disabled', true);
		$.post(ajaxurl, {
			action: 'mpc_retry_queue',
			mpc_nonce: $('#mpc-settings-form input[name="mpc_nonce"]').val()
		}, function(res) {
			$msg.text(res.data ? res.data.message : 'Done!');
			$('#mpc-retry-now').text('Retry Failed Events').prop('disabled', false);
		});
	});

	// ── Token Test ──
	$('#mpc-test-token').on('click', function() {
		var $btn = $(this);
		var $res = $('#mpc-token-test-result');
		var token = $('#mpc_capi_token').val();
		var pixel_id = $('#mpc_pixel_id').val();
		
		if (!token || !pixel_id) {
			$res.css('color', '#ef4444').text('✗ Please enter both Pixel ID and Access Token first.');
			return;
		}

		$btn.text('Testing...').prop('disabled', true);
		$res.text('').css('color', '');

		$.post(ajaxurl, {
			action: 'mpc_test_token',
			mpc_nonce: $('#mpc-settings-form input[name="mpc_nonce"]').val(),
			pixel_id: pixel_id,
			token: token
		}, function(res) {
			$btn.text('Test Token Connection').prop('disabled', false);
			if (res.success) {
				$res.css('color', '#22c55e').html('✓ <strong>Success:</strong> Connected to pixel "' + res.data.name + '" (Created: ' + res.data.creation_time + ')');
			} else {
				$res.css('color', '#ef4444').html('✗ <strong>Error:</strong> ' + res.data.message);
			}
		}).fail(function() {
			$btn.text('Test Token Connection').prop('disabled', false);
			$res.css('color', '#ef4444').text('✗ Network error.');
		});
	});

	// ── Clear Logs ──
	$('#mpc-clear-logs').on('click', function() {
		if (!confirm('Are you sure you want to delete all event logs?')) return;
		var $msg = $('#mpc-debug-msg');
		$(this).text('Clearing...').prop('disabled', true);
		$.post(ajaxurl, {
			action: 'mpc_clear_logs',
			mpc_nonce: $('#mpc-settings-form input[name="mpc_nonce"]').val()
		}, function(res) {
			$msg.text(res.data ? res.data.message : 'Logs cleared!');
			$('#mpc-log-body').html('<tr><td colspan="5" style="text-align:center; color: #64748b; padding: 30px;">Logs cleared.</td></tr>');
			$('#mpc-clear-logs').text('Clear All Logs').prop('disabled', false);
		});
	});
});
