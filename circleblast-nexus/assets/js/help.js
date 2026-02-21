/**
 * CircleBlast Nexus — Help Drawer Toggle
 *
 * Opens/closes the context-sensitive help drawer.
 * Also handles Escape key and overlay click to close.
 *
 * @since 1.2.0
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var toggle  = document.getElementById('cbnexus-help-toggle');
		var drawer  = document.getElementById('cbnexus-help-drawer');
		var overlay = document.getElementById('cbnexus-help-overlay');
		var close   = drawer ? drawer.querySelector('.cbnexus-help-close') : null;

		if (!toggle || !drawer) return;

		function open() {
			drawer.classList.add('open');
			drawer.setAttribute('aria-hidden', 'false');
			if (overlay) overlay.classList.add('open');
			// Focus the close button for accessibility.
			if (close) close.focus();
		}

		function shut() {
			drawer.classList.remove('open');
			drawer.setAttribute('aria-hidden', 'true');
			if (overlay) overlay.classList.remove('open');
			toggle.focus();
		}

		toggle.addEventListener('click', function () {
			drawer.classList.contains('open') ? shut() : open();
		});

		if (close) close.addEventListener('click', shut);
		if (overlay) overlay.addEventListener('click', shut);

		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && drawer.classList.contains('open')) {
				shut();
			}
		});
	});
})();
