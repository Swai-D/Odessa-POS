/*
 * Odessa POS service worker. It only does two things:
 *  1. keeps the till page (/pos) and the files it needs, so the till opens without internet;
 *  2. leaves every other request alone. Data (products, customers, queued sales) lives in IndexedDB, managed by pos-offline.js.
 * Nothing but the till page is ever cached; a response that redirected (to the sign-in page) is never stored.
 */
'use strict';

var VERSION = 'v1';
var STATIC = 'odessa-static-' + VERSION;
var PAGES = 'odessa-pages-' + VERSION;

self.addEventListener('install', function () { self.skipWaiting(); });

self.addEventListener('activate', function (event) {
	event.waitUntil(
		caches.keys().then(function (keys) {
			return Promise.all(keys.filter(function (key) {
				return key.indexOf('odessa-') === 0 && key !== STATIC && key !== PAGES;
			}).map(function (key) { return caches.delete(key); }));
		}).then(function () { return self.clients.claim(); })
	);
});

// Sign-out on a shared device: forget the cached till page and files.
self.addEventListener('message', function (event) {
	if (event.data === 'wipe') {
		event.waitUntil(Promise.all([caches.delete(STATIC), caches.delete(PAGES)]));
	}
});

function isStatic(url) {
	return /^\/(assets|js|pwa)\//.test(url.pathname) || url.pathname === '/manifest.webmanifest';
}

function offlinePage() {
	return new Response(
		'<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Odessa POS</title>' +
		'<body style="font-family:sans-serif;text-align:center;padding:48px 16px"><h2>No connection</h2>' +
		'<p>Open the till once while online so it can be saved on this device, then it will work without internet.</p>' +
		'<p><a href="/pos">Try again</a></p></body>',
		{ status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } }
	);
}

self.addEventListener('fetch', function (event) {
	var request = event.request;
	if (request.method !== 'GET') { return; }
	var url = new URL(request.url);
	if (url.origin !== self.location.origin) { return; }

	if (request.mode === 'navigate' && url.pathname === '/pos') {
		event.respondWith(
			fetch(request).then(function (response) {
				if (response.ok && !response.redirected) {
					var copy = response.clone();
					caches.open(PAGES).then(function (cache) { cache.put('/pos', copy); });
				}
				return response;
			}).catch(function () {
				return caches.open(PAGES).then(function (cache) { return cache.match('/pos'); }).then(function (cached) {
					return cached || offlinePage();
				});
			})
		);
		return;
	}

	if (isStatic(url)) {
		event.respondWith(
			caches.open(STATIC).then(function (cache) {
				return cache.match(request).then(function (cached) {
					var network = fetch(request).then(function (response) {
						if (response.ok) { cache.put(request, response.clone()); }
						return response;
					}).catch(function () { return cached; });
					return cached || network;
				});
			})
		);
	}
});
