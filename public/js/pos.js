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

	var state = {
		items: [],
		customerId: '',
		discount: { type: 'none', value: 0 },
		category: '',
		busy: false,
		lastAttempt: null,
		pay: { rows: [] }
	};
	var cache = {};
	var searchTimer = null;

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
		$('#pos-list').style.display = empty ? 'none' : '';
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
		$('#pos-discount').textContent = result.discount_total > 0 ? '-' + fmt(result.discount_total) : fmt(0);
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
		var params = new URLSearchParams();
		var term = $('#pos-search').value.trim();
		if (term) { params.set('q', term); }
		if (state.category) { params.set('category', state.category); }
		params.set('warehouse', currentWarehouse());
		return request('GET', CFG.routes.products + '?' + params.toString()).then(function (result) {
			if (result.ok) { renderProducts(result.data.data); if (done) { done(result.data.data); } }
		});
	}

	function currentWarehouse() {
		var select = $('#pos-warehouse');
		return select ? select.value : CFG.warehouseId;
	}

	/* ---------- customers ---------- */

	function selectedCustomer() { return $('#pos-customer').value; }

	/* ---------- payment modal ---------- */

	function payRow(method, amountMinor) {
		return { method: method, amount: amountMinor > 0 ? (amountMinor / 100).toFixed(2) : '', reference: '' };
	}

	function openPay(method) {
		var result = totals();
		if (!state.items.length) { alertMessage(T.cart_empty, 'warning'); return; }
		if (result.total <= 0) { alertMessage(CFG.errors.nothing_to_charge, 'warning'); return; }
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
		if (other > total) { error = CFG.errors.overpaid_non_cash; }
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
			return '<div class="row g-2 align-items-end mb-2" data-row="' + i + '">' +
				'<div class="col-4"><select class="form-select" data-pay-field="method">' + methods.replace('value="' + row.method + '"', 'value="' + row.method + '" selected') + '</select></div>' +
				'<div class="col-3"><input type="text" inputmode="decimal" class="form-control" data-pay-field="amount" placeholder="' + esc(T.amount) + '" value="' + esc(row.amount) + '"></div>' +
				'<div class="col-4"><input type="text" class="form-control" maxlength="100" data-pay-field="reference" placeholder="' + esc(T.reference) + '" value="' + esc(row.reference) + '"></div>' +
				'<div class="col-1"><a href="javascript:void(0);" class="link-danger fs-18" data-pay-remove="' + i + '"><i class="ti ti-trash"></i></a></div></div>';
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
			return { method: row.method, amount: toMinor(row.amount), reference: row.reference || null };
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
			state.busy = false;
			updatePaySummary();
			var error = $('#pos-pay-error');
			error.textContent = T.network_error;
			error.style.display = '';
		});
	}

	function showDone(sale) {
		$('#pos-done-number').textContent = sale.number;
		$('#pos-done-total').textContent = fmt(sale.total);
		$('#pos-done-change').textContent = fmt(sale.change_given);
		$('#pos-done-balance').textContent = fmt(sale.balance_due);
		$('#pos-done-balance-row').style.display = sale.balance_due > 0 ? '' : 'none';
		$('#pos-done-change-row').style.display = sale.change_given > 0 ? '' : 'none';
		$('#pos-done-receipt').setAttribute('href', sale.receipt_url + '?format=thermal');
		$('#pos-done-invoice').setAttribute('href', sale.receipt_url);
		modal('pos-done').show();
	}

	/* ---------- hold / resume ---------- */

	function holdOrder() {
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
				$('#pos-customer').value = held.customer_id ? String(held.customer_id) : '';
				modal('pos-held').hide();
				render();
			});
		});
	}

	/* ---------- events ---------- */

	document.addEventListener('click', function (event) {
		var target = event.target;
		var closest = function (selector) { return target.closest ? target.closest(selector) : null; };

		var tile = closest('#pos-grid .product-info');
		if (tile) {
			var product = cache[parseInt(tile.getAttribute('data-id'), 10)];
			if (product) { addProduct(product, 1); }
			render();
			return;
		}

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
		if (payRemove) { state.pay.rows.splice(parseInt(payRemove.getAttribute('data-pay-remove'), 10), 1); renderPay(); return; }
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
		var raw = parseFloat($('#pos-discount-value').value);
		if (isNaN(raw) || raw <= 0) { state.discount = { type: 'none', value: 0 }; }
		else if (type === 'percent') { state.discount = { type: 'percent', value: Math.min(raw, 100) }; }
		else { state.discount = { type: 'fixed', value: Math.round(raw * 100) }; }
		modal('pos-discount').hide();
		render();
	}

	function saveCustomer() {
		var name = $('#pos-customer-name').value.trim();
		if (!name) { return; }
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

	document.addEventListener('shown.bs.modal', function (event) {
		if (event.target.id === 'pos-pay') { var amount = $('#pos-pay-rows [data-pay-field="amount"]'); if (amount) { amount.focus(); amount.select(); } }
		if (event.target.id === 'pos-discount') { $('#pos-discount-value').focus(); }
	});

	// Customer changes affect the credit warning in the payment dialog.
	$('#pos-customer').addEventListener('change', updatePaySummary);

	render();
	loadProducts();
})();
