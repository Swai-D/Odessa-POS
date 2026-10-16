// Contract test: the browser promotion engine must agree with the shared fixtures that the PHP
// PromotionEngine is also tested against. Run with: node tests/js/promo.test.mjs
import { readFileSync } from 'node:fs';
import assert from 'node:assert/strict';

const source = readFileSync(new URL('../../public/js/pos-promo.js', import.meta.url), 'utf8');
const { evaluate, localParts } = new Function('self', `${source}\nreturn self.PosPromo;`)({});
const cases = JSON.parse(readFileSync(new URL('../fixtures/promotion-cases.json', import.meta.url)));

let failed = 0;
for (const testCase of cases) {
	try {
		const result = evaluate(testCase.promotions, testCase.lines, testCase.coupon, testCase.at)
			.map((entry) => (entry === null ? null : { promotion_id: entry.promotion_id, amount: entry.amount }));
		assert.deepEqual(result, testCase.expected);
		console.log(`ok   ${testCase.name}`);
	} catch (error) {
		failed++;
		console.error(`FAIL ${testCase.name}\n${error.message}`);
	}
}

// The shop's clock: 2026-10-14 22:30 UTC is already Thursday the 15th, 01:30 in Dar es Salaam (UTC+3).
try {
	assert.deepEqual(localParts(new Date('2026-10-14T22:30:00Z'), 'Africa/Dar_es_Salaam'), { date: '2026-10-15', weekday: 4, minutes: 90 });
	console.log('ok   local parts use the shop time zone');
} catch (error) {
	failed++;
	console.error(`FAIL local parts\n${error.message}`);
}

if (failed > 0) {
	process.exit(1);
}
console.log(`${cases.length} cases passed`);
