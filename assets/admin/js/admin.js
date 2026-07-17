jQuery(document).ready(function($) {

	// ── Tab Switching ──
	$('.mpc-tab-btn').on('click', function() {
		var tab = $(this).data('tab');
		$('.mpc-tab-btn').removeClass('active');
		$(this).addClass('active');
		$('.mpc-tab-panel').removeClass('active');
		$('.mpc-tab-panel[data-panel="' + tab + '"]').addClass('active');
	});

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
		$.post(ajaxurl, { action: 'mpc_retry_queue' }, function(res) {
			$msg.text(res.data ? res.data.message : 'Done!');
			$('#mpc-retry-now').text('Retry Failed Events').prop('disabled', false);
		});
	});

	// ── Clear Logs ──
	$('#mpc-clear-logs').on('click', function() {
		if (!confirm('Are you sure you want to delete all event logs?')) return;
		var $msg = $('#mpc-debug-msg');
		$(this).text('Clearing...').prop('disabled', true);
		$.post(ajaxurl, { action: 'mpc_clear_logs' }, function(res) {
			$msg.text(res.data ? res.data.message : 'Logs cleared!');
			$('#mpc-log-body').html('<tr><td colspan="5" style="text-align:center; color: #64748b; padding: 30px;">Logs cleared.</td></tr>');
			$('#mpc-clear-logs').text('Clear All Logs').prop('disabled', false);
		});
	});
});
