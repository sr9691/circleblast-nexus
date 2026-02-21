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

	// ── My Actions – status update buttons ─────────────────────────
	var actionsPage = document.getElementById('cbnexus-actions-page');
	if (actionsPage) {
		var ajaxUrl = actionsPage.getAttribute('data-ajax');
		var nonce   = actionsPage.getAttribute('data-nonce');

		actionsPage.addEventListener('click', function (e) {
			var btn = e.target.closest('.cbnexus-action-btn');
			if (!btn) return;

			var row    = btn.closest('.cbnexus-action-item');
			var itemId = row.getAttribute('data-id');
			var toStatus = btn.getAttribute('data-to');

			btn.disabled = true;
			btn.classList.add('cbnexus-action-btn--loading');

			var data = new FormData();
			data.append('action', 'cbnexus_action_update_status');
			data.append('nonce', nonce);
			data.append('item_id', itemId);
			data.append('status', toStatus);

			fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data })
				.then(function (r) { return r.json(); })
				.then(function (json) {
					if (!json.success) {
						btn.disabled = false;
						btn.classList.remove('cbnexus-action-btn--loading');
						alert(json.data && json.data.message ? json.data.message : 'Error updating.');
						return;
					}

					var d = json.data;

					/* Update the status pill */
					var pill = row.querySelector('.cbnexus-status-pill');
					if (pill) {
						pill.className = 'cbnexus-status-pill ' + d.pill['class'];
						pill.textContent = d.pill.label;
					}

					/* Update data attribute */
					row.setAttribute('data-status', d.status);

					/* Rebuild the controls */
					var controls = row.querySelector('.cbnexus-action-item__controls');
					controls.innerHTML = '';
					d.buttons.forEach(function (b) {
						var newBtn = document.createElement('button');
						newBtn.type = 'button';
						newBtn.className = 'cbnexus-action-btn ' + b['class'];
						newBtn.setAttribute('data-to', b.label === 'Start' ? 'in_progress' : (b.label === 'Done' ? 'done' : 'approved'));
						newBtn.title = b.label;
						newBtn.innerHTML = '<span class="cbnexus-action-btn__icon">' + b.icon + '</span>' +
							'<span class="cbnexus-action-btn__label">' + b.label + '</span>';
						controls.appendChild(newBtn);
					});

					/* Move row between Open / Done sections */
					var openSection = document.getElementById('cbnexus-actions-open');
					var doneSection = document.getElementById('cbnexus-actions-done');

					if (d.is_done) {
						row.classList.add('cbnexus-action-item--done');
						/* Animate out of open list */
						row.style.transition = 'opacity 0.3s, transform 0.3s';
						row.style.opacity = '0';
						row.style.transform = 'translateX(20px)';
						setTimeout(function () {
							row.style.opacity = '';
							row.style.transform = '';
							row.style.transition = '';
							/* Move to done section, create if needed */
							if (!doneSection) {
								var card = document.createElement('div');
								card.className = 'cbnexus-card';
								card.id = 'cbnexus-actions-done';
								card.innerHTML = '<h3>Completed</h3><div class="cbnexus-actions-list"></div>';
								actionsPage.appendChild(card);
								doneSection = card;
							}
							var doneList = doneSection.querySelector('.cbnexus-actions-list');
							doneList.insertBefore(row, doneList.firstChild);
							row.style.animation = 'cbnexus-fade-in 0.3s ease';
							/* Hide open section if empty */
							if (openSection) {
								var remaining = openSection.querySelectorAll('.cbnexus-action-item');
								if (remaining.length === 0) { openSection.style.display = 'none'; }
							}
						}, 300);
					} else {
						row.classList.remove('cbnexus-action-item--done');
						/* Moving from done back to open */
						row.style.transition = 'opacity 0.3s, transform 0.3s';
						row.style.opacity = '0';
						row.style.transform = 'translateX(-20px)';
						setTimeout(function () {
							row.style.opacity = '';
							row.style.transform = '';
							row.style.transition = '';
							if (!openSection) {
								var card = document.createElement('div');
								card.className = 'cbnexus-card';
								card.id = 'cbnexus-actions-open';
								card.innerHTML = '<h3>Open</h3><div class="cbnexus-actions-list"></div>';
								/* Insert before done section */
								if (doneSection) {
									actionsPage.insertBefore(card, doneSection);
								} else {
									actionsPage.appendChild(card);
								}
								openSection = card;
								openSection.style.display = '';
							}
							openSection.style.display = '';
							var openList = openSection.querySelector('.cbnexus-actions-list');
							openList.appendChild(row);
							row.style.animation = 'cbnexus-fade-in 0.3s ease';
							/* Hide done section if empty */
							if (doneSection) {
								var remaining = doneSection.querySelectorAll('.cbnexus-action-item');
								if (remaining.length === 0) { doneSection.style.display = 'none'; }
							}
						}, 300);
					}

					/* Update summary counts */
					var summary = document.getElementById('cbnexus-actions-summary');
					if (summary) {
						summary.textContent = d.open_count + ' open \u00b7 ' + d.done_count + ' completed';
					}
				})
				.catch(function () {
					btn.disabled = false;
					btn.classList.remove('cbnexus-action-btn--loading');
				});
		});
	}
})();