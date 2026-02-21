/**
 * CircleBlast Nexus – CircleUp Archive JS
 * ITER-0014: Search, quick submission, Quick Share toggle, and type hints.
 */
(function () {
	'use strict';

	// ── Hint text by type ──────────────────────────────────────────
	var hints = {
		win:         'e.g. "Closed a deal from a CircleBlast referral" or "Got a promotion at work"',
		insight:     'e.g. "Learned a great cold-outreach strategy" or "New perspective on pricing"',
		opportunity: 'e.g. "Looking for an intro to someone in healthcare" or "Partnership idea with a member"'
	};

	// ── Quick Share toggle ──────────────────────────────────────────
	var toggle = document.getElementById('cbnexus-cu-toggle');
	var body   = document.getElementById('cbnexus-cu-submit-body');

	if (toggle && body) {
		toggle.addEventListener('click', function () {
			var expanded = toggle.getAttribute('aria-expanded') === 'true';
			toggle.setAttribute('aria-expanded', String(!expanded));
			body.hidden = expanded;
			if (!expanded) {
				var ta = document.getElementById('cbnexus-cu-content');
				if (ta) { ta.focus(); }
			}
		});
	}

	// ── Type chip radios → update hint ─────────────────────────────
	var radios  = document.querySelectorAll('input[name="cu_type"]');
	var hintEl  = document.getElementById('cbnexus-cu-hint');

	radios.forEach(function (radio) {
		radio.addEventListener('change', function () {
			if (hintEl && hints[radio.value]) {
				hintEl.textContent = hints[radio.value];
			}
		});
	});

	// ── Live search ────────────────────────────────────────────────
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

		searchInput.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') {
				searchInput.value = '';
				resultsDiv.style.display = 'none';
			}
		});
	}

	// ── Quick submission form ──────────────────────────────────────
	var form = document.getElementById('cbnexus-cu-submit-form');
	if (form) {
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var checkedRadio = document.querySelector('input[name="cu_type"]:checked');
			var contentEl    = document.getElementById('cbnexus-cu-content');
			var msgEl        = document.getElementById('cbnexus-cu-submit-msg');
			if (!contentEl.value.trim()) return;

			var data = new FormData();
			data.append('action', 'cbnexus_circleup_submit');
			data.append('nonce', cbnexusCU.nonce);
			data.append('item_type', checkedRadio ? checkedRadio.value : 'win');
			data.append('content', contentEl.value.trim());

			var btn = form.querySelector('button[type="submit"]');
			btn.disabled = true;

			fetch(cbnexusCU.ajax_url, { method: 'POST', credentials: 'same-origin', body: data })
				.then(function (r) { return r.json(); })
				.then(function (json) {
					msgEl.style.display = 'inline';
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