// Contract test: the browser calculator must agree with the shared fixtures that the PHP
// SaleCalculator is also tested against. Run with: node tests/js/calc.test.mjs
import { readFileSync } from 'node:fs';
import assert from 'node:assert/strict';

// package.json is "type": "module", so evaluate the browser script in a sandboxed scope instead of require().
const source = readFileSync(new URL('../../public/js/pos-calc.js', import.meta.url), 'utf8');
const { calculate } = new Function('self', `${source}\nreturn self.PosCalc;`)({});
const cases = JSON.parse(readFileSync(new URL('../fixtures/sale-calc-cases.json', import.meta.url)));

let failed = 0;
for (const testCase of cases) {
	try {
		assert.deepEqual(calculate(testCase.lines, testCase.discount), testCase.expected);
		console.log(`ok   ${testCase.name}`);
	} catch (error) {
		failed++;
		console.error(`FAIL ${testCase.name}\n${error.message}`);
	}
}

if (failed > 0) {
	process.exit(1);
}
console.log(`${cases.length} cases passed`);
