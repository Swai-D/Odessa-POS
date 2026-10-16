/*
 * Odessa POS till. Binds the Dreams POS markup (resources/views/pos/index.blade.php) to live data.
 * The browser only sends product ids and quantities; the server recomputes prices, tax and totals.
 * Money is handled in integer minor units, using the same calculator as the server (pos-calc.js).
 */
(function () {
	'use strict';

	var CFG = window.POS;
	if (!CFG) { return; }
	var T = CFG.i18n;
	var MM = CFG.mobileMoney || { enabled: false };
	var OFF = CFG.offline || {};
	var OT = OFF.i18n || {};
	var store = window.PosOffline;

	var state = {
		items: [],
		customerId: '',
		discount: { type: 'none', value: 0 },
		category: '',
		busy: false,
		lastAttempt: null,
		pay: { rows: [] },
		online: navigator.onLine !== false,
		queue: [],
		syncing: false,
		syncMessage: '',
		snapshots: {}
	};
	var cache = {};
	var searchTimer = null;
	var productSearchId = 0;

	function $(selector, root) { return (root || document).querySelector(selector); }
	function $all(selector, root) { return Array.prototype.slice.call((root || document).querySelectorAll(selector)); }
	function esc(value) {
		return String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}
	function fmt(minor) {
		var sign = minor < 0 ? '-' : '';
		return sign + CFG.currency + ' ' + (Math.abs(minor) / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	}
	function qtyText(value) { return String(Math.round(value * 1000) / 1000); }
	function toMinor(text) {
		var value = parseFloat(String(text).replace(/,/g, ''));
		return isNaN(value) || value < 0 ? 0 : Math.round(value * 100);
	}
	function uuid() {
		if (window.crypto && window.crypto.randomUUID) { return window.crypto.randomUUID(); }
		return 'k' + Date.now().toString(36) + Math.random().toString(36).slice(2, 12);
	}
	function modal(id) { return window.bootstrap.Modal.getOrCreateInstance(document.getElementById(id)); }

	function request(method, url, body) {
		var options = {
			method: method,
			credentials: 'same-origin',
			headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': CFG.csrf }
		};
		if (body !== undefined) {
			options.headers['Content-Type'] = 'application/json';
			options.body = JSON.stringify(body);
		}
		return fetch(url, options).then(function (response) {
			return response.json().catch(function () { return {}; }).then(function (data) {
				return { ok: response.ok, status: response.status, data: data };
			});
		});
	}

	function errorMessages(result) {
		if (result.status === 419) { return [CFG.sessionExpired]; }
		if (result.data && result.data.errors) {
			return Object.keys(result.data.errors).reduce(function (all, key) { return all.concat(result.data.errors[key]); }, []);
		}
		return [(result.data && result.data.message) || T.network_error];
	}

	function alertMessage(messages, type) {
		var box = $('#pos-alert');
		if (!messages || !messages.length) { box.style.display = 'none'; box.innerHTML = ''; return; }
		box.className = 'alert alert-' + (type || 'danger') + ' py-2 px-3 fs-13 mb-2';
		box.innerHTML = [].concat(messages).map(esc).join('<br>');
		box.style.display = '';
	}

	/* ---------- cart ---------- */

	function calcInput() {
		return state.items.map(function (item) {
			return { quantity: item.qty, unit_price: item.price, tax_rate: item.tax_rate, tax_inclusive: item.tax_inclusive };
		});
	}
	function discountInput() {
		return { type: state.discount.type, value: state.discount.value };
	}
	function totals() { return window.PosCalc.calculate(calcInput(), discountInput()); }

	function findItem(id) {
		return state.items.filter(function (item) { return item.id === id; })[0];
	}

	function addProduct(product, quantity) {
		quantity = quantity || 1;
		cache[product.id] = product;
		var item = findItem(product.id);
		var next = (item ? item.qty : 0) + quantity;

		if (product.track_stock && product.stock !== null && next > product.stock) {
			alertMessage(product.name + ': ' + T.out_of_stock + (product.stock > 0 ? ' (' + qtyText(product.stock) + ' ' + T.in_stock + ')' : ''), 'warning');
			return;
		}
		alertMessage(null);

		if (item) {
			item.qty = next;
		} else {
			state.items.push({
				id: product.id, name: product.name, sku: product.sku, price: product.price, tax_rate: product.tax_rate,
				tax_inclusive: product.tax_inclusive, track_stock: product.track_stock, stock: product.stock,
				allow_decimal: product.allow_decimal, unit: product.unit, qty: quantity
			});
		}
		render();
	}

	function setQty(id, value) {
		var item = findItem(id);
		if (!item) { return; }
		var qty = parseFloat(value);
		if (isNaN(qty) || qty <= 0) { state.items = state.items.filter(function (i) { return i.id !== id; }); render(); return; }
		if (!item.allow_decimal) { qty = Math.max(1, Math.round(qty)); }
		qty = Math.round(qty * 1000) / 1000;
		if (item.track_stock && item.stock !== null && qty > item.stock) {
			alertMessage(item.name + ': ' + T.out_of_stock + ' (' + qtyText(item.stock) + ' ' + T.in_stock + ')', 'warning');
			qty = item.stock;
		}
		item.qty = qty;
		render();
	}

	function clearCart() {
		state.items = [];
		state.discount = { type: 'none', value: 0 };
		state.lastAttempt = null;
		alertMessage(null);
		render();
	}

	/* ---------- rendering ---------- */

	function render() {
		var result = totals();
		var empty = state.items.length === 0;

		$('#pos-empty').style.display = empty ? 'flex' : 'none';
		$('#pos-list').style.display = empty ? 'none' : 'block';
		$('#pos-items-count').textContent = state.items.length;

		$('#pos-cart-body').innerHTML = state.items.map(function (item, i) {
			var line = result.lines[i];
			return '<tr data-id="' + item.id + '">' +
				'<td><div class="d-flex align-items-center">' +
				'<a class="delete-icon" href="javascript:void(0);" data-pos-remove="' + item.id + '"><i class="ti ti-trash-x-filled"></i></a>' +
				'<h6 class="fs-13 fw-normal"><span class="link-default">' + esc(item.name) + '</span></h6></div></td>' +
				'<td><div class="qty-item m-0">' +
				'<a href="javascript:void(0);" class="dec dark d-flex justify-content-center align-items-center" data-pos-qty="-1"><i class="ti ti-minus"></i></a>' +
				'<input type="text" class="form-control text-center" name="qty" value="' + qtyText(item.qty) + '" inputmode="decimal" data-pos-qty-input>' +
				'<a href="javascript:void(0);" class="inc dark d-flex justify-content-center align-items-center" data-pos-qty="1"><i class="ti ti-plus"></i></a>' +
				'</div></td>' +
				'<td class="fs-13 fw-semibold text-gray-9 text-end">' + fmt(line.total) + '</td></tr>';
		}).join('');

		$('#pos-subtotal').textContent = fmt(result.subtotal);
		$('#pos-discount-total').textContent = result.discount_total > 0 ? '-' + fmt(result.discount_total) : fmt(0);
		$('#pos-tax').textContent = fmt(result.tax_total);
		$('#pos-total').textContent = fmt(result.total);
		$('#pos-pay-total').textContent = fmt(result.total);

		var chip = $('#pos-discount-chip');
		chip.style.display = result.discount_total > 0 ? '' : 'none';
		$('#pos-discount-label').textContent = state.discount.type === 'percent'
			? T.discount + ' ' + state.discount.value + '%'
			: T.discount + ' ' + fmt(result.discount_total);

		$all('#pos-grid .product-info').forEach(function (tile) {
			tile.classList.toggle('active', !!findItem(parseInt(tile.getAttribute('data-id'), 10)));
		});
	}

	function renderProducts(products) {
		var grid = $('#pos-grid');
		if (!products.length) {
			grid.innerHTML = '<div class="col-12"><p class="text-center text-muted py-5">' + esc(T.no_results) + '</p></div>';
			return;
		}
		grid.innerHTML = products.map(function (p) {
			cache[p.id] = p;
			var out = p.track_stock && p.stock !== null && p.stock <= 0;
			var stock = p.track_stock && p.stock !== null
				? '<span class="badge ' + (out ? 'bg-danger' : 'bg-light text-gray-9 border') + ' fs-10">' + (out ? esc(T.out_of_stock) : qtyText(p.stock) + (p.unit ? ' ' + esc(p.unit) : '')) + '</span>'
				: '';
			return '<div class="col-sm-6 col-md-6 col-lg-6 col-xl-4 col-xxl-3">' +
				'<div class="product-info card mb-0' + (findItem(p.id) ? ' active' : '') + '" data-id="' + p.id + '" style="' + (out ? 'opacity:.55;' : '') + '">' +
				'<a href="javascript:void(0);" class="pro-img">' +
				'<div class="d-flex align-items-center justify-content-center fw-bold text-gray-9 bg-light rounded" style="height:96px;font-size:28px;">' + esc(p.initials) + '</div>' +
				'<span><i class="ti ti-circle-check-filled"></i></span></a>' +
				'<h6 class="cat-name"><a href="javascript:void(0);">' + esc(p.category || '') + '</a></h6>' +
				'<h6 class="product-name"><a href="javascript:void(0);">' + esc(p.name) + '</a></h6>' +
				'<div class="d-flex align-items-center justify-content-between price"><p class="text-gray-9 mb-0">' + fmt(p.price) + '</p>' + stock + '</div>' +
				'</div></div>';
		}).join('');
	}

	function loadProducts(done) {
		var searchId = ++productSearchId;
		var params = new URLSearchParams();
		var term = $('#pos-search').value.trim();
		if (term) { params.set('q', term); }
		if (state.category) { params.set('category', state.category); }
		params.set('warehouse', currentWarehouse());
		if (!state.online) { return loadProductsOffline(searchId, done); }
		return request('GET', CFG.routes.products + '?' + params.toString()).then(function (result) {
			if (searchId !== productSearchId) { return; }
			if (result.ok) {
				alertMessage(null);
				renderProducts(result.data.data || []);
				if (done) { done(result.data.data || []); }
			} else {
				alertMessage(errorMessages(result));
			}
		}).catch(function () {
			if (searchId !== productSearchId) { return; }
			goOffline();
			return loadProductsOffline(searchId, done);
		});
	}

	function currentWarehouse() {
		var select = $('#pos-warehouse');
		return select ? select.value : CFG.warehouseId;
	}

	/* ---------- customers ---------- */

	function selectedCustomer() { return $('#pos-customer').value; }

	function customerLabel(customer) { return customer.name + (customer.phone ? ' (' + customer.phone + ')' : ''); }

	// Replace the picker's options with search results, always keeping walk-in and the current choice.
	function fillCustomers(list) {
		var select = $('#pos-customer');
		var current = select.value;
		var currentOption = current ? select.querySelector('option[value="' + current + '"]') : null;
		var keep = currentOption ? { id: current, label: currentOption.textContent } : null;
		select.innerHTML = '<option value="">' + esc(CFG.walkIn) + '</option>';
		if (keep) { appendCustomer(select, keep.id, keep.label); }
		list.forEach(function (customer) {
			if (String(customer.id) !== String(current)) { appendCustomer(select, customer.id, customerLabel(customer)); }
		});
		select.value = current;
	}

	function appendCustomer(select, id, label) {
		var option = document.createElement('option');
		option.value = id;
		option.textContent = label;
		select.appendChild(option);
	}

	var customerTimer = null;
	var customerSearchId = 0;
	function renderCustomerResults(list, term) {
		var results = $('#pos-customer-results');
		if (!term) { results.classList.remove('show'); results.innerHTML = ''; return; }
		if (!list.length) {
			results.innerHTML = '<span class="dropdown-item-text text-muted">' + esc(T.no_customers_found) + '</span>';
		} else {
			results.innerHTML = list.map(function (customer) {
				cache['customer:'+customer.id] = customer;
				return '<button type="button" class="dropdown-item text-wrap text-start" role="option" data-pos-customer-result="' + customer.id + '">' + esc(customerLabel(customer)) + '</button>';
			}).join('');
		}
		results.classList.add('show');
	}

	function searchCustomers(term) {
		var searchId = ++customerSearchId;
		if (term) {
			var results = $('#pos-customer-results');
			results.innerHTML = '<span class="dropdown-item-text text-muted">' + esc(T.searching_customers) + '</span>';
			results.classList.add('show');
		}
		if (!state.online) { searchCustomersOffline(term, searchId); return; }
		request('GET', CFG.routes.customerSearch + '?q=' + encodeURIComponent(term)).then(function (result) {
			if (searchId !== customerSearchId) { return; }
			if (result.ok) {
				fillCustomers(result.data.data || []);
				renderCustomerResults(result.data.data || [], term);
			} else {
				renderCustomerResults([], term);
				if (term) { $('#pos-customer-results').innerHTML = '<span class="dropdown-item-text text-danger">' + esc(T.network_error) + '</span>'; }
			}
		}).catch(function () {
			if (searchId !== customerSearchId) { return; }
			goOffline();
			searchCustomersOffline(term, searchId);
		});
	}

	// A held order may point at a customer the picker has not loaded yet.
	function ensureCustomer(id) {
		if (!id) { $('#pos-customer').value = ''; return; }
		var select = $('#pos-customer');
		if (select.querySelector('option[value="' + id + '"]')) { select.value = String(id); return; }
		request('GET', CFG.routes.customerSearch + '?id=' + encodeURIComponent(id)).then(function (result) {
			var found = result.ok && result.data.data && result.data.data[0];
			if (found) { appendCustomer(select, found.id, customerLabel(found)); select.value = String(found.id); updatePaySummary(); }
		});
	}

	/* ---------- payment modal ---------- */

	function payRow(method, amountMinor) {
		return {
			method: method, amount: amountMinor > 0 ? (amountMinor / 100).toFixed(2) : '', reference: '',
			provider: MM.enabled && MM.providers ? MM.providers[0] : '', phone: '',
			mmKey: null, requestId: null, mmStatus: null, mmMessage: '', timer: null
		};
	}

	/* ---------- mobile money prompt (AzamPay) ---------- */

	function hasMm(row) { return MM.enabled && row.method === 'mobile_money'; }
	function mmLocked(row) { return row.mmStatus === 'pending' || row.mmStatus === 'succeeded'; }
	function stopPolling(row) { if (row.timer) { window.clearInterval(row.timer); row.timer = null; } }
	function stopAllPolling() { state.pay.rows.forEach(stopPolling); }

	function mmHtml(row) {
		var M = MM.i18n;
		var message = row.mmStatus ? (MM.statuses[row.mmStatus] || '') : '';
		if (row.mmStatus === 'failed' && row.mmMessage) { message += ': ' + row.mmMessage; }
		if (!row.mmStatus && row.mmMessage) { message = row.mmMessage; }
		var cls = row.mmStatus === 'succeeded' ? 'text-success' : (row.mmStatus === 'pending' ? 'text-muted' : 'text-danger');
		var providers = MM.providers.map(function (p) {
			return '<option value="' + esc(p) + '"' + (p === row.provider ? ' selected' : '') + '>' + esc(p) + '</option>';
		}).join('');
		var locked = mmLocked(row) ? ' disabled' : '';
		var button = row.mmStatus === 'succeeded' ? '' :
			'<button type="button" class="btn btn-sm btn-primary" data-mm-request="1"' + (row.mmStatus === 'pending' ? ' disabled' : '') + '>' +
			esc(row.mmStatus === 'failed' || row.mmStatus === 'expired' ? M.retry : M.request) + '</button>';
		return '<div class="col-4"><select class="form-select form-select-sm" data-pay-field="provider"' + locked + ' aria-label="' + esc(M.provider) + '">' + providers + '</select></div>' +
			'<div class="col-4"><input type="tel" class="form-control form-control-sm" maxlength="20" data-pay-field="phone"' + locked + ' placeholder="' + esc(M.phone) + '" value="' + esc(row.phone) + '"></div>' +
			'<div class="col-4">' + button + '</div>' +
			(message ? '<div class="col-12 small ' + cls + '">' + esc(message) + '</div>' : '');
	}

	function refreshMm(index) {
		var holder = document.querySelector('#pos-pay-rows [data-mm="' + index + '"]');
		var row = state.pay.rows[index];
		if (holder && row) { holder.innerHTML = mmHtml(row); }
		var line = document.querySelector('#pos-pay-rows [data-row="' + index + '"]');
		if (line && row) {
			$all('[data-pay-field="method"],[data-pay-field="amount"],[data-pay-field="reference"]', line).forEach(function (input) { input.disabled = mmLocked(row); });
		}
		updatePaySummary();
	}

	function pollMm(row) {
		stopPolling(row);
		row.timer = window.setInterval(function () {
			var index = state.pay.rows.indexOf(row);
			if (index < 0) { stopPolling(row); return; }
			request('GET', MM.showBase + '/' + row.requestId).then(function (result) {
				if (!result.ok || state.pay.rows.indexOf(row) < 0) { return; }
				applyMm(row, result.data);
			}).catch(function () { /* keep polling; the next tick tries again */ });
		}, 3000);
	}

	function applyMm(row, data) {
		row.mmStatus = data.status;
		row.mmMessage = data.reason || '';
		if (data.status !== 'pending') {
			stopPolling(row);
			row.mmKey = data.status === 'succeeded' ? row.mmKey : null;
			if (data.status !== 'succeeded') { row.requestId = null; }
		}
		refreshMm(state.pay.rows.indexOf(row));
	}

	function requestMm(index) {
		var row = state.pay.rows[index];
		if (!row) { return; }
		if (!state.online) { row.mmMessage = OT.mm_offline; refreshMm(index); return; }
		var amount = toMinor(row.amount);
		if (row.mmStatus === 'pending' || row.mmStatus === 'succeeded') { return; }
		if (!row.provider || !row.phone.trim() || amount < 100) { row.mmMessage = MM.i18n.need_fields; refreshMm(index); return; }
		// A failed or expired request starts over with a new key; a repeat of one still in doubt reuses its key.
		if (!row.mmKey) { row.mmKey = uuid(); }
		row.mmMessage = '';
		row.mmStatus = 'pending';
		refreshMm(index);
		request('POST', MM.store, { idempotency_key: row.mmKey, provider: row.provider, phone: row.phone.trim(), amount: amount }).then(function (result) {
			if (!result.ok) {
				row.mmStatus = null;
				row.mmMessage = errorMessages(result).join(' ') || MM.i18n.network_error;
				if (result.status >= 400 && result.status < 500) { row.mmKey = null; }
				refreshMm(index);
				return;
			}
			row.requestId = result.data.id;
			applyMm(row, result.data);
			if (result.data.status === 'pending') { pollMm(row); }
		}).catch(function () {
			row.mmStatus = null;
			row.mmMessage = MM.i18n.network_error;
			refreshMm(index);
		});
	}

	function openPay(method) {
		var result = totals();
		if (!state.items.length) { alertMessage(T.cart_empty, 'warning'); return; }
		if (result.total <= 0) { alertMessage(CFG.errors.nothing_to_charge, 'warning'); return; }
		stopAllPolling();
		if (method === 'credit') {
			state.pay.rows = [];
		} else if (method === 'split') {
			state.pay.rows = [payRow('cash', 0), payRow('mobile_money', 0)];
		} else {
			state.pay.rows = [payRow(method, result.total)];
		}
		renderPay();
		modal('pos-pay').show();
	}

	function settlePreview() {
		var total = totals().total;
		var cash = 0;
		var other = 0;
		state.pay.rows.forEach(function (row) {
			var amount = toMinor(row.amount);
			if (row.method === 'cash') { cash += amount; } else { other += amount; }
		});
		var error = null;
		state.pay.rows.forEach(function (row) {
			if (!hasMm(row) || error) { return; }
			if (row.mmStatus === 'pending') { error = MM.i18n.waiting_block; }
			else if (row.mmStatus === 'failed' || row.mmStatus === 'expired') { error = MM.i18n.unfinished_block; }
		});
		if (!error && other > total) { error = CFG.errors.overpaid_non_cash; }
		var dueForCash = Math.max(total - other, 0);
		var cashApplied = Math.min(cash, dueForCash);
		var paid = other + cashApplied;
		var balance = total - paid;
		if (!error && balance > 0 && !selectedCustomer()) { error = T.select_customer_for_credit; }
		return { total: total, paid: paid, change: cash - cashApplied, balance: balance, error: error };
	}

	function renderPay() {
		var methods = CFG.methods.map(function (m) { return '<option value="' + m + '">' + esc(CFG.methodLabels[m]) + '</option>'; }).join('');
		$('#pos-pay-rows').innerHTML = state.pay.rows.map(function (row, i) {
			var off = mmLocked(row) ? ' disabled' : '';
			return '<div class="row g-2 align-items-end mb-2" data-row="' + i + '">' +
				'<div class="col-4"><select class="form-select" data-pay-field="method"' + off + '>' + methods.replace('value="' + row.method + '"', 'value="' + row.method + '" selected') + '</select></div>' +
				'<div class="col-3"><input type="text" inputmode="decimal" class="form-control" data-pay-field="amount"' + off + ' placeholder="' + esc(T.amount) + '" value="' + esc(row.amount) + '"></div>' +
				'<div class="col-4"><input type="text" class="form-control" maxlength="100" data-pay-field="reference"' + off + ' placeholder="' + esc(T.reference) + '" value="' + esc(row.reference) + '"></div>' +
				'<div class="col-1"><a href="javascript:void(0);" class="link-danger fs-18" data-pay-remove="' + i + '"><i class="ti ti-trash"></i></a></div>' +
				(hasMm(row) ? '<div class="col-12"><div class="row g-2 align-items-center" data-mm="' + i + '">' + mmHtml(row) + '</div></div>' : '') +
				'</div>';
		}).join('');
		updatePaySummary();
	}

	function updatePaySummary() {
		var preview = settlePreview();
		$('#pos-pay-paid').textContent = fmt(preview.paid);
		$('#pos-pay-change').textContent = fmt(preview.change);
		$('#pos-pay-balance').textContent = fmt(preview.balance);
		$('#pos-pay-balance-row').className = 'd-flex justify-content-between fw-bold ' + (preview.balance > 0 ? 'text-danger' : '');
		var error = $('#pos-pay-error');
		error.textContent = preview.error || '';
		error.style.display = preview.error ? '' : 'none';
		$('#pos-pay-confirm').disabled = state.busy || !!preview.error;
	}

	function checkoutPayload() {
		return {
			customer_id: selectedCustomer() || null,
			warehouse_id: parseInt(currentWarehouse(), 10),
			items: state.items.map(function (item) { return { product_id: item.id, quantity: item.qty }; }),
			discount: state.discount.type === 'none' ? null : { type: state.discount.type, value: state.discount.value },
			payments: settlePayments()
		};
	}

	function settlePayments() {
		return state.pay.rows.map(function (row) {
			var payment = { method: row.method, amount: toMinor(row.amount), reference: row.reference || null };
			if (hasMm(row) && row.mmStatus === 'succeeded' && row.requestId) { payment.payment_request_id = row.requestId; }
			return payment;
		}).filter(function (payment) { return payment.amount > 0; });
	}

	function confirmPay() {
		if (state.busy) { return; }
		var payload = checkoutPayload();
		var signature = JSON.stringify(payload);
		// Retrying the same cart reuses the key (safe against double charging); a changed cart gets a new one.
		if (!state.lastAttempt || state.lastAttempt.signature !== signature) {
			state.lastAttempt = { signature: signature, key: uuid() };
		}
		payload.idempotency_key = state.lastAttempt.key;

		state.busy = true;
		updatePaySummary();
		if (!state.online && saveSaleOffline(payload)) { return; }
		request('POST', CFG.routes.checkout, payload).then(function (result) {
			state.busy = false;
			if (!result.ok) {
				updatePaySummary();
				var error = $('#pos-pay-error');
				error.textContent = errorMessages(result).join(' ');
				error.style.display = '';
				return;
			}
			modal('pos-pay').hide();
			showDone(result.data);
			clearCart();
			state.lastAttempt = null;
			loadProducts();
		}).catch(function () {
			// The connection dropped. If the server did get the sale, sending it again later with the same key returns it.
			goOffline();
			if (saveSaleOffline(payload)) { return; }
			state.busy = false;
			updatePaySummary();
			var error = $('#pos-pay-error');
			error.textContent = T.network_error;
			error.style.display = '';
		});
	}

	function showDone(sale, local) {
		var invoice = $('#pos-done-invoice');
		var receipt = $('#pos-done-receipt');
		$('#pos-done-offline-note').style.display = local ? '' : 'none';
		invoice.style.display = local ? 'none' : '';
		if (local) {
			receipt.removeAttribute('target');
			receipt.setAttribute('href', 'javascript:void(0);');
			receipt.setAttribute('data-local-receipt', local.key);
		} else {
			receipt.setAttribute('target', '_blank');
			receipt.removeAttribute('data-local-receipt');
		}
		$('#pos-done-number').textContent = sale.number;
		$('#pos-done-total').textContent = fmt(sale.total);
		$('#pos-done-change').textContent = fmt(sale.change_given);
		$('#pos-done-balance').textContent = fmt(sale.balance_due);
		$('#pos-done-balance-row').style.display = sale.balance_due > 0 ? '' : 'none';
		$('#pos-done-change-row').style.display = sale.change_given > 0 ? '' : 'none';
		if (!local) {
			receipt.setAttribute('href', sale.receipt_url + '?format=thermal');
			invoice.setAttribute('href', sale.receipt_url);
		}
		modal('pos-done').show();
	}

	/* ---------- hold / resume ---------- */

	function holdOrder() {
		if (!state.online) { alertMessage(OT.unavailable, 'warning'); return; }
		if (!state.items.length) { alertMessage(T.cart_empty, 'warning'); return; }
		$('#pos-hold-reference').value = '';
		modal('pos-hold').show();
	}

	function saveHold() {
		var body = {
			reference: $('#pos-hold-reference').value || null,
			customer_id: selectedCustomer() || null,
			items: state.items.map(function (item) { return { product_id: item.id, quantity: item.qty }; }),
			discount: state.discount.type === 'none' ? null : state.discount
		};
		request('POST', CFG.routes.heldStore, body).then(function (result) {
			if (!result.ok) { alertMessage(errorMessages(result)); modal('pos-hold').hide(); return; }
			modal('pos-hold').hide();
			clearCart();
		});
	}

	function openHeld() {
		if (!state.online) { alertMessage(OT.unavailable, 'warning'); return; }
		var body = $('#pos-held-body');
		body.innerHTML = '';
		request('GET', CFG.routes.heldIndex).then(function (result) {
			var rows = result.ok ? result.data.data : [];
			body.innerHTML = rows.length ? rows.map(function (order) {
				return '<tr><td>' + esc(order.reference || ('#' + order.id)) + '</td><td>' + esc(order.customer || CFG.walkIn) + '</td><td>' + order.items + '</td><td>' + esc(order.created_at) + '</td>' +
					'<td class="text-end text-nowrap"><a href="javascript:void(0);" class="btn btn-sm btn-primary me-1" data-held-resume="' + order.id + '">' + esc(T.resume) + '</a>' +
					'<a href="javascript:void(0);" class="btn btn-sm btn-danger" data-held-discard="' + order.id + '">' + esc(T.discard) + '</a></td></tr>';
			}).join('') : '<tr><td colspan="5" class="text-center text-muted py-4">' + esc(T.no_held) + '</td></tr>';
		});
		modal('pos-held').show();
	}

	function resumeHeld(id) {
		request('POST', CFG.routes.heldBase + '/' + id + '/resume').then(function (result) {
			if (!result.ok) { alertMessage(errorMessages(result)); return; }
			var held = result.data;
			var ids = held.items.map(function (item) { return item.product_id; });
			var params = new URLSearchParams();
			ids.forEach(function (productId) { params.append('ids[]', productId); });
			params.set('warehouse', currentWarehouse());
			request('GET', CFG.routes.products + '?' + params.toString()).then(function (productsResult) {
				var byId = {};
				(productsResult.data.data || []).forEach(function (p) { byId[p.id] = p; });
				state.items = [];
				held.items.forEach(function (item) {
					if (byId[item.product_id]) { addProduct(byId[item.product_id], parseFloat(item.quantity)); }
				});
				state.discount = held.discount || { type: 'none', value: 0 };
				ensureCustomer(held.customer_id);
				modal('pos-held').hide();
				render();
			});
		});
	}

	/* ---------- events ---------- */

	document.addEventListener('click', function (event) {
		var tile = event.target.closest ? event.target.closest('#pos-grid .product-info') : null;
		if (!tile) { return; }

		event.preventDefault();
		event.stopPropagation();
		var product = cache[parseInt(tile.getAttribute('data-id'), 10)];
		if (product) { addProduct(product, 1); }
	}, true);

	document.addEventListener('click', function (event) {
		var target = event.target;
		var closest = function (selector) { return target.closest ? target.closest(selector) : null; };

		var category = closest('[data-pos-category]');
		if (category) {
			state.category = category.getAttribute('data-pos-category');
			// The template's tab script hides the single tab pane on click; keep the grid visible.
			var pane = $('.tabs_container .tab_content');
			if (pane) { pane.classList.add('active'); }
			loadProducts();
			return;
		}

		var qty = closest('[data-pos-qty]');
		if (qty) {
			var row = closest('tr[data-id]');
			var item = findItem(parseInt(row.getAttribute('data-id'), 10));
			if (item) { setQty(item.id, item.qty + parseInt(qty.getAttribute('data-pos-qty'), 10)); }
			return;
		}

		var remove = closest('[data-pos-remove]');
		if (remove) {
			state.items = state.items.filter(function (i) { return i.id !== parseInt(remove.getAttribute('data-pos-remove'), 10); });
			render();
			return;
		}

		var action = closest('[data-pos-action]');
		if (action) {
			var name = action.getAttribute('data-pos-action');
			if (name === 'clear') { if (!state.items.length || window.confirm(T.confirm_clear)) { clearCart(); } }
			else if (name === 'hold') { holdOrder(); }
			else if (name === 'held') { openHeld(); }
			else if (name === 'remove-discount') { state.discount = { type: 'none', value: 0 }; render(); }
			else if (name === 'scan') { $('#pos-search').focus(); }
			else if (name === 'pay') { openPay('cash'); }
			return;
		}

		var pay = closest('[data-pos-pay]');
		if (pay) { openPay(pay.getAttribute('data-pos-pay')); return; }

		if (closest('#pos-pay-add')) {
			var remaining = Math.max(settlePreview().balance, 0);
			state.pay.rows.push(payRow('mobile_money', remaining));
			renderPay();
			return;
		}
		var payRemove = closest('[data-pay-remove]');
		if (payRemove) {
			var removeAt = parseInt(payRemove.getAttribute('data-pay-remove'), 10);
			var removing = state.pay.rows[removeAt];
			if (removing && hasMm(removing) && removing.mmStatus === 'succeeded' && !window.confirm(MM.i18n.remove_paid)) { return; }
			if (removing) { stopPolling(removing); }
			state.pay.rows.splice(removeAt, 1);
			renderPay();
			return;
		}
		var mmRequest = closest('[data-mm-request]');
		if (mmRequest) { requestMm(parseInt(mmRequest.closest('[data-row]').getAttribute('data-row'), 10)); return; }
		if (closest('#pos-pay-confirm')) { confirmPay(); return; }
		if (closest('#pos-hold-save')) { saveHold(); return; }
		var resume = closest('[data-held-resume]');
		if (resume) { resumeHeld(resume.getAttribute('data-held-resume')); return; }
		var discard = closest('[data-held-discard]');
		if (discard) {
			request('DELETE', CFG.routes.heldBase + '/' + discard.getAttribute('data-held-discard')).then(openHeld);
			return;
		}
		if (closest('#pos-discount-apply')) { applyDiscount(); return; }
		if (closest('#pos-customer-save')) { saveCustomer(); return; }
		if (closest('#pos-done-new')) { modal('pos-done').hide(); $('#pos-search').focus(); }
	});

	document.addEventListener('change', function (event) {
		var target = event.target;
		if (target.matches && target.matches('[data-pos-qty-input]')) {
			setQty(parseInt(target.closest('tr').getAttribute('data-id'), 10), target.value);
		}
		if (target.matches && target.matches('[data-pay-field]')) {
			var row = state.pay.rows[parseInt(target.closest('[data-row]').getAttribute('data-row'), 10)];
			row[target.getAttribute('data-pay-field')] = target.value;
			if (target.getAttribute('data-pay-field') === 'method') { stopPolling(row); row.mmStatus = null; row.requestId = null; row.mmKey = null; row.mmMessage = ''; renderPay(); return; }
			updatePaySummary();
		}
		if (target.id === 'pos-warehouse') { clearCart(); loadProducts(); }
		if (target.id === 'pos-customer') { state.lastAttempt = null; }
	});

	document.addEventListener('input', function (event) {
		var target = event.target;
		if (target.matches && target.matches('[data-pay-field]')) {
			var row = state.pay.rows[parseInt(target.closest('[data-row]').getAttribute('data-row'), 10)];
			row[target.getAttribute('data-pay-field')] = target.value;
			updatePaySummary();
		}
		if (target.id === 'pos-search') {
			window.clearTimeout(searchTimer);
			searchTimer = window.setTimeout(loadProducts, 250);
		}
	});

	// Enter in the search box adds an exact barcode/SKU match (barcode scanners type the code and press Enter).
	document.addEventListener('keydown', function (event) {
		if (event.target.id === 'pos-search' && event.key === 'Enter') {
			event.preventDefault();
			window.clearTimeout(searchTimer);
			var term = event.target.value.trim();
			if (!term) { return; }
			loadProducts(function (products) {
				var exact = products.filter(function (p) { return p.barcode === term || (p.sku || '').toLowerCase() === term.toLowerCase(); });
				var match = exact.length ? exact[0] : (products.length === 1 ? products[0] : null);
				if (match) { addProduct(match, 1); event.target.value = ''; loadProducts(); }
			});
		}
	});

	function applyDiscount() {
		var type = $('#pos-discount-type').value;
		var input = $('#pos-discount-value').value.trim().replace(/,/g, '');
		var error = $('#pos-discount-error');
		var value = Number(input);

		if (!input || !/^(?:\d+(?:\.\d*)?|\.\d+)$/.test(input) || !Number.isFinite(value)) {
			error.textContent = T.discount_invalid;
			error.style.display = '';
			return;
		}
		if (type === 'percent' && value > 100) {
			error.textContent = T.discount_percent_limit;
			error.style.display = '';
			return;
		}

		error.textContent = '';
		error.style.display = 'none';
		if (value === 0) { state.discount = { type: 'none', value: 0 }; }
		else if (type === 'percent') { state.discount = { type: 'percent', value: value }; }
		else { state.discount = { type: 'fixed', value: Math.round(value * 100) }; }
		modal('pos-discount').hide();
		render();
	}

	function saveCustomer() {
		var name = $('#pos-customer-name').value.trim();
		if (!name) { return; }
		if (!state.online) { var offlineError = $('#pos-customer-error'); offlineError.textContent = OT.unavailable; offlineError.style.display = ''; return; }
		request('POST', CFG.routes.customers, { name: name, phone: $('#pos-customer-phone').value.trim() || null }).then(function (result) {
			var error = $('#pos-customer-error');
			if (!result.ok) { error.textContent = errorMessages(result).join(' '); error.style.display = ''; return; }
			error.style.display = 'none';
			var select = $('#pos-customer');
			var option = document.createElement('option');
			option.value = result.data.id;
			option.textContent = result.data.name + (result.data.phone ? ' (' + result.data.phone + ')' : '');
			select.appendChild(option);
			select.value = String(result.data.id);
			$('#pos-customer-name').value = '';
			$('#pos-customer-phone').value = '';
			modal('pos-customer-modal').hide();
		});
	}

	document.addEventListener('show.bs.modal', function (event) {
		if (event.target.id !== 'pos-discount') { return; }
		var isFixed = state.discount.type === 'fixed';
		$('#pos-discount-type').value = isFixed ? 'fixed' : 'percent';
		$('#pos-discount-value').value = state.discount.type === 'none'
			? ''
			: isFixed
				? (state.discount.value / 100).toFixed(2).replace(/\.?0+$/, '')
				: String(state.discount.value);
		$('#pos-discount-error').textContent = '';
		$('#pos-discount-error').style.display = 'none';
	});

	document.addEventListener('hidden.bs.modal', function (event) {
		if (event.target.id === 'pos-pay') { stopAllPolling(); }
	});

	document.addEventListener('shown.bs.modal', function (event) {
		if (event.target.id === 'pos-pay') { var amount = $('#pos-pay-rows [data-pay-field="amount"]'); if (amount) { amount.focus(); amount.select(); } }
		if (event.target.id === 'pos-discount') { $('#pos-discount-value').focus(); }
	});

	// Customer changes affect the credit warning in the payment dialog.
	$('#pos-customer').addEventListener('change', updatePaySummary);
	$('#pos-customer-search').addEventListener('input', function () {
		var term = this.value.trim();
		clearTimeout(customerTimer);
		customerSearchId++;
		var results = $('#pos-customer-results');
		if (term) {
			results.innerHTML = '<span class="dropdown-item-text text-muted">' + esc(T.searching_customers) + '</span>';
			results.classList.add('show');
		} else {
			results.innerHTML = '';
			results.classList.remove('show');
		}
		customerTimer = setTimeout(function () { searchCustomers(term); }, 180);
	});
	document.addEventListener('click', function (event) {
		if (event.target.closest('#pos-customer-search, #pos-customer-results')) { return; }
		$('#pos-customer-results').classList.remove('show');
	});
	$('#pos-customer-results').addEventListener('click', function (event) {
		var option = event.target.closest('[data-pos-customer-result]');
		if (!option) { return; }
		var id = option.getAttribute('data-pos-customer-result');
		var select = $('#pos-customer');
		if (!select.querySelector('option[value="' + id + '"]')) {
			var customer = cache['customer:'+id];
			if (customer) { appendCustomer(select, customer.id, customerLabel(customer)); }
		}
		select.value = id;
		select.dispatchEvent(new Event('change', { bubbles: true }));
		$('#pos-customer-search').value = '';
		$('#pos-customer-results').classList.remove('show');
		$('#pos-customer-results').innerHTML = '';
	});

	/* ---------- offline: saved list, queue of sales, sync ---------- */

	function offlineReady() { return !!(store && store.available && OFF.tenant); }
	function fill(text, values) {
		return String(text || '').replace(/:(\w+)/g, function (all, key) { return values && values[key] !== undefined ? values[key] : all; });
	}
	function snapKey(warehouse) { return 'snapshot:' + OFF.tenant + ':' + warehouse; }
	function timeText(iso) { var d = new Date(iso); return isNaN(d) ? '' : d.toLocaleString(); }

	function goOffline() {
		if (!state.online) { return; }
		state.online = false;
		renderNet();
	}

	function setOnline() {
		if (state.online) { return; }
		state.online = true;
		renderNet();
		syncQueue();
		refreshSnapshots();
		loadProducts();
	}

	function renderNet() {
		var chip = $('#pos-net-text');
		if (!chip) { return; }
		var pending = state.queue.length;
		chip.textContent = state.online ? OT.online : OT.offline;
		$('#pos-net-dot').className = 'ti ti-circle-filled me-1 ' + (state.online ? 'text-success' : 'text-danger');
		var badge = $('#pos-net-pending');
		badge.style.display = pending ? '' : 'none';
		badge.textContent = fill(OT.pending, { count: pending });
		if ($('#pos-offline').classList.contains('show')) { renderQueue(); }
	}

	function loadQueue() {
		if (!offlineReady()) { return Promise.resolve(); }
		return store.queueAll(OFF.tenant).then(function (all) { state.queue = all; renderNet(); }).catch(function () {});
	}

	function renderQueue() {
		var rows = $('#pos-offline-rows');
		rows.innerHTML = state.queue.length ? state.queue.map(function (rec) {
			var rejected = rec.status === 'rejected';
			return '<tr><td>' + esc(timeText(rec.created_at)) + '<div class="small text-muted">' + esc(rec.summary.number) + '</div></td>' +
				'<td>' + fmt(rec.expected_total) + '</td>' +
				'<td>' + (rejected ? '<span class="badge bg-danger">' + esc(OT.status_rejected) + '</span><div class="small text-danger">' + esc(rec.message) + '</div>' : '<span class="badge bg-warning">' + esc(OT.status_pending) + '</span>') + '</td>' +
				'<td class="text-end text-nowrap">' + (rejected ? '<button type="button" class="btn btn-sm btn-white me-1" data-off-retry="' + esc(rec.key) + '">' + esc(OT.retry) + '</button>' : '') +
				'<button type="button" class="btn btn-sm btn-white" data-off-discard="' + esc(rec.key) + '">' + esc(OT.discard) + '</button></td></tr>';
		}).join('') : '<tr><td colspan="4" class="text-center text-muted py-3">' + esc(OT.queue_empty) + '</td></tr>';
		$('#pos-offline-info').textContent = OT.info;
		var message = $('#pos-offline-msg');
		message.textContent = state.syncMessage;
		message.style.display = state.syncMessage ? '' : 'none';
		var button = $('#pos-offline-sync');
		button.disabled = state.syncing || !state.online || !state.queue.length;
		button.textContent = state.syncing ? OT.syncing : OT.sync_now;
	}

	/* The saved product list (per warehouse), used when there is no connection. */

	function loadSnapshot(warehouse) {
		if (state.snapshots[warehouse]) { return Promise.resolve(state.snapshots[warehouse]); }
		if (!offlineReady()) { return Promise.resolve(null); }
		return store.get(snapKey(warehouse)).then(function (snap) {
			if (snap) { state.snapshots[warehouse] = snap; }
			return snap || null;
		}).catch(function () { return null; });
	}

	function refreshSnapshots() {
		if (!offlineReady() || !state.online) { return Promise.resolve(); }
		return (OFF.warehouses || []).reduce(function (chain, warehouse) {
			return chain.then(function () {
				return request('GET', OFF.snapshot + '?warehouse=' + encodeURIComponent(warehouse)).then(function (result) {
					if (!result.ok || !result.data.products) { return; }
					var snap = { at: result.data.generated_at, products: result.data.products, customers: result.data.customers || [] };
					state.snapshots[warehouse] = snap;
					return store.put(snapKey(warehouse), snap);
				}).catch(function () { /* try again at the next refresh */ });
			});
		}, Promise.resolve());
	}

	function rank(product, term) {
		var barcode = (product.barcode || '').toLowerCase();
		var sku = (product.sku || '').toLowerCase();
		var name = (product.name || '').toLowerCase();
		if (barcode === term) { return 0; }
		if (sku === term) { return 1; }
		if (name === term) { return 2; }
		if (barcode.indexOf(term) === 0) { return 3; }
		if (sku.indexOf(term) === 0) { return 4; }
		return 5;
	}

	function loadProductsOffline(searchId, done) {
		return loadSnapshot(currentWarehouse()).then(function (snap) {
			if (searchId !== productSearchId) { return; }
			if (!snap) { alertMessage(OT.no_snapshot, 'warning'); renderProducts([]); return; }
			var term = $('#pos-search').value.trim().toLowerCase();
			var list = snap.products.filter(function (p) {
				if (state.category && String(p.category_id) !== String(state.category)) { return false; }
				if (!term) { return true; }
				return [p.name, p.sku, p.barcode].some(function (v) { return v && String(v).toLowerCase().indexOf(term) >= 0; });
			});
			if (term) {
				list.sort(function (a, b) { return rank(a, term) - rank(b, term) || (a.name < b.name ? -1 : 1); });
			}
			list = list.slice(0, 60);
			alertMessage(fill(OT.using_saved, { time: timeText(snap.at) }), 'warning');
			renderProducts(list);
			if (done) { done(list); }
		});
	}

	function searchCustomersOffline(term, searchId) {
		loadSnapshot(currentWarehouse()).then(function (snap) {
			if (searchId !== customerSearchId) { return; }
			var needle = term.toLowerCase();
			var list = ((snap && snap.customers) || []).filter(function (c) {
				return !needle || (c.name || '').toLowerCase().indexOf(needle) >= 0 || (c.phone || '').indexOf(needle) >= 0;
			}).slice(0, 20);
			fillCustomers(list);
			renderCustomerResults(list, term);
		});
	}

	/* Selling without a connection: keep the sale on this device until it can be sent. */

	function saveSaleOffline(payload) {
		if (!offlineReady()) { return false; }
		var calc = totals();
		var preview = settlePreview();
		var customerSelect = $('#pos-customer');
		var customerName = customerSelect.value && customerSelect.selectedOptions[0] ? customerSelect.selectedOptions[0].textContent : '';
		var rec = {
			key: payload.idempotency_key,
			tenant: OFF.tenant,
			created_at: new Date().toISOString(),
			status: 'pending',
			message: '',
			expected_total: calc.total,
			payload: payload,
			summary: {
				number: (OT.prefix || 'OFF') + '-' + String(payload.idempotency_key).replace(/[^a-zA-Z0-9]/g, '').slice(0, 6).toUpperCase(),
				customer: customerName,
				items: state.items.map(function (item, i) {
					return { name: item.name, qty: item.qty, price: item.price, total: calc.lines[i].total };
				}),
				subtotal: calc.subtotal, discount: calc.discount_total, tax: calc.tax_total, total: calc.total,
				paid: preview.paid, change: preview.change, balance: preview.balance,
				payments: payload.payments.map(function (p) { return { method: CFG.methodLabels[p.method] || p.method, amount: p.amount }; })
			}
		};
		var sold = state.items.map(function (item) { return { id: item.id, qty: item.qty }; });
		store.queuePut(rec).then(function () {
			state.busy = false;
			state.queue.push(rec);
			lowerLocalStock(sold);
			renderNet();
			modal('pos-pay').hide();
			showDone({ number: rec.summary.number, total: calc.total, change_given: preview.change, balance_due: preview.balance }, rec);
			clearCart();
			state.lastAttempt = null;
			loadProducts();
		}).catch(function () {
			state.busy = false;
			updatePaySummary();
			var error = $('#pos-pay-error');
			error.textContent = T.network_error;
			error.style.display = '';
		});
		return true;
	}

	// The saved list keeps counting down, so a second offline sale of the last unit is not offered.
	function lowerLocalStock(sold) {
		var warehouse = currentWarehouse();
		var snap = state.snapshots[warehouse];
		if (!snap) { return; }
		sold.forEach(function (line) {
			snap.products.forEach(function (p) {
				if (p.id === line.id && p.track_stock && p.stock !== null) { p.stock = Math.round((p.stock - line.qty) * 1000) / 1000; }
			});
		});
		store.put(snapKey(warehouse), snap).catch(function () {});
	}

	function printLocalReceipt(key) {
		var rec = state.queue.filter(function (r) { return r.key === key; })[0];
		if (!rec) { return; }
		var sum = rec.summary;
		var w = window.open('', '_blank', 'width=360,height=640');
		if (!w) { return; }
		var rows = sum.items.map(function (item) {
			return '<tr><td>' + esc(item.name) + '<br><small>' + qtyText(item.qty) + ' x ' + fmt(item.price) + '</small></td><td style="text-align:right">' + fmt(item.total) + '</td></tr>';
		}).join('');
		var pay = sum.payments.map(function (p) { return '<tr><td>' + esc(p.method) + '</td><td style="text-align:right">' + fmt(p.amount) + '</td></tr>'; }).join('');
		w.document.write('<!doctype html><meta charset="utf-8"><title>' + esc(sum.number) + '</title>' +
			'<style>body{font-family:monospace;font-size:12px;width:280px;margin:8px auto}table{width:100%;border-collapse:collapse}td{padding:2px 0;vertical-align:top}hr{border:0;border-top:1px dashed #000}h3,p{text-align:center;margin:4px 0}</style>' +
			'<h3>' + esc(OFF.business || '') + '</h3><p>' + esc(sum.number) + '<br>' + esc(timeText(rec.created_at)) + (sum.customer ? '<br>' + esc(sum.customer) : '') + '</p><hr>' +
			'<table>' + rows + '</table><hr><table>' +
			'<tr><td>' + esc(T.subtotal || 'Subtotal') + '</td><td style="text-align:right">' + fmt(sum.subtotal) + '</td></tr>' +
			(sum.discount ? '<tr><td>' + esc(T.discount || 'Discount') + '</td><td style="text-align:right">-' + fmt(sum.discount) + '</td></tr>' : '') +
			'<tr><td><b>Total</b></td><td style="text-align:right"><b>' + fmt(sum.total) + '</b></td></tr>' + pay +
			(sum.change ? '<tr><td>' + esc(T.change) + '</td><td style="text-align:right">' + fmt(sum.change) + '</td></tr>' : '') +
			(sum.balance ? '<tr><td>' + esc(T.balance_due) + '</td><td style="text-align:right">' + fmt(sum.balance) + '</td></tr>' : '') +
			'</table><hr><p><small>' + esc(OT.receipt_note) + '</small></p>');
		w.document.close();
		w.focus();
		w.print();
	}

	/* Sending the queue: one sale at a time, oldest first. A key already recorded comes back as the same sale. */

	function syncQueue() {
		if (!offlineReady() || state.syncing || !state.online) { return Promise.resolve(); }
		var waiting = state.queue.filter(function (rec) { return rec.status !== 'rejected'; });
		if (!waiting.length) { return Promise.resolve(); }
		state.syncing = true;
		state.syncMessage = '';
		renderNet();
		var sent = 0;
		var flagged = 0;

		function finish() {
			state.syncing = false;
			if (sent) {
				state.syncMessage = fill(OT.synced, { count: sent }) + (flagged ? ' ' + fill(OT.sync_checks, { count: flagged }) : '') + (state.syncMessage ? ' ' + state.syncMessage : '');
				refreshSnapshots().then(function () { loadProducts(); });
			}
			renderNet();
		}

		function sendNext(list, retried) {
			var rec = list.shift();
			if (!rec) { return Promise.resolve(); }
			var body = Object.assign({}, rec.payload, { offline: { sold_at: rec.created_at, expected_total: rec.expected_total } });
			return request('POST', OFF.checkout, body).then(function (result) {
				if (result.ok) {
					sent += 1;
					if (result.data.needs_review) { flagged += 1; }
					state.queue = state.queue.filter(function (r) { return r.key !== rec.key; });
					return store.queueRemove(rec.key).then(function () { return sendNext(list, false); });
				}
				if (result.status === 401) { state.syncMessage = OT.login_needed; return; }
				if (result.status === 419 && !retried) {
					return fetchToken().then(function (ok) { if (!ok) { return; } list.unshift(rec); return sendNext(list, true); });
				}
				if (result.status === 422 || result.status === 403 || result.status === 404) {
					rec.status = 'rejected';
					rec.message = errorMessages(result).join(' ') || String(result.status);
					return store.queuePut(rec).then(function () { return sendNext(list, false); });
				}
				state.syncMessage = OT.server_down;
			});
		}

		return fetchToken().then(function (ok) {
			if (!ok) { return; }
			return sendNext(waiting.slice(), false);
		}).catch(function () {
			state.online = false;
			state.syncMessage = OT.server_down;
		}).then(finish);
	}

	function fetchToken() {
		return request('GET', OFF.session).then(function (result) {
			if (result.ok && result.data.csrf) { CFG.csrf = result.data.csrf; return true; }
			state.syncMessage = result.status === 401 || result.ok ? OT.login_needed : OT.server_down;
			return false;
		});
	}

	document.addEventListener('click', function (event) {
		var target = event.target;
		var closest = function (selector) { return target.closest ? target.closest(selector) : null; };
		var local = closest('[data-local-receipt]');
		if (local) { printLocalReceipt(local.getAttribute('data-local-receipt')); return; }
		if (closest('#pos-offline-sync')) { syncQueue(); return; }
		var retry = closest('[data-off-retry]');
		if (retry) {
			var rec = state.queue.filter(function (r) { return r.key === retry.getAttribute('data-off-retry'); })[0];
			if (rec) { rec.status = 'pending'; rec.message = ''; store.queuePut(rec).then(function () { renderNet(); syncQueue(); }); }
			return;
		}
		var discard = closest('[data-off-discard]');
		if (discard && window.confirm(OT.confirm_discard)) {
			var key = discard.getAttribute('data-off-discard');
			state.queue = state.queue.filter(function (r) { return r.key !== key; });
			store.queueRemove(key).then(renderNet);
		}
	});

	document.addEventListener('show.bs.modal', function (event) {
		if (event.target.id === 'pos-offline') { renderQueue(); }
	});

	function initOffline() {
		if (!offlineReady()) { return; }
		window.addEventListener('online', setOnline);
		window.addEventListener('offline', goOffline);
		renderNet();
		loadQueue().then(function () { if (state.online) { syncQueue(); } });
		(OFF.warehouses || []).forEach(loadSnapshot);
		if (state.online) { refreshSnapshots(); }
		// Retry what is waiting, check whether the connection is back, and keep the saved list fresh.
		window.setInterval(function () {
			if (!state.online) {
				request('GET', OFF.session).then(function () { setOnline(); }).catch(function () {});
			} else if (state.queue.some(function (rec) { return rec.status !== 'rejected'; })) {
				syncQueue();
			}
		}, 20000);
		window.setInterval(refreshSnapshots, 10 * 60 * 1000);
	}

	render();
	loadProducts();
	initOffline();
})();
