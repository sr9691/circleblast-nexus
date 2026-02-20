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
				countEl.textContent = json.data.label;
			}
		})
		.catch(function () {
			loadingEl.style.display = 'none';
			resultsEl.style.opacity = '1';
		});
	}
})();