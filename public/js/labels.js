/*
 * Barcode label printing. Builds labels from PosBarcode, shows a preview, and prints them from a hidden frame with an
 * exact @page size (thermal rolls: one label per page; A4 sheets: a grid, optionally skipping used positions).
 */
(function () {
	'use strict';

	var config = window.labelsConfig;
	var barcode = window.PosBarcode;
	var picker = document.querySelector('.js-product-picker');
	var search = document.getElementById('label-search');
	var tbody = document.querySelector('#label-table tbody');
	var items = [];

	// Millimetres. thermal: one label per page. sheet: A4 grid.
	var SIZES = {
		t50x30: { kind: 'roll', w: 50, h: 30 },
		t40x25: { kind: 'roll', w: 40, h: 25 },
		t58x40: { kind: 'roll', w: 58, h: 40 },
		a3x8: { kind: 'sheet', cols: 3, rows: 8 },
		a4x10: { kind: 'sheet', cols: 4, rows: 10 },
		a5x13: { kind: 'sheet', cols: 5, rows: 13 }
	};

	function escapeHtml(value) {
		return String(value).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; });
	}

	function codeOf(item) { return (item.barcode || item.sku || '').trim(); }
	function option(id) { return document.getElementById(id).checked; }
	function size() { return SIZES[document.getElementById('label-size').value]; }

	function labelSize() {
		var s = size();
		return s.kind === 'roll' ? { w: s.w, h: s.h } : { w: 210 / s.cols, h: 297 / s.rows };
	}

	function labelHtml(item) {
		var encoded = barcode.encode(codeOf(item));
		var lines = '';
		if (option('opt-shop')) { lines += '<div class="shop">' + escapeHtml(config.business) + '</div>'; }
		if (option('opt-name')) { lines += '<div class="name">' + escapeHtml(item.name) + '</div>'; }
		var bars = encoded ? '<div class="bars">' + barcode.toSvg(encoded, 50) + '</div><div class="code">' + escapeHtml(encoded.text) + '</div>' : '<div class="code">' + escapeHtml(config.i18n.noCode) + '</div>';
		var foot = '';
		if (option('opt-sku') && item.sku) { foot += '<span>' + escapeHtml(item.sku) + '</span>'; }
		if (option('opt-price')) { foot += '<b>' + escapeHtml(item.price_text) + '</b>'; }
		return '<div class="label">' + lines + bars + (foot ? '<div class="foot">' + foot + '</div>' : '') + '</div>';
	}

	function css() {
		var dim = labelSize();
		var s = size();
		var page = s.kind === 'roll' ? '@page{size:' + s.w + 'mm ' + s.h + 'mm;margin:0}' : '@page{size:A4;margin:0}';
		var grid = s.kind === 'roll' ? '' : '.sheet{display:grid;grid-template-columns:repeat(' + s.cols + ',' + dim.w + 'mm);grid-auto-rows:' + dim.h + 'mm}';
		var page_break = s.kind === 'roll' ? '.label{page-break-after:always;break-after:page}' : '';
		return page + 'html,body{margin:0;padding:0;background:#fff}' + grid + page_break +
			'.label{box-sizing:border-box;width:' + dim.w + 'mm;height:' + dim.h + 'mm;padding:1.5mm 2mm;overflow:hidden;display:flex;flex-direction:column;align-items:stretch;justify-content:center;font-family:Arial,Helvetica,sans-serif;color:#000;text-align:center;line-height:1.15}' +
			'.shop{font-size:6pt;text-transform:uppercase;letter-spacing:.04em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}' +
			'.name{font-size:7.5pt;font-weight:bold;max-height:2.3em;overflow:hidden}' +
			'.bars{flex:1 1 auto;min-height:6mm;margin:.6mm 0}.bars svg{display:block;width:100%;height:100%}' +
			'.code{font-size:6.5pt;letter-spacing:.08em;font-family:"Courier New",monospace}' +
			'.foot{display:flex;justify-content:space-between;align-items:baseline;gap:2mm;font-size:6.5pt}.foot b{font-size:9pt;margin-left:auto}' +
			'.blank{box-sizing:border-box}';
	}

	function allLabels() {
		var out = [];
		items.forEach(function (item) {
			for (var i = 0; i < item.qty; i++) { out.push(labelHtml(item)); }
		});
		return out;
	}

	function body() {
		var s = size();
		var labels = allLabels();
		if (s.kind === 'sheet') {
			var skip = Math.max(0, Math.min(parseInt(document.getElementById('label-skip').value, 10) || 0, s.cols * s.rows - 1));
			for (var i = 0; i < skip; i++) { labels.unshift('<div class="blank"></div>'); }
			return '<div class="sheet">' + labels.join('') + '</div>';
		}
		return labels.join('');
	}

	function render() {
		var empty = items.length === 0;
		document.getElementById('label-empty').classList.toggle('d-none', !empty);
		document.getElementById('label-skip-wrap').classList.toggle('d-none', size().kind !== 'sheet');
		tbody.innerHTML = '';
		var unreadable = [];
		var total = 0;
		items.forEach(function (item, index) {
			total += item.qty;
			if (codeOf(item) === '' || !barcode.encode(codeOf(item))) { unreadable.push(item.name); }
			var row = document.createElement('tr');
			row.innerHTML = '<td>' + escapeHtml(item.name) + '</td><td>' + escapeHtml(codeOf(item) || '—') + '</td>' +
				'<td><input type="number" min="1" max="500" class="form-control form-control-sm" value="' + item.qty + '"></td>' +
				'<td><a href="javascript:void(0);" title="' + escapeHtml(config.i18n.remove) + '"><i class="ti ti-trash"></i></a></td>';
			row.querySelector('input').addEventListener('input', function (event) {
				item.qty = Math.max(1, Math.min(500, parseInt(event.target.value, 10) || 1));
				preview();
			});
			row.querySelector('a').addEventListener('click', function () { items.splice(index, 1); render(); });
			tbody.appendChild(row);
		});
		var warn = document.getElementById('label-warn');
		warn.classList.toggle('d-none', unreadable.length === 0);
		warn.textContent = unreadable.length ? config.i18n.unreadable + ' ' + unreadable.join(', ') : '';
		document.getElementById('label-print').disabled = empty;
		preview(total);
	}

	function preview(total) {
		if (typeof total !== 'number') {
			total = items.reduce(function (sum, item) { return sum + item.qty; }, 0);
		}
		document.getElementById('label-total').textContent = config.i18n.total.replace(':count', total);
		var target = document.getElementById('label-preview');
		if (items.length === 0) { target.innerHTML = ''; return; }
		var dim = labelSize();
		// Preview shows the first label of the first product, at roughly real size (3.78 px per mm, scaled up a little).
		target.innerHTML = '<style>#label-preview .label-box{display:inline-block;border:1px dashed #adb5bd;transform-origin:top left}' + css().replace(/@page\{[^}]*\}/g, '').replace(/html,body\{[^}]*\}/, '').replace(/\.sheet\{[^}]*\}/, '') + '</style>' +
			'<div class="label-box" style="zoom:' + (dim.w < 60 ? 1.6 : 1.3) + ';">' + labelHtml(items[0]) + '</div>';
	}

	function add(item) {
		var found = items.filter(function (existing) { return existing.id === item.id; })[0];
		if (found) {
			found.qty += 1;
		} else {
			items.push({ id: item.id, name: item.name, sku: item.sku, barcode: item.barcode, price_text: item.price_text, stock: item.stock, qty: 1 });
		}
		render();
	}

	function print() {
		if (items.length === 0) { return; }
		var frame = document.createElement('iframe');
		frame.setAttribute('aria-hidden', 'true');
		frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;';
		document.body.appendChild(frame);
		var doc = frame.contentWindow.document;
		doc.open();
		doc.write('<!doctype html><html><head><meta charset="utf-8"><title>Labels</title><style>' + css() + '</style></head><body>' + body() + '</body></html>');
		doc.close();
		setTimeout(function () {
			try {
				frame.contentWindow.focus();
				frame.contentWindow.print();
			} catch (error) {
				window.alert(config.i18n.popup);
			}
			setTimeout(function () { document.body.removeChild(frame); }, 60000);
		}, 250);
	}

	// Picking from the search box: product-picker.js resolves the datalist choice into hidden id; we also need the item,
	// so listen for the choice ourselves using the same lookup the picker uses.
	function choose() {
		var label = search.value;
		if (!label) { return; }
		fetch(picker.dataset.url + '?q=' + encodeURIComponent(label), { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
			.then(function (response) { return response.ok ? response.json() : { data: [] }; })
			.then(function (result) {
				var match = (result.data || []).filter(function (entry) { return entry.label === label; })[0];
				if (match) {
					add(match);
					search.value = '';
				}
			});
	}

	search.addEventListener('change', choose);
	['label-size', 'label-skip', 'opt-shop', 'opt-name', 'opt-price', 'opt-sku'].forEach(function (id) {
		document.getElementById(id).addEventListener('input', function () { render(); });
		document.getElementById(id).addEventListener('change', function () { render(); });
	});
	document.getElementById('label-print').addEventListener('click', print);
	document.getElementById('label-clear').addEventListener('click', function () { items = []; render(); });
	document.getElementById('label-fill-stock').addEventListener('click', function () {
		items.forEach(function (item) {
			if (item.stock !== null && item.stock !== undefined) { item.qty = Math.max(1, Math.min(500, Math.round(item.stock))); }
		});
		render();
	});

	config.initial.forEach(function (item) { items.push({ id: item.id, name: item.name, sku: item.sku, barcode: item.barcode, price_text: item.price_text, stock: item.stock, qty: 1 }); });
	render();
}());
