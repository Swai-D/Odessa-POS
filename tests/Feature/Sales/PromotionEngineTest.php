<?php

use App\Domain\Sales\Services\PromotionEngine;

it('matches the shared fixtures that the browser promotion engine is also tested against', function (): void {
    $cases = json_decode((string) file_get_contents(base_path('tests/fixtures/promotion-cases.json')), true, 512, JSON_THROW_ON_ERROR);

    foreach ($cases as $case) {
        $result = array_map(
            fn (?array $entry) => $entry === null ? null : ['promotion_id' => $entry['promotion_id'], 'amount' => $entry['amount']],
            (new PromotionEngine)->evaluate($case['promotions'], $case['lines'], $case['coupon'], $case['at']),
        );

        expect($result)->toBe($case['expected'], $case['name']);
    }
});
