/*
 * Barcode encoders for label printing: EAN-13 (also UPC-A), EAN-8 and Code 128 (sets B and C).
 * encode(value) returns { format, text, modules } where modules is a string of 1 (bar) and 0 (space), or null when the
 * value cannot be a barcode. toSvg() draws it. No dependencies; checked against tests/fixtures/barcode-cases.json.
 */
(function (root, factory) {
	if (typeof module === 'object' && module.exports) {
		module.exports = factory();
	} else {
		root.PosBarcode = factory();
	}
}(typeof self !== 'undefined' ? self : this, function () {
	'use strict';

	// Code 128: element widths (bar, space, bar, space, bar, space) of values 0 to 106 (106 is the stop, 7 elements).
	var C128 = [
		'212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
		'221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
		'221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
		'212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
		'231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
		'231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
		'314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
		'112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
		'111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
		'214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
		'114131', '311141', '411131', '211412', '211214', '211232', '2331112'
	];
	var START_B = 104;
	var START_C = 105;
	var STOP = 106;

	function widthsToModules(widths) {
		var out = '';
		for (var i = 0; i < widths.length; i++) {
			out += (i % 2 === 0 ? '1' : '0').repeat(parseInt(widths.charAt(i), 10));
		}
		return out;
	}

	function code128(value) {
		var codes;
		if (/^\d{4,}$/.test(value) && value.length % 2 === 0) {
			codes = [START_C];
			for (var i = 0; i < value.length; i += 2) { codes.push(parseInt(value.substr(i, 2), 10)); }
		} else {
			codes = [START_B];
			for (var j = 0; j < value.length; j++) {
				var code = value.charCodeAt(j);
				if (code < 32 || code > 126) { return null; }
				codes.push(code - 32);
			}
		}
		var sum = codes[0];
		for (var k = 1; k < codes.length; k++) { sum += codes[k] * k; }
		codes.push(sum % 103, STOP);
		return codes.map(function (c) { return widthsToModules(C128[c]); }).join('');
	}

	var L = ['0001101', '0011001', '0010011', '0111101', '0100011', '0110001', '0101111', '0111011', '0110111', '0001011'];
	var G = ['0100111', '0110011', '0011011', '0100001', '0011101', '0111001', '0000101', '0010001', '0001001', '0010111'];
	var R = L.map(function (pattern) { return pattern.replace(/[01]/g, function (bit) { return bit === '0' ? '1' : '0'; }); });
	var PARITY = ['LLLLLL', 'LLGLGG', 'LLGGLG', 'LLGGGL', 'LGLLGG', 'LGGLLG', 'LGGGLL', 'LGLGLG', 'LGLGGL', 'LGGLGL'];

	// Check digit of a digit string (without it): weights 3,1,3,1... counted from the right.
	function checkDigit(digits) {
		var sum = 0;
		for (var i = 0; i < digits.length; i++) {
			sum += parseInt(digits.charAt(digits.length - 1 - i), 10) * (i % 2 === 0 ? 3 : 1);
		}
		return (10 - (sum % 10)) % 10;
	}

	function validEan(value) {
		return checkDigit(value.slice(0, -1)) === parseInt(value.slice(-1), 10);
	}

	function ean13(value) {
		var parity = PARITY[parseInt(value.charAt(0), 10)];
		var out = '101';
		for (var i = 1; i <= 6; i++) { out += (parity.charAt(i - 1) === 'L' ? L : G)[parseInt(value.charAt(i), 10)]; }
		out += '01010';
		for (var j = 7; j <= 12; j++) { out += R[parseInt(value.charAt(j), 10)]; }
		return out + '101';
	}

	function ean8(value) {
		var out = '101';
		for (var i = 0; i < 4; i++) { out += L[parseInt(value.charAt(i), 10)]; }
		out += '01010';
		for (var j = 4; j < 8; j++) { out += R[parseInt(value.charAt(j), 10)]; }
		return out + '101';
	}

	function encode(raw) {
		var value = String(raw === null || raw === undefined ? '' : raw).trim();
		if (value === '') { return null; }

		// A valid retail code keeps its retail symbol; anything else (SKUs, internal codes) is Code 128.
		if (/^\d{13}$/.test(value) && validEan(value)) { return { format: 'EAN13', text: value, modules: ean13(value) }; }
		if (/^\d{12}$/.test(value) && validEan(value)) { return { format: 'UPCA', text: value, modules: ean13('0' + value) }; }
		if (/^\d{8}$/.test(value) && validEan(value)) { return { format: 'EAN8', text: value, modules: ean8(value) }; }

		var modules = code128(value);
		return modules ? { format: 'CODE128', text: value, modules: modules } : null;
	}

	// An <svg> that scales to the width it is given. The quiet zone (blank margin) is part of the drawing.
	function toSvg(encoded, height) {
		var quiet = 10;
		var width = encoded.modules.length + quiet * 2;
		var path = '';
		var i = 0;
		while (i < encoded.modules.length) {
			if (encoded.modules.charAt(i) === '1') {
				var start = i;
				while (i < encoded.modules.length && encoded.modules.charAt(i) === '1') { i++; }
				path += 'M' + (quiet + start) + ' 0h' + (i - start) + 'v' + height + 'h-' + (i - start) + 'z';
			} else {
				i++;
			}
		}
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + width + ' ' + height + '" preserveAspectRatio="none" shape-rendering="crispEdges" role="img" aria-label="' + encoded.text.replace(/[<>&"]/g, '') + '"><path d="' + path + '" fill="#000"/></svg>';
	}

	return { encode: encode, toSvg: toSvg, checkDigit: checkDigit };
}));
