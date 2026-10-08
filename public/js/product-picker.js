/*
 * Product picker: a text box that searches products on the server as you type (name, SKU or barcode),
 * instead of loading the whole catalogue into a <select>. Plain input + datalist, so it needs no extra CSS.
 *
 * Markup (see resources/views/partials/product-picker.blade.php):
 *   <div class="js-product-picker" data-url="..." data-tracked="1">
 *     <input type="text" class="form-control" list="...">   <datalist id="..."></datalist>
 *     <input type="hidden" name="product_id" value="">
 *   </div>
 */
(function () {
	'use strict';

	function attach(root, onPick) {
		if (!root || root.dataset.pickerReady) { return; }
		root.dataset.pickerReady = '1';

		var text = root.querySelector('input[type="text"]');
		var hidden = root.querySelector('input[type="hidden"]');
		var list = root.querySelector('datalist');
		var required = root.dataset.required === '1';
		var items = {};
		var timer = null;

		function validity() {
			text.setCustomValidity(required && !hidden.value ? (root.dataset.message || 'Select a product from the list') : '');
		}

		function pick() {
			var item = items[text.value];
			hidden.value = item ? item.id : '';
			validity();
			if (item && onPick) { onPick(item); }
		}

		function search() {
			var url = root.dataset.url + '?q=' + encodeURIComponent(text.value.trim()) + (root.dataset.tracked === '1' ? '&tracked=1' : '');
			fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
				.then(function (response) { return response.ok ? response.json() : { data: [] }; })
				.then(function (body) {
					items = {};
					list.innerHTML = '';
					(body.data || []).forEach(function (item) {
						items[item.label] = item;
						var option = document.createElement('option');
						option.value = item.label;
						list.appendChild(option);
					});
					pick();
				})
				.catch(function () {});
		}

		text.addEventListener('input', function () {
			// Picking from the datalist fires "input" with the full label: resolve it right away.
			if (items[text.value]) { pick(); return; }
			hidden.value = '';
			validity();
			clearTimeout(timer);
			timer = setTimeout(search, 250);
		});
		text.addEventListener('focus', function () { if (!list.children.length) { search(); } });
		text.addEventListener('change', pick);
		validity();
	}

	window.ProductPicker = {
		attach: attach,
		attachAll: function (scope, onPick) {
			Array.prototype.forEach.call((scope || document).querySelectorAll('.js-product-picker'), function (root) { attach(root, onPick); });
		}
	};
})();
