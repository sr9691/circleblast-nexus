/**
 * CircleBlast Nexus — Events JS
 * Handles RSVP button clicks via AJAX.
 */
(function(){
	document.addEventListener('click', function(e){
		var btn = e.target.closest('.cbnexus-rsvp-btn');
		if (!btn) return;
		e.preventDefault();

		var wrap = btn.closest('.cbnexus-rsvp-buttons');
		if (!wrap) return;

		var eventId = wrap.dataset.event;
		var status  = btn.dataset.rsvp;

		// Optimistic UI update.
		wrap.querySelectorAll('.cbnexus-rsvp-btn').forEach(function(b){
			b.classList.remove('cbnexus-btn-primary');
			b.classList.add('cbnexus-btn-outline');
		});
		btn.classList.remove('cbnexus-btn-outline');
		btn.classList.add('cbnexus-btn-primary');

		var fd = new FormData();
		fd.append('action', 'cbnexus_event_rsvp');
		fd.append('event_id', eventId);
		fd.append('rsvp_status', status);
		fd.append('nonce', window.cbnexus_events_nonce || '');

		fetch(window.cbnexus_ajax_url || '/wp-admin/admin-ajax.php', {
			method: 'POST', body: fd, credentials: 'same-origin'
		}).then(function(r){ return r.json(); }).then(function(data){
			if (data.success) {
				// Could update counts in UI if needed.
			}
		});
	});
})();
