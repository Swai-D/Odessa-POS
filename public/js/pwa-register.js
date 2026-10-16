/*
 * Registers the service worker (the till opens offline) and clears what it saved when someone signs out,
 * so the next person on a shared computer does not see the previous shop's till or product list.
 * Sales that are still waiting to be sent are NOT cleared: they belong to the shop and must not be lost.
 */
(function () {
	'use strict';

	if ('serviceWorker' in navigator) {
		window.addEventListener('load', function () {
			navigator.serviceWorker.register('/sw.js').catch(function () { /* the till still works online */ });
		});
	}

	document.addEventListener('submit', function (event) {
		var form = event.target;
		if (!form || !form.getAttribute || !/\/logout\/?$/.test(form.getAttribute('action') || '')) { return; }
		try {
			if (navigator.serviceWorker && navigator.serviceWorker.controller) { navigator.serviceWorker.controller.postMessage('wipe'); }
			if (window.PosOffline) { window.PosOffline.wipeCatalog(); }
			else if (window.indexedDB) {
				var open = window.indexedDB.open('odessa-pos', 1);
				open.onsuccess = function () {
					var db = open.result;
					if (db.objectStoreNames.contains('kv')) { db.transaction('kv', 'readwrite').objectStore('kv').clear(); }
					db.close();
				};
			}
		} catch (e) { /* signing out must never be blocked */ }
	}, true);
})();
