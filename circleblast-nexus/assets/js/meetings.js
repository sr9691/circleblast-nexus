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

		var ratingEl = form.querySelector('[name="rating"]:checked');
		data.append('rating', ratingEl ? ratingEl.value : '0');

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
