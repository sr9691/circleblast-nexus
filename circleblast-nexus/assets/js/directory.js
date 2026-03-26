/**
 * CircleBlast Nexus – Directory JS
 * ITER-0007 + Phase 3: AJAX-powered search, filter (industry + category), and view toggle.
 */
(function () {
	'use strict';

	var searchInput    = document.getElementById('cbnexus-dir-search');
	var industrySelect = document.getElementById('cbnexus-dir-industry');
	var categorySelect = document.getElementById('cbnexus-dir-category');
	var statusSelect   = document.getElementById('cbnexus-dir-status');
	var resultsEl      = document.getElementById('cbnexus-dir-results');
	var countEl        = document.getElementById('cbnexus-dir-count');
	var loadingEl      = document.getElementById('cbnexus-dir-loading');
	var viewBtns       = document.querySelectorAll('.cbnexus-view-btn');

	if (!searchInput || !resultsEl) return;

	var debounceTimer = null;

	// Search with debounce.
	searchInput.addEventListener('input', function () {
		clearTimeout(debounceTimer);
		debounceTimer = setTimeout(fetchMembers, 350);
	});

	// Filter changes.
	if (industrySelect) industrySelect.addEventListener('change', fetchMembers);
	if (categorySelect) categorySelect.addEventListener('change', fetchMembers);
	if (statusSelect) statusSelect.addEventListener('change', fetchMembers);

	// View toggle.
	viewBtns.forEach(function (btn) {
		btn.addEventListener('click', function () {
			var view = this.getAttribute('data-view');
			viewBtns.forEach(function (b) { b.classList.remove('active'); });
			this.classList.add('active');

			resultsEl.classList.remove('cbnexus-dir-grid', 'cbnexus-dir-list');
			resultsEl.classList.add('cbnexus-dir-' + view);
		});
	});

	// ── Quick Preview modal ──────────────────────────────────────────
	var overlay  = document.getElementById('cbnexus-preview-overlay');
	var modal    = document.getElementById('cbnexus-preview-modal');
	var nameEl   = document.getElementById('cbnexus-preview-name');
	var titleEl  = document.getElementById('cbnexus-preview-title');
	var bodyEl   = document.getElementById('cbnexus-preview-body');
	var linkEl   = document.getElementById('cbnexus-preview-profile-link');
	var closeBtn = document.getElementById('cbnexus-preview-close');

	function openPreview(btn) {
		var d = btn.dataset;
		nameEl.textContent = d.name || '';
		var titleLine = (d.title || '') + (d.company ? ' · ' + d.company : '');
		titleEl.textContent = titleLine;

		var html = '';
		if (d.industry) html += '<div class="cbnexus-preview-row"><strong>Industry:</strong> ' + esc(d.industry) + '</div>';

		var expertise = tryParse(d.expertise);
		if (expertise.length) {
			html += '<div class="cbnexus-preview-row"><strong>Skills:</strong> ' + expertise.map(function (t) { return '<span class="cbnexus-tag">' + esc(t) + '</span>'; }).join(' ') + '</div>';
		}

		var looking = tryParse(d.looking);
		if (looking.length) {
			html += '<div class="cbnexus-preview-row"><strong>Looking for:</strong> ' + looking.map(function (t) { return '<span class="cbnexus-tag cbnexus-tag-looking">' + esc(t) + '</span>'; }).join(' ') + '</div>';
		}

		var help = tryParse(d.help);
		if (help.length) {
			html += '<div class="cbnexus-preview-row"><strong>Can help with:</strong> ' + help.map(function (t) { return '<span class="cbnexus-tag cbnexus-tag-help">' + esc(t) + '</span>'; }).join(' ') + '</div>';
		}

		if (d.bio) html += '<div class="cbnexus-preview-row cbnexus-preview-bio">' + esc(d.bio) + '</div>';

		// Contact row.
		var contacts = [];
		if (d.email) contacts.push('<a href="mailto:' + esc(d.email) + '">✉ Email</a>');
		if (d.phone) contacts.push('<a href="tel:' + esc(d.phone) + '">📞 ' + esc(d.phone) + '</a>');
		if (d.linkedin) contacts.push('<a href="' + esc(d.linkedin) + '" target="_blank" rel="noopener">🔗 LinkedIn</a>');
		if (contacts.length) html += '<div class="cbnexus-preview-row cbnexus-preview-contacts">' + contacts.join(' &nbsp;·&nbsp; ') + '</div>';

		bodyEl.innerHTML = html;
		linkEl.href = d.profileUrl || '#';

		overlay.style.display = 'block';
		modal.style.display = 'block';
	}

	function closePreview() {
		if (overlay) overlay.style.display = 'none';
		if (modal) modal.style.display = 'none';
	}

	function esc(str) {
		var el = document.createElement('span');
		el.textContent = str;
		return el.innerHTML;
	}

	function tryParse(json) {
		try { var a = JSON.parse(json || '[]'); return Array.isArray(a) ? a : []; }
		catch (e) { return []; }
	}

	if (closeBtn) closeBtn.addEventListener('click', closePreview);
	if (overlay) overlay.addEventListener('click', closePreview);

	// Delegate click on preview buttons (works after AJAX refresh).
	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.cbnexus-preview-btn');
		if (btn) { e.preventDefault(); openPreview(btn); }
	});

	function fetchMembers() {
		var data = new FormData();
		data.append('action', 'cbnexus_directory_filter');
		data.append('nonce', cbnexusDir.nonce);
		data.append('search', searchInput.value);
		data.append('industry', industrySelect ? industrySelect.value : '');
		data.append('category', categorySelect ? categorySelect.value : '');
		data.append('status', statusSelect ? statusSelect.value : 'active');

		loadingEl.style.display = 'block';
		resultsEl.style.opacity = '0.5';

		fetch(cbnexusDir.ajax_url, {
			method: 'POST',
			credentials: 'same-origin',
			body: data
		})
		.then(function (res) { return res.json(); })
		.then(function (json) {
			loadingEl.style.display = 'none';
			resultsEl.style.opacity = '1';

			if (json.success) {
				resultsEl.innerHTML = json.data.html;
				countEl.textContent = json.data.count;
			}
		})
		.catch(function () {
			loadingEl.style.display = 'none';
			resultsEl.style.opacity = '1';
		});
	}
})();