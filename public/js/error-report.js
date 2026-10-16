/*
 * Sends uncaught JavaScript errors to /client-errors (which forwards them to error tracking).
 * At most 5 per page load, and the same error only once. Never throws, never blocks the page.
 */
(function () {
	'use strict';

	var script = document.currentScript;
	var url = script && script.dataset.url;
	var token = document.querySelector('meta[name="csrf-token"]');
	if (!url || !window.fetch) { return; }

	var sent = 0;
	var seen = {};

	function report(data) {
		try {
			var key = data.message + '|' + data.source + '|' + data.line;
			if (sent >= 5 || seen[key]) { return; }
			seen[key] = true;
			sent++;
			data.page = location.href.split('?')[0].split('#')[0];
			fetch(url, {
				method: 'POST',
				credentials: 'same-origin',
				keepalive: true,
				headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token ? token.content : '' },
				body: JSON.stringify(data)
			}).catch(function () {});
		} catch (error) { /* ignore */ }
	}

	window.addEventListener('error', function (event) {
		if (!event.message || event.message === 'Script error.') { return; }
		report({ message: String(event.message).slice(0, 500), source: event.filename, line: event.lineno, column: event.colno, stack: event.error && event.error.stack ? String(event.error.stack).slice(0, 4000) : '' });
	});
	window.addEventListener('unhandledrejection', function (event) {
		var reason = event.reason;
		report({ message: String(reason && reason.message ? reason.message : reason).slice(0, 500), source: '', line: 0, column: 0, stack: reason && reason.stack ? String(reason.stack).slice(0, 4000) : '' });
	});
}());
