/**
 * Journal JS
 *
 * Handles:
 *  - Collapsible form toggle (reuses cbnexus-cu-submit-* pattern)
 *  - New entry form submission (AJAX)
 *  - Entry deletion (AJAX)
 *  - Type filter tabs (AJAX)
 *  - Stats bar update after add/delete
 */
(function () {
	'use strict';

	const cfg = window.cbnexusJournal || {};

	function ready(fn) {
		if (document.readyState !== 'loading') { fn(); }
		else { document.addEventListener('DOMContentLoaded', fn); }
	}

	// ── helpers ──────────────────────────────────────────────────────────
	function post(action, data, cb) {
		const body = new FormData();
		body.append('action', action);
		body.append('nonce', cfg.nonce);
		Object.entries(data).forEach(([k, v]) => body.append(k, v));
		fetch(cfg.ajax_url, { method: 'POST', body })
			.then(r => r.json())
			.then(cb)
			.catch(() => cb({ success: false, data: 'Network error.' }));
	}

	function showMsg(el, text, isOk) {
		if (!el) return;
		el.textContent = text;
		el.style.display = 'block';
		el.style.color = isOk ? 'var(--cb-green,#2a7a2a)' : 'var(--cb-error,#c00)';
		if (isOk) { setTimeout(() => { el.style.display = 'none'; }, 3000); }
	}

	// ── type-specific form hints ─────────────────────────────────────────
	var typeHints = {
		win:               { label: 'What did you win?',          placeholder: 'e.g. Closed a deal, landed a new client, hit a milestone...' },
		insight:           { label: 'What did you learn?',        placeholder: 'e.g. New strategy, key takeaway from a conversation...' },
		referral_given:    { label: 'Who did you refer?',         placeholder: 'e.g. Referred Jane to Bob for web design services...' },
		referral_received: { label: 'What referral did you get?', placeholder: 'e.g. Got an intro to Acme Corp from a fellow member...' },
		action:            { label: 'What did you commit to?',    placeholder: 'e.g. Follow up with prospect, send proposal by Friday...' },
	};

	function initTypeHints() {
		var radios  = document.querySelectorAll('#cbnexus-journal-form input[name="entry_type"]');
		var labelEl = document.querySelector('label[for="cbnexus-journal-content"]');
		var textarea = document.getElementById('cbnexus-journal-content');
		if (!radios.length || !labelEl || !textarea) return;

		function update(type) {
			var hint = typeHints[type];
			if (!hint) return;
			labelEl.innerHTML = hint.label + ' <span style="color:var(--cb-accent);">*</span>';
			textarea.placeholder = hint.placeholder;
		}

		radios.forEach(function (r) {
			r.addEventListener('change', function () { update(r.value); });
		});
		// Set initial hint for default selection.
		var checked = document.querySelector('#cbnexus-journal-form input[name="entry_type"]:checked');
		if (checked) update(checked.value);
	}

	// ── collapsible toggle (mirrors circleup.js pattern) ─────────────────
	function initToggle() {
		const btn  = document.getElementById('cbnexus-journal-toggle');
		const body = document.getElementById('cbnexus-journal-form-body');
		if (!btn || !body) return;
		const chevron = btn.querySelector('.cbnexus-cu-submit-chevron');
		btn.addEventListener('click', () => {
			const open = !body.hidden;
			body.hidden = open;
			btn.setAttribute('aria-expanded', String(!open));
			if (chevron) chevron.style.transform = open ? '' : 'rotate(90deg)';
		});
	}

	// ── add entry ─────────────────────────────────────────────────────────
	function initForm() {
		const form = document.getElementById('cbnexus-journal-form');
		const msg  = document.getElementById('cbnexus-journal-msg');
		const feed = document.getElementById('cbnexus-journal-feed');
		if (!form) return;

		form.addEventListener('submit', e => {
			e.preventDefault();
			const btn = form.querySelector('[type=submit]');
			const orig = btn.textContent;
			btn.disabled = true;
			btn.textContent = 'Saving…';

			const data = {
				entry_type:  form.querySelector('[name=entry_type]:checked')?.value || 'win',
				content:     form.querySelector('[name=content]').value,
				context:     form.querySelector('[name=context]').value,
				entry_date:  form.querySelector('[name=entry_date]').value,
				visibility:  form.querySelector('[name=visibility]').value,
			};

			post('cbnexus_journal_add', data, res => {
				btn.disabled = false;
				btn.textContent = orig;
				if (!res.success) {
					const err = res.data?.errors?.[0] || 'Error saving entry.';
					showMsg(msg, err, false);
					return;
				}
				// Prepend new card to feed.
				if (feed && res.data.card_html) {
					const tmp = document.createElement('div');
					tmp.innerHTML = res.data.card_html;
					Array.from(tmp.children).reverse().forEach(c => {
						feed.insertBefore(c, feed.firstChild);
					});
					// Remove empty-state placeholder if present.
					feed.querySelectorAll('.cbnexus-card p.cbnexus-text-muted').forEach(p => {
						if (p.closest('.cbnexus-card')) { p.closest('.cbnexus-card').remove(); }
					});
				}
				updateStats(res.data.counts, res.data.total);
				showMsg(msg, '✓ Saved!', true);
				form.querySelector('[name=content]').value = '';
				form.querySelector('[name=context]').value = '';
			});
		});
	}

	// ── delete entry ──────────────────────────────────────────────────────
	function initDeleteDelegation() {
		const feed = document.getElementById('cbnexus-journal-feed');
		if (!feed) return;
		feed.addEventListener('click', e => {
			const btn = e.target.closest('.cbnexus-journal-delete-btn');
			if (!btn) return;
			if (!confirm('Delete this entry?')) return;
			const entryId = btn.dataset.entryId;
			btn.disabled = true;
			post('cbnexus_journal_delete', { entry_id: entryId }, res => {
				if (!res.success) { btn.disabled = false; return; }
				const card = btn.closest('.cbnexus-journal-entry');
				if (card) { card.remove(); }
				updateStats(res.data.counts, res.data.total);
				// Show empty state if no entries left.
				if (res.data.total === 0) {
					feed.innerHTML = '<div class="cbnexus-card"><p class="cbnexus-text-muted" style="text-align:center;padding:24px 0;">Nothing here yet. Add your first entry above! 🎉</p></div>';
				}
			});
		});
	}

	// ── filter tabs (progressive enhancement: full-page fallback works too) ──
	function initFilterTabs() {
		const tabs = document.querySelectorAll('.cbnexus-journal-filter-tab');
		const feed = document.getElementById('cbnexus-journal-feed');
		if (!tabs.length || !feed) return;
		tabs.forEach(tab => {
			tab.addEventListener('click', e => {
				e.preventDefault();
				const url  = new URL(tab.href);
				const type = url.searchParams.get('jtype') || '';
				tabs.forEach(t => t.classList.remove('active'));
				tab.classList.add('active');
				feed.style.opacity = '0.5';
				post('cbnexus_journal_filter', { jtype: type }, res => {
					feed.style.opacity = '1';
					if (res.success) { feed.innerHTML = res.data.html; }
				});
			});
		});
	}

	// ── stats bar update ──────────────────────────────────────────────────
	const TYPE_META = {
		win:               { icon: '🏆', label: 'Win',               pill: 'cbnexus-pill--gold-soft' },
		insight:           { icon: '💡', label: 'Insight',           pill: 'cbnexus-pill--accent-soft' },
		referral_given:    { icon: '🤝', label: 'Referral Given',    pill: 'cbnexus-pill--green' },
		referral_received: { icon: '⭐', label: 'Referral Received', pill: 'cbnexus-pill--blue' },
		action:            { icon: '✅', label: 'Action',            pill: 'cbnexus-pill--muted' },
	};

	function updateStats(counts, total) {
		const bar = document.getElementById('cbnexus-journal-stats');
		if (!bar) return;
		if (total === 0) { bar.innerHTML = ''; return; }
		bar.innerHTML = Object.entries(counts)
			.filter(([, v]) => v > 0)
			.map(([type, cnt]) => {
				const m = TYPE_META[type];
				if (!m) return '';
				return `<span class="cbnexus-pill ${m.pill}">${m.icon} ${cnt} ${m.label}${cnt === 1 ? '' : 's'}</span>`;
			}).join('');
	}

	ready(() => {
		initToggle();
		initTypeHints();
		initForm();
		initDeleteDelegation();
		initFilterTabs();
	});
}());
