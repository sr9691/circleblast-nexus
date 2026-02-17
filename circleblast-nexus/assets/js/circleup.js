/**
 * CircleBlast Nexus – CircleUp Archive JS
 * ITER-0014: Search and quick submission handlers.
 */
(function () {
	'use strict';

	var searchInput = document.getElementById('cbnexus-cu-search');
	var resultsDiv  = document.getElementById('cbnexus-cu-results');
	var timer;

	if (searchInput && resultsDiv) {
		searchInput.addEventListener('input', function () {
			clearTimeout(timer);
			var q = searchInput.value.trim();
			if (q.length < 2) { resultsDiv.style.display = 'none'; return; }
			timer = setTimeout(function () {
				var data = new FormData();
				data.append('action', 'cbnexus_circleup_search');
				data.append('nonce', cbnexusCU.nonce);
				data.append('query', q);
				fetch(cbnexusCU.ajax_url, { method: 'POST', credentials: 'same-origin', body: data })
					.then(function (r) { return r.json(); })
					.then(function (json) {
						if (json.success && json.data.html) {
							resultsDiv.innerHTML = json.data.html;
							resultsDiv.style.display = 'block';
						}
					});
			}, 400);
		});
	}

	// Quick submission form.
	var form = document.getElementById('cbnexus-cu-submit-form');
	if (form) {
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var typeEl    = document.getElementById('cbnexus-cu-type');
			var contentEl = document.getElementById('cbnexus-cu-content');
			var msgEl     = document.getElementById('cbnexus-cu-submit-msg');
			if (!contentEl.value.trim()) return;

			var data = new FormData();
			data.append('action', 'cbnexus_circleup_submit');
			data.append('nonce', cbnexusCU.nonce);
			data.append('item_type', typeEl.value);
			data.append('content', contentEl.value.trim());

			var btn = form.querySelector('button[type="submit"]');
			btn.disabled = true;

			fetch(cbnexusCU.ajax_url, { method: 'POST', credentials: 'same-origin', body: data })
				.then(function (r) { return r.json(); })
				.then(function (json) {
					msgEl.style.display = 'block';
					if (json.success) {
						msgEl.textContent = json.data.message || 'Submitted!';
						msgEl.style.color = '#059669';
						contentEl.value = '';
					} else {
						msgEl.textContent = (json.data && json.data.errors) ? json.data.errors.join(', ') : 'Error submitting.';
						msgEl.style.color = '#e53e3e';
					}
					btn.disabled = false;
					setTimeout(function () { msgEl.style.display = 'none'; }, 4000);
				})
				.catch(function () { btn.disabled = false; });
		});
	}
})();
