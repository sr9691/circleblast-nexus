/**
 * CircleBlast Nexus – Referral Form (modal + AJAX)
 *
 * Handles the "Refer Someone" modal that appears on Dashboard and Directory.
 * Submits via AJAX to avoid page reloads.
 *
 * @since 1.2.0
 */
(function () {
	'use strict';

	var overlay, modal, form, msgEl, submitBtn;
	var closing = false;

	function init() {
		overlay = document.getElementById('cbnexus-referral-overlay');
		modal   = document.getElementById('cbnexus-referral-modal');
		form    = document.getElementById('cbnexus-referral-form');
		msgEl   = document.getElementById('cbnexus-referral-msg');

		if (!overlay || !modal || !form) return;

		submitBtn = form.querySelector('button[type="submit"]');

		// Open triggers: any element with data-referral-open.
		document.addEventListener('click', function (e) {
			var trigger = e.target.closest('[data-referral-open]');
			if (!trigger) return;
			e.preventDefault();
			openModal(trigger);
		});

		// Close triggers.
		overlay.addEventListener('click', closeModal);
		var closeBtns = modal.querySelectorAll('[data-referral-close]');
		for (var i = 0; i < closeBtns.length; i++) {
			closeBtns[i].addEventListener('click', closeModal);
		}
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && overlay.classList.contains('open')) {
				closeModal();
			}
		});

		// Submit.
		form.addEventListener('submit', handleSubmit);

		// Auto-open if URL has ?referral=open.
		checkAutoOpen();
	}

	function openModal(trigger) {
		// Pre-populate from data attributes.
		var catId    = trigger.getAttribute('data-referral-category') || '';
		var catTitle = trigger.getAttribute('data-referral-category-title') || '';

		// Reset form.
		form.reset();
		msgEl.style.display = 'none';
		msgEl.className = 'cbnexus-referral-msg';
		if (submitBtn) {
			submitBtn.disabled = false;
			submitBtn.textContent = 'Submit Referral';
		}

		// Set category if provided.
		var catSelect = form.querySelector('[name="category_id"]');
		if (catSelect && catId) {
			catSelect.value = catId;
		}

		// Show the category context if available.
		var catContext = document.getElementById('cbnexus-referral-cat-context');
		if (catContext) {
			if (catTitle) {
				catContext.textContent = 'For: ' + catTitle;
				catContext.style.display = '';
			} else {
				catContext.style.display = 'none';
			}
		}

		overlay.classList.add('open');
		modal.classList.add('open');
		// Focus first input.
		var firstInput = form.querySelector('input[name="name"]');
		if (firstInput) setTimeout(function () { firstInput.focus(); }, 150);
	}

	function closeModal() {
		if (closing) return;
		closing = true;
		overlay.classList.remove('open');
		modal.classList.remove('open');
		setTimeout(function () { closing = false; }, 300);
	}

	function handleSubmit(e) {
		e.preventDefault();

		var name = form.querySelector('[name="name"]').value.trim();
		if (!name) {
			showMsg('Please enter the person\'s name.', 'error');
			return;
		}

		if (submitBtn) {
			submitBtn.disabled = true;
			submitBtn.textContent = 'Submitting…';
		}

		var data = new FormData(form);
		data.append('action', 'cbnexus_submit_referral');
		data.append('_ajax_nonce', cbnexusReferral.nonce);

		var xhr = new XMLHttpRequest();
		xhr.open('POST', cbnexusReferral.ajax_url, true);
		xhr.onload = function () {
			var resp;
			try { resp = JSON.parse(xhr.responseText); } catch (ex) {
				showMsg('Something went wrong. Please try again.', 'error');
				if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Submit Referral'; }
				return;
			}
			if (resp.success) {
				showMsg('Thank you! Your referral has been submitted. 🎉', 'success');
				form.reset();
				if (submitBtn) { submitBtn.textContent = 'Submitted ✓'; }
				setTimeout(closeModal, 1800);
			} else {
				showMsg(resp.data || 'Something went wrong.', 'error');
				if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Submit Referral'; }
			}
		};
		xhr.onerror = function () {
			showMsg('Network error. Please try again.', 'error');
			if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Submit Referral'; }
		};
		xhr.send(data);
	}

	function showMsg(text, type) {
		if (!msgEl) return;
		msgEl.textContent = text;
		msgEl.className = 'cbnexus-referral-msg cbnexus-referral-msg-' + type;
		msgEl.style.display = '';
	}

	function checkAutoOpen() {
		var params = new URLSearchParams(window.location.search);
		if (params.get('referral') === 'open' && overlay) {
			openModal(document.createElement('span'));
			// Clean the URL so refreshing doesn't re-open.
			params.delete('referral');
			var clean = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
			window.history.replaceState({}, '', clean);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
