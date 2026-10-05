/*
 * Sale arithmetic for the POS screen. Mirrors App\Domain\Sales\Services\SaleCalculator exactly
 * (integer minor units, same rounding). Both are verified against tests/fixtures/sale-calc-cases.json.
 */
(function (root, factory) {
	if (typeof module === 'object' && module.exports) {
		module.exports = factory();
	} else {
		root.PosCalc = factory();
	}
}(typeof self !== 'undefined' ? self : this, function () {
	'use strict';

	function discountAmount(subtotal, discount) {
		var type = (discount && discount.type) || 'none';
		var value = Number((discount && discount.value) || 0);
		var amount = 0;

		if (type === 'percent') {
			amount = Math.floor(subtotal * Math.min(Math.max(value, 0), 100) / 100 + 0.5);
		} else if (type === 'fixed') {
			amount = Math.trunc(Math.max(value, 0));
		}

		return Math.min(amount, subtotal);
	}

	function allocate(gross, discountTotal, subtotal) {
		var parts = gross.map(function () { return 0; });
		if (discountTotal <= 0 || subtotal <= 0) {
			return parts;
		}

		var allocated = 0;
		gross.forEach(function (amount, i) {
			parts[i] = Math.floor(discountTotal * amount / subtotal);
			allocated += parts[i];
		});

		var left = discountTotal - allocated;
		for (var i = 0; i < gross.length && left > 0; i++) {
			if (parts[i] < gross[i]) {
				parts[i]++;
				left--;
			}
		}

		return parts;
	}

	function calculate(lines, discount) {
		var gross = lines.map(function (line) {
			var quantityMilli = Math.round(Number(line.quantity) * 1000);
			return Math.floor(quantityMilli * line.unit_price / 1000 + 0.5);
		});

		var subtotal = gross.reduce(function (a, b) { return a + b; }, 0);
		var discountTotal = discountAmount(subtotal, discount);
		var allocated = allocate(gross, discountTotal, subtotal);

		var taxTotal = 0;
		var total = 0;
		var result = lines.map(function (line, i) {
			var net = gross[i] - allocated[i];
			var basisPoints = Math.round(Number(line.tax_rate) * 100);
			var tax;
			var lineTotal;

			if (line.tax_inclusive) {
				tax = net - Math.floor(net * 10000 / (10000 + basisPoints) + 0.5);
				lineTotal = net;
			} else {
				tax = Math.floor(net * basisPoints / 10000 + 0.5);
				lineTotal = net + tax;
			}

			taxTotal += tax;
			total += lineTotal;

			return { gross: gross[i], discount: allocated[i], tax: tax, total: lineTotal };
		});

		return { lines: result, subtotal: subtotal, discount_total: discountTotal, tax_total: taxTotal, total: total };
	}

	return { calculate: calculate };
}));
