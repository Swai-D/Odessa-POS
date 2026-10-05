<?php

use App\Domain\Sales\Services\SaleCalculator;

it('matches the shared fixtures that the browser calculator is also tested against', function (): void {
    $cases = json_decode((string) file_get_contents(base_path('tests/fixtures/sale-calc-cases.json')), true, 512, JSON_THROW_ON_ERROR);

    foreach ($cases as $case) {
        $result = (new SaleCalculator)->calculate($case['lines'], $case['discount']);

        expect($result['subtotal'])->toBe($case['expected']['subtotal'], $case['name'])
            ->and($result['discount_total'])->toBe($case['expected']['discount_total'], $case['name'])
            ->and($result['tax_total'])->toBe($case['expected']['tax_total'], $case['name'])
            ->and($result['total'])->toBe($case['expected']['total'], $case['name']);

        foreach ($case['expected']['lines'] as $i => $line) {
            expect(array_intersect_key($result['lines'][$i], $line))->toBe($line, $case['name']);
        }
    }
});
