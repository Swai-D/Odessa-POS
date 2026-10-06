/*
 * Sends ESC/POS bytes from the server straight to a thermal printer using Web Serial (USB-serial or
 * Bluetooth-paired printers) or WebUSB. Works in Chrome/Edge on https or localhost only.
 */
(function () {
	'use strict';

	var BAUD_RATE = 9600;
	var CHUNK = 512;

	function decode(b64) {
		var binary = atob(b64);
		var bytes = new Uint8Array(binary.length);
		for (var i = 0; i < binary.length; i++) { bytes[i] = binary.charCodeAt(i); }
		return bytes;
	}

	async function sendSerial(bytes) {
		if (!('serial' in navigator)) { throw new Error('unsupported'); }
		var ports = await navigator.serial.getPorts();
		var port = ports.length ? ports[0] : await navigator.serial.requestPort();
		await port.open({ baudRate: BAUD_RATE });
		try {
			var writer = port.writable.getWriter();
			try {
				for (var i = 0; i < bytes.length; i += CHUNK) { await writer.write(bytes.slice(i, i + CHUNK)); }
			} finally { writer.releaseLock(); }
		} finally { await port.close(); }
	}

	async function sendUsb(bytes) {
		if (!('usb' in navigator)) { throw new Error('unsupported'); }
		var devices = await navigator.usb.getDevices();
		var device = devices.length ? devices[0] : await navigator.usb.requestDevice({ filters: [] });
		await device.open();
		if (device.configuration === null) { await device.selectConfiguration(1); }

		var target = null;
		device.configuration.interfaces.forEach(function (iface) {
			iface.alternates.forEach(function (alt) {
				alt.endpoints.forEach(function (ep) {
					// Prefer the printer class (7); otherwise take the first bulk OUT endpoint.
					if (ep.direction === 'out' && ep.type === 'bulk' && (target === null || alt.interfaceClass === 7)) {
						target = { iface: iface.interfaceNumber, endpoint: ep.endpointNumber };
					}
				});
			});
		});
		if (target === null) { throw new Error('No printer endpoint found'); }

		await device.claimInterface(target.iface);
		try {
			for (var i = 0; i < bytes.length; i += CHUNK) { await device.transferOut(target.endpoint, bytes.slice(i, i + CHUNK)); }
		} finally { await device.releaseInterface(target.iface); }
	}

	async function print(url) {
		var response = await fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
		if (!response.ok) { throw new Error('HTTP ' + response.status); }
		var job = await response.json();
		var bytes = decode(job.data);
		for (var copy = 0; copy < job.copies; copy++) {
			if (job.connection === 'usb') { await sendUsb(bytes); } else { await sendSerial(bytes); }
		}
	}

	document.addEventListener('click', function (event) {
		var button = event.target.closest('#print-thermal');
		if (!button) { return; }
		var i18n = window.ESCPOS_I18N || { failed: 'Could not print: :message', unsupported: '' };

		button.disabled = true;
		print(button.dataset.url).catch(function (error) {
			var message = error.message === 'unsupported' ? i18n.unsupported : i18n.failed.replace(':message', error.message);
			window.alert(message);
		}).finally(function () { button.disabled = false; });
	});
})();
