/*
 * Device storage for the offline till (IndexedDB):
 *  - kv:    the saved product/customer list per warehouse ("snapshot:<tenant>:<warehouse>")
 *  - queue: sales made without a connection, waiting to be sent. Each has the checkout payload with its idempotency key,
 *           so sending one twice can never record it twice.
 */
(function () {
	'use strict';

	var DB = 'odessa-pos';
	var dbPromise = null;

	function open() {
		if (dbPromise) { return dbPromise; }
		dbPromise = new Promise(function (resolve, reject) {
			if (!window.indexedDB) { reject(new Error('no indexedDB')); return; }
			var request = window.indexedDB.open(DB, 1);
			request.onupgradeneeded = function () {
				var db = request.result;
				if (!db.objectStoreNames.contains('kv')) { db.createObjectStore('kv'); }
				if (!db.objectStoreNames.contains('queue')) { db.createObjectStore('queue', { keyPath: 'key' }); }
			};
			request.onsuccess = function () { resolve(request.result); };
			request.onerror = function () { reject(request.error); };
		});
		return dbPromise;
	}

	function run(store, mode, work) {
		return open().then(function (db) {
			return new Promise(function (resolve, reject) {
				var tx = db.transaction(store, mode);
				var result = work(tx.objectStore(store));
				tx.oncomplete = function () { resolve(result && 'result' in result ? result.result : undefined); };
				tx.onerror = function () { reject(tx.error); };
				tx.onabort = function () { reject(tx.error); };
			});
		});
	}

	window.PosOffline = {
		available: !!window.indexedDB,
		get: function (key) { return run('kv', 'readonly', function (s) { return s.get(key); }); },
		put: function (key, value) { return run('kv', 'readwrite', function (s) { return s.put(value, key); }); },
		wipeCatalog: function () { return run('kv', 'readwrite', function (s) { return s.clear(); }).catch(function () {}); },
		queueAll: function (tenant) {
			return run('queue', 'readonly', function (s) { return s.getAll(); }).then(function (all) {
				return (all || []).filter(function (rec) { return rec.tenant === tenant; }).sort(function (a, b) { return a.created_at < b.created_at ? -1 : 1; });
			});
		},
		queuePut: function (record) { return run('queue', 'readwrite', function (s) { return s.put(record); }); },
		queueRemove: function (key) { return run('queue', 'readwrite', function (s) { return s.delete(key); }); }
	};
})();
