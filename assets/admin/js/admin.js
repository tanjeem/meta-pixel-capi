jQuery(document).ready(function($) {
	// Copy to clipboard functionality
	$('.mpc-copy').on('click', function() {
		var textToCopy = $(this).data('copy');
		var $icon = $(this).find('.dashicons');
		
		navigator.clipboard.writeText(textToCopy).then(function() {
			// Success
			$icon.removeClass('dashicons-admin-page').addClass('dashicons-yes-alt').css('color', '#10b981');
			setTimeout(function() {
				$icon.removeClass('dashicons-yes-alt').addClass('dashicons-admin-page').css('color', '');
			}, 2000);
		}).catch(function(err) {
			console.error('Could not copy text: ', err);
		});
	});

	// Add dynamic ripple effect to buttons
	$('.mpc-btn').on('mousedown', function(e) {
		var $btn = $(this);
		var $ripple = $('<span class="mpc-ripple"></span>');
		
		var x = e.pageX - $btn.offset().left;
		var y = e.pageY - $btn.offset().top;
		
		$ripple.css({
			top: y + 'px',
			left: x + 'px',
			position: 'absolute',
			background: 'rgba(255,255,255,0.3)',
			borderRadius: '50%',
			width: '0',
			height: '0',
			transform: 'translate(-50%, -50%)',
			animation: 'rippleEffect 0.6s linear'
		});
		
		$btn.css({ position: 'relative', overflow: 'hidden' });
		$btn.append($ripple);
		
		setTimeout(function() {
			$ripple.remove();
		}, 600);
	});

	// Append keyframes for ripple if not exists
	if ($('#mpc-ripple-style').length === 0) {
		$('head').append(`
			<style id="mpc-ripple-style">
				@keyframes rippleEffect {
					to {
						width: 300px;
						height: 300px;
						opacity: 0;
					}
				}
			</style>
		`);
	}
});
