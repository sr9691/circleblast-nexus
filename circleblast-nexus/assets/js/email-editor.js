/**
 * CircleBlast Nexus — Email Template Rich-Text Editor
 *
 * Provides a Visual / HTML toggle for email template editing.
 * Visual mode uses contenteditable with a formatting toolbar.
 * HTML mode uses a plain textarea (monospace).
 * The hidden textarea[name="body"] is always the form submission source.
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var wrap = document.getElementById('cbnexus-email-editor');
		if (!wrap) return;

		var hiddenBody  = wrap.querySelector('textarea[name="body"]');
		var visual      = wrap.querySelector('.cbnexus-rte-visual');
		var htmlArea    = wrap.querySelector('.cbnexus-rte-html');
		var tabVisual   = wrap.querySelector('[data-rte-tab="visual"]');
		var tabHtml     = wrap.querySelector('[data-rte-tab="html"]');
		var toolbar     = wrap.querySelector('.cbnexus-rte-toolbar');

		if (!hiddenBody || !visual || !htmlArea || !tabVisual || !tabHtml) return;

		// ─── Initialize visual editor with current content ───────────
		visual.innerHTML = hiddenBody.value;
		htmlArea.value   = hiddenBody.value;

		// ─── Tab switching ───────────────────────────────────────────
		function switchTo(mode) {
			if (mode === 'visual') {
				// Sync HTML → visual.
				visual.innerHTML = htmlArea.value;
				visual.style.display = 'block';
				htmlArea.style.display = 'none';
				toolbar.style.display = '';
				tabVisual.classList.add('active');
				tabHtml.classList.remove('active');
			} else {
				// Sync visual → HTML (pretty-print lightly).
				htmlArea.value = visual.innerHTML;
				visual.style.display = 'none';
				htmlArea.style.display = 'block';
				toolbar.style.display = 'none';
				tabHtml.classList.add('active');
				tabVisual.classList.remove('active');
			}
		}

		tabVisual.addEventListener('click', function (e) { e.preventDefault(); switchTo('visual'); });
		tabHtml.addEventListener('click', function (e) { e.preventDefault(); switchTo('html'); });

		// ─── Sync content to hidden textarea on any change ──────────
		visual.addEventListener('input', function () {
			hiddenBody.value = visual.innerHTML;
		});
		htmlArea.addEventListener('input', function () {
			hiddenBody.value = htmlArea.value;
		});

		// Also sync right before form submit (belt + suspenders).
		var form = wrap.closest('form');
		if (form) {
			form.addEventListener('submit', function () {
				if (visual.style.display !== 'none') {
					hiddenBody.value = visual.innerHTML;
				} else {
					hiddenBody.value = htmlArea.value;
				}
			});
		}

		// ─── Toolbar commands ────────────────────────────────────────
		wrap.querySelectorAll('.cbnexus-rte-toolbar button[data-cmd]').forEach(function (btn) {
			btn.addEventListener('click', function (e) {
				e.preventDefault();
				var cmd = btn.getAttribute('data-cmd');
				var val = btn.getAttribute('data-val') || null;

				if (cmd === 'createLink') {
					var url = prompt('Enter URL:', 'https://');
					if (!url) return;
					document.execCommand(cmd, false, url);
				} else if (cmd === 'formatBlock') {
					document.execCommand(cmd, false, val);
				} else {
					document.execCommand(cmd, false, val);
				}

				visual.focus();
				hiddenBody.value = visual.innerHTML;
			});
		});

		// ─── Placeholder insertion ───────────────────────────────────
		wrap.querySelectorAll('.cbnexus-rte-placeholder-btn').forEach(function (btn) {
			btn.addEventListener('click', function (e) {
				e.preventDefault();
				var tag = btn.getAttribute('data-placeholder');
				if (!tag) return;

				if (visual.style.display !== 'none') {
					// Visual mode — insert at cursor.
					visual.focus();
					document.execCommand('insertText', false, '{{' + tag + '}}');
					hiddenBody.value = visual.innerHTML;
				} else {
					// HTML mode — insert at cursor in textarea.
					var start = htmlArea.selectionStart;
					var end   = htmlArea.selectionEnd;
					var text  = htmlArea.value;
					htmlArea.value = text.substring(0, start) + '{{' + tag + '}}' + text.substring(end);
					htmlArea.selectionStart = htmlArea.selectionEnd = start + tag.length + 4;
					htmlArea.focus();
					hiddenBody.value = htmlArea.value;
				}
			});
		});

		// ─── Start in visual mode ────────────────────────────────────
		switchTo('visual');
	});
})();
