/*
 * Promotion arithmetic for the POS screen. Mirrors App\Domain\Sales\Services\PromotionEngine exactly
 * (integer minor units, same rounding). Both are verified against tests/fixtures/promotion-cases.json.
 */
(function (root, factory) {
	if (typeof module === 'object' && module.exports) {
		module.exports = factory();
	} else {
		root.PosPromo = factory();
	}
}(typeof self !== 'undefined' ? self : this, function () {
	'use strict';

	function minutes(time) {
		if (time === null || time === undefined || time === '') { return null; }
		var parts = String(time).split(':');
		return parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10);
	}

	function running(promotion, at) {
		if (!promotion.is_active) { return false; }
		if (promotion.starts_on && at.date < promotion.starts_on) { return false; }
		if (promotion.ends_on && at.date > promotion.ends_on) { return false; }
		if (promotion.days && promotion.days.length && promotion.days.indexOf(at.weekday) < 0) { return false; }

		var from = minutes(promotion.time_from);
		var to = minutes(promotion.time_to);
		if (from !== null && to !== null) {
			return from <= to ? (at.minutes >= from && at.minutes < to) : (at.minutes >= from || at.minutes < to);
		}
		if (from !== null && at.minutes < from) { return false; }
		return !(to !== null && at.minutes >= to);
	}

	function covers(promotion, line) {
		if (promotion.scope === 'products') { return promotion.scope_ids.indexOf(line.product_id) >= 0; }
		if (promotion.scope === 'categories') { return line.category_id !== null && line.category_id !== undefined && promotion.scope_ids.indexOf(line.category_id) >= 0; }
		return true;
	}

	function percentOff(gross, hundredths) {
		return Math.floor(gross * Math.min(hundredths, 10000) / 10000 + 0.5);
	}

	function perUnitOff(quantityMilli, unitPrice, perUnit) {
		return Math.floor(quantityMilli * Math.min(perUnit, unitPrice) / 1000 + 0.5);
	}

	function freeUnits(quantityMilli, buy, get) {
		if (buy < 1 || get < 1) { return 0; }
		return Math.floor(Math.floor(quantityMilli / 1000) / (buy + get)) * get;
	}

	function discount(promotion, line) {
		var quantityMilli = Math.round(Number(line.quantity) * 1000);
		var gross = Math.floor(quantityMilli * line.unit_price / 1000 + 0.5);
		var value = Math.trunc(Number(promotion.value || 0));
		var amount = 0;

		if (promotion.type === 'percent') {
			amount = percentOff(gross, value);
		} else if (promotion.type === 'fixed') {
			amount = perUnitOff(quantityMilli, line.unit_price, value);
		} else if (promotion.type === 'bulk') {
			if (quantityMilli >= Math.round(Number(promotion.min_qty || 0) * 1000)) {
				amount = promotion.value_kind === 'fixed' ? perUnitOff(quantityMilli, line.unit_price, value) : percentOff(gross, value);
			}
		} else if (promotion.type === 'bogo') {
			amount = freeUnits(quantityMilli, Math.trunc(Number(promotion.buy_qty || 0)), Math.trunc(Number(promotion.get_qty || 0))) * line.unit_price;
		}

		return Math.min(Math.max(amount, 0), gross);
	}

	// One entry per line: { promotion_id, name, amount } or null.
	function evaluate(promotions, lines, coupon, at) {
		var code = coupon && String(coupon).trim() !== '' ? String(coupon).trim().toUpperCase() : null;

		var usable = promotions.filter(function (promotion) {
			if (!running(promotion, at)) { return false; }
			var promoCode = promotion.code;
			return !promoCode || (code !== null && String(promoCode).toUpperCase() === code);
		});

		return lines.map(function (line) {
			var best = null;
			usable.forEach(function (promotion) {
				if (!covers(promotion, line)) { return; }
				var amount = discount(promotion, line);
				if (amount > 0 && (best === null || amount > best.amount || (amount === best.amount && promotion.id < best.promotion_id))) {
					best = { promotion_id: promotion.id, name: promotion.name, amount: amount };
				}
			});
			return best;
		});
	}

	// The shop's local date, ISO weekday and minutes since midnight for a moment, in the shop's time zone.
	function localParts(date, timeZone) {
		var parts = {};
		new Intl.DateTimeFormat('en-GB', {
			timeZone: timeZone, year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', weekday: 'short', hourCycle: 'h23'
		}).formatToParts(date).forEach(function (part) { parts[part.type] = part.value; });
		var weekdays = { Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6, Sun: 7 };
		return {
			date: parts.year + '-' + parts.month + '-' + parts.day,
			weekday: weekdays[parts.weekday],
			minutes: parseInt(parts.hour, 10) * 60 + parseInt(parts.minute, 10)
		};
	}

	return { evaluate: evaluate, running: running, localParts: localParts };
}));
