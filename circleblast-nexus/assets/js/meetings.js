/**
 * CircleBlast Nexus – Meetings JS
 * ITER-0009: AJAX handlers for meeting actions and notes submission.
 */
(function () {
	'use strict';

	// Action buttons (accept, decline, schedule, complete, cancel).
	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.cbnexus-action-btn');
		if (!btn) return;

		e.preventDefault();
		var action = btn.getAttribute('data-action');
		var meetingId = btn.getAttribute('data-meeting-id');
		if (!action || !meetingId) return;

		var data = new FormData();
		data.append('action', 'cbnexus_' + action);
		data.append('nonce', cbnexusMtg.nonce);
		data.append('meeting_id', meetingId);

		// Response type for accept/decline.
		var response = btn.getAttribute('data-response');
		if (response) data.append('response', response);

		// Schedule date.
		if (action === 'schedule_meeting') {
			var input = document.querySelector('.cbnexus-schedule-input[data-meeting-id="' + meetingId + '"]');
			if (!input || !input.value) { alert('Please select a date and time.'); return; }
			data.append('scheduled_at', input.value.replace('T', ' ') + ':00');
		}

		// Cancel confirmation.
		if (action === 'cancel_meeting') {
			if (!confirm('Are you sure you want to cancel this meeting?')) return;
		}

		btn.disabled = true;
		btn.textContent = '...';

		fetch(cbnexusMtg.ajax_url, { method: 'POST', credentials: 'same-origin', body: data })
			.then(function (r) { return r.json(); })
			.then(function (json) {
				if (json.success) {
					location.reload();
				} else {
					alert((json.data && json.data.errors) ? json.data.errors.join('\n') : 'An error occurred.');
					btn.disabled = false;
					btn.textContent = action.replace(/_/g, ' ');
				}
			})
			.catch(function () { btn.disabled = false; });
	});

	// Request 1:1 button (on directory profile pages).
	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.cbnexus-request-meeting-btn');
		if (!btn || btn.disabled) return;

		var memberId = btn.getAttribute('data-member-id');
		if (!memberId) return;

		btn.disabled = true;
		btn.textContent = 'Sending...';

		var data = new FormData();
		data.append('action', 'cbnexus_request_meeting');
		data.append('nonce', cbnexusMtg.nonce);
		data.append('target_id', memberId);

		fetch(cbnexusMtg.ajax_url, { method: 'POST', credentials: 'same-origin', body: data })
			.then(function (r) { return r.json(); })
			.then(function (json) {
				if (json.success) {
					btn.textContent = 'Request Sent!';
					btn.classList.remove('cbnexus-btn-primary');
					btn.classList.add('cbnexus-btn-outline-dark');
				} else {
					alert((json.data && json.data.errors) ? json.data.errors.join('\n') : 'Could not send request.');
					btn.disabled = false;
					btn.textContent = 'Request 1:1';
				}
			})
			.catch(function () {
				btn.disabled = false;
				btn.textContent = 'Request 1:1';
			});
	});

	// Log Meeting – toggle and submit.
	var logToggle = document.getElementById('cbnexus-log-meeting-toggle');
	var logBody   = document.getElementById('cbnexus-log-meeting-body');
	if (logToggle && logBody) {
		logToggle.addEventListener('click', function () {
			var open = logBody.style.display !== 'none';
			logBody.style.display = open ? 'none' : '';
			logToggle.classList.toggle('open', !open);
		});
	}

	var logForm = document.getElementById('cbnexus-log-meeting-form');
	if (logForm) {
		logForm.addEventListener('submit', function (e) {
			e.preventDefault();
			var btn = logForm.querySelector('button[type="submit"]');
			var msgEl = document.getElementById('cbnexus-log-meeting-msg');

			var partner = logForm.querySelector('[name="partner_id"]').value;
			if (!partner) {
				if (msgEl) { msgEl.textContent = 'Please select a member.'; msgEl.className = 'cbnexus-referral-msg cbnexus-referral-msg-error'; msgEl.style.display = ''; }
				return;
			}

			btn.disabled = true;
			btn.textContent = 'Logging…';

			var data = new FormData();
			data.append('action', 'cbnexus_log_meeting');
			data.append('nonce', cbnexusMtg.nonce);
			data.append('partner_id', partner);
			data.append('met_at', logForm.querySelector('[name="met_at"]').value || '');
			data.append('wins', (logForm.querySelector('[name="wins"]') || {}).value || '');

			fetch(cbnexusMtg.ajax_url, { method: 'POST', credentials: 'same-origin', body: data })
				.then(function (r) { return r.json(); })
				.then(function (json) {
					if (json.success) {
						if (msgEl) { msgEl.textContent = 'Meeting logged! 🎉'; msgEl.className = 'cbnexus-referral-msg cbnexus-referral-msg-success'; msgEl.style.display = ''; }
						logForm.reset();
						btn.textContent = 'Logged ✓';
						setTimeout(function () { location.reload(); }, 1200);
					} else {
						var errMsg = (json.data && json.data.errors) ? json.data.errors.join('\n') : 'Something went wrong.';
						if (msgEl) { msgEl.textContent = errMsg; msgEl.className = 'cbnexus-referral-msg cbnexus-referral-msg-error'; msgEl.style.display = ''; }
						btn.disabled = false;
						btn.textContent = 'Log Meeting';
					}
				})
				.catch(function () { btn.disabled = false; btn.textContent = 'Log Meeting'; });
		});
	}

	// Rating button click handler.
	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.cbnexus-rating-btn');
		if (!btn) return;

		var container = btn.closest('.cbnexus-rating-input');
		if (!container) return;

		// Remove active from siblings, add to clicked.
		container.querySelectorAll('.cbnexus-rating-btn').forEach(function (b) {
			b.classList.remove('active');
		});
		btn.classList.add('active');

		// Update the hidden input.
		var hidden = container.querySelector('input[name="rating"]');
		if (hidden) {
			hidden.value = btn.getAttribute('data-rating');
		}
	});

	// Notes form submission.
	document.addEventListener('submit', function (e) {
		var form = e.target.closest('.cbnexus-notes-form');
		if (!form) return;
		e.preventDefault();

		var meetingId = form.getAttribute('data-meeting-id');
		var submitBtn = form.querySelector('button[type="submit"]');

		var data = new FormData();
		data.append('action', 'cbnexus_submit_notes');
		data.append('nonce', cbnexusMtg.nonce);
		data.append('meeting_id', meetingId);
		data.append('wins', form.querySelector('[name="wins"]').value);
		data.append('insights', form.querySelector('[name="insights"]').value);
		data.append('action_items', form.querySelector('[name="action_items"]').value);

		var ratingInput = form.querySelector('input[name="rating"]');
		data.append('rating', ratingInput ? ratingInput.value || '0' : '0');

		submitBtn.disabled = true;
		submitBtn.textContent = 'Submitting...';

		fetch(cbnexusMtg.ajax_url, { method: 'POST', credentials: 'same-origin', body: data })
			.then(function (r) { return r.json(); })
			.then(function (json) {
				if (json.success) {
					location.reload();
				} else {
					alert((json.data && json.data.errors) ? json.data.errors.join('\n') : 'Failed to submit notes.');
					submitBtn.disabled = false;
					submitBtn.textContent = 'Submit Notes';
				}
			})
			.catch(function () { submitBtn.disabled = false; submitBtn.textContent = 'Submit Notes'; });
	});
})();
