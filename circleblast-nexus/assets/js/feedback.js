/**
 * CircleBlast Nexus – Feedback Form (modal + AJAX)
 *
 * Handles the "Send Feedback" modal triggered by the header icon.
 * Submits via AJAX to avoid page reloads.
 *
 * @since 1.3.0
 */
(function () {
	'use strict';

	var overlay, modal, form, msgEl, submitBtn;
	var closing = false;

	function init() {
		overlay   = document.getElementById('cbnexus-feedback-overlay');
		modal     = document.getElementById('cbnexus-feedback-modal');
		form      = document.getElementById('cbnexus-feedback-form');
		msgEl     = document.getElementById('cbnexus-feedback-msg');

		if (!overlay || !modal || !form) return;

		submitBtn = form.querySelector('button[type="submit"]');

		// Open triggers: any element with data-feedback-open.
		document.addEventListener('click', function (e) {
			var trigger = e.target.closest('[data-feedback-open]');
			if (!trigger) return;
			e.preventDefault();
			openModal();
		});

		// Close triggers.
		overlay.addEventListener('click', closeModal);
		var closeBtns = modal.querySelectorAll('[data-feedback-close]');
		for (var i = 0; i < closeBtns.length; i++) {
			closeBtns[i].addEventListener('click', closeModal);
		}
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && overlay.classList.contains('open')) {
				closeModal();
			}
		});

		// Type pill selection.
		var pills = form.querySelectorAll('.cbnexus-feedback-pill input[type="radio"]');
		for (var j = 0; j < pills.length; j++) {
			pills[j].addEventListener('change', function () {
				var allPills = form.querySelectorAll('.cbnexus-feedback-pill');
				for (var k = 0; k < allPills.length; k++) {
					allPills[k].classList.remove('active');
				}
				this.closest('.cbnexus-feedback-pill').classList.add('active');
			});
		}

		// Submit.
		form.addEventListener('submit', handleSubmit);
	}

	function openModal() {
		// Reset form.
		form.reset();
		msgEl.style.display = 'none';
		msgEl.className = 'cbnexus-feedback-msg';
		if (submitBtn) {
			submitBtn.disabled = false;
			submitBtn.textContent = 'Send Feedback';
		}

		// Reset pill state.
		var allPills = form.querySelectorAll('.cbnexus-feedback-pill');
		for (var i = 0; i < allPills.length; i++) {
			allPills[i].classList.remove('active');
		}
		var firstPill = form.querySelector('.cbnexus-feedback-pill');
		if (firstPill) firstPill.classList.add('active');

		// Capture the current page context from the URL.
		var params = new URLSearchParams(window.location.search);
		var context = params.get('section') || 'dashboard';
		var contextEl = document.getElementById('cbnexus-feedback-context');
		if (contextEl) contextEl.value = context;

		overlay.classList.add('open');
		modal.classList.add('open');

		// Focus message textarea.
		var messageInput = form.querySelector('textarea[name="message"]');
		if (messageInput) setTimeout(function () { messageInput.focus(); }, 150);
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

		var message = form.querySelector('[name="message"]').value.trim();
		if (!message) {
			showMsg('Please include a message.', 'error');
			return;
		}

		if (submitBtn) {
			submitBtn.disabled = true;
			submitBtn.textContent = 'Sending…';
		}

		var data = new FormData(form);
		data.append('action', 'cbnexus_submit_feedback');
		data.append('_ajax_nonce', cbnexusFeedback.nonce);

		var xhr = new XMLHttpRequest();
		xhr.open('POST', cbnexusFeedback.ajax_url, true);
		xhr.onload = function () {
			var resp;
			try { resp = JSON.parse(xhr.responseText); } catch (ex) {
				showMsg('Something went wrong. Please try again.', 'error');
				if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Send Feedback'; }
				return;
			}
			if (resp.success) {
				showMsg('Thank you for your feedback! 🎉', 'success');
				form.reset();
				if (submitBtn) { submitBtn.textContent = 'Sent ✓'; }
				setTimeout(closeModal, 1800);
			} else {
				showMsg(resp.data || 'Something went wrong.', 'error');
				if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Send Feedback'; }
			}
		};
		xhr.onerror = function () {
			showMsg('Network error. Please try again.', 'error');
			if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Send Feedback'; }
		};
		xhr.send(data);
	}

	function showMsg(text, type) {
		if (!msgEl) return;
		msgEl.textContent = text;
		msgEl.className = 'cbnexus-feedback-msg cbnexus-feedback-msg-' + type;
		msgEl.style.display = '';
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
