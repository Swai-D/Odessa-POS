// The barcode encoder must reproduce modules that were independently decoded (zxing) back to the same value.
// Run with: node tests/js/barcode.test.mjs
import { readFileSync } from 'node:fs';
import assert from 'node:assert/strict';

const source = readFileSync(new URL('../../public/js/barcode.js', import.meta.url), 'utf8');
const barcode = new Function('self', `${source}\nreturn self.PosBarcode;`)({});
const cases = JSON.parse(readFileSync(new URL('../fixtures/barcode-cases.json', import.meta.url)));

let failed = 0;
for (const testCase of cases) {
	try {
		const encoded = barcode.encode(testCase.value);
		assert.equal(encoded.format, testCase.format);
		assert.equal(encoded.modules, testCase.modules);
		assert.match(barcode.toSvg(encoded, 40), /^<svg/);
	} catch (error) {
		failed++;
		console.error('FAIL', testCase.value, error.message);
	}
}

assert.equal(barcode.encode(''), null);
assert.equal(barcode.encode('héllo'), null);
assert.equal(barcode.checkDigit('590123412345'), 7);

if (failed > 0) {
	process.exit(1);
}
console.log(`barcode: ${cases.length} cases ok`);
