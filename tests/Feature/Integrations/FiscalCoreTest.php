<?php

use App\Domain\Integrations\Actions\IssueFiscalReceiptAction;
use App\Domain\Integrations\Models\FiscalReceipt;
use App\Domain\Integrations\Services\FiscalException;
use App\Domain\Integrations\Services\FiscalIdentity;
use App\Domain\Integrations\Services\FiscalSigner;
use App\Domain\Sales\Models\Sale;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;

function fiscalIdentity(array $override = []): FiscalIdentity
{
    return new FiscalIdentity(...array_merge([
        'tin' => '123456789', 'regId' => 'TZ0100001', 'efdSerial' => '10TZ100200', 'receiptCode' => 'ABC123',
    ], $override));
}

/** @return array{0: string, 1: string} a throw-away .pfx and its password */
function fiscalPfx(): array
{
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $csr = openssl_csr_new(['commonName' => 'test-vfd'], $key);
    $cert = openssl_csr_sign($csr, null, $key, 30, [], 1234567890);
    openssl_pkcs12_export($cert, $pfx, $key, 'secret');

    return [$pfx, 'secret'];
}

/** Makes a paid (or credit) sale at the till and returns it. The shop's user and stock are created on first use. */
function fiscalSale(object $test, string $slug, array $extraCart = []): Sale
{
    static $fixtures = [];

    $tenant = Tenant::query()->where('slug', $slug)->firstOrFail();
    $fixtures[$slug] ??= [createTenantUser($tenant, posPermissions()), posFixture($tenant, 10)];
    [$user, [$product, $warehouse, $customer]] = $fixtures[$slug];

    // `customer_id => true` means "the shop's fixture customer" (it does not exist before the first call).
    if (($extraCart['customer_id'] ?? null) === true) {
        $extraCart['customer_id'] = $customer->id;
    }

    checkout($test, $slug, $user, cart($product, $warehouse, $extraCart + ['payments' => [['method' => 'cash', 'amount' => 200000]]]))->assertCreated();

    return app(TenantContext::class)->run($tenant, fn () => Sale::query()->latest('id')->firstOrFail());
}

function fiscalIssue(object $tenant, Sale $sale, ?FiscalIdentity $identity = null): FiscalReceipt
{
    return app(TenantContext::class)->run($tenant, fn () => app(IssueFiscalReceiptAction::class)->handle($sale, $identity ?? fiscalIdentity()));
}

afterEach(fn () => Carbon::setTestNow());

it('signs with SHA-1 RSA, wraps the message and rejects a wrong password', function () {
    [$pfx, $password] = fiscalPfx();
    $signer = new FiscalSigner($pfx, $password);

    $signature = $signer->sign('<RCT>x</RCT>');
    expect($signer->verify('<RCT>x</RCT>', $signature))->toBeTrue()
        ->and($signer->verify('<RCT>tampered</RCT>', $signature))->toBeFalse()
        ->and($signer->envelope('<RCT>x</RCT>'))->toStartWith('<?xml version="1.0" encoding="UTF-8"?><EFDMS><RCT>x</RCT><EFDMSSIGNATURE>')->toEndWith('</EFDMSSIGNATURE></EFDMS>')
        ->and(base64_decode($signer->certSerialHeader()))->toBe('49 96 02 d2');

    expect(fn () => new FiscalSigner($pfx, 'wrong'))->toThrow(FiscalException::class);
});

it('numbers receipts without gaps and resets the daily counter each day', function () {
    Carbon::setTestNow('2026-10-16 10:00:00');
    $tenant = createTenant('fis-a');
    $first = fiscalIssue($tenant, fiscalSale($this, 'fis-a'));

    expect($first->gc)->toBe(1)->and($first->dc)->toBe(1)->and($first->znum)->toBe(20261016)->and($first->rctvnum)->toBe('ABC1231')->and($first->status)->toBe('pending');

    // Same sale again: nothing new.
    $again = fiscalIssue($tenant, Sale::query()->withoutGlobalScopes()->findOrFail($first->sale_id));
    expect($again->id)->toBe($first->id);

    $second = fiscalIssue($tenant, fiscalSale($this, 'fis-a'));
    expect($second->gc)->toBe(2)->and($second->dc)->toBe(2);

    // 22:30 UTC is already the next day in Tanzania: the day counter restarts, the global one does not.
    Carbon::setTestNow('2026-10-16 22:30:00');
    $third = fiscalIssue($tenant, fiscalSale($this, 'fis-a'));
    expect($third->gc)->toBe(3)->and($third->dc)->toBe(1)->and($third->znum)->toBe(20261017);
});

it('starts at the counter TRA gave at registration and keeps shops apart', function () {
    $a = createTenant('fis-b');
    $b = createTenant('fis-c');

    $receiptA = fiscalIssue($a, fiscalSale($this, 'fis-b'), fiscalIdentity(['startingGc' => 501]));
    $receiptB = fiscalIssue($b, fiscalSale($this, 'fis-c'));

    expect($receiptA->gc)->toBe(501)->and($receiptA->rctvnum)->toBe('ABC123501')->and($receiptB->gc)->toBe(1);
});

it('builds the receipt XML from the sale', function () {
    Carbon::setTestNow('2026-10-16 10:00:00');
    $tenant = createTenant('fis-d');
    $sale = fiscalSale($this, 'fis-d');
    app(TenantContext::class)->run($tenant, function () use ($sale): void {
        $sale->items()->update(['tax_rate' => 18, 'tax' => 30508]);
        $sale->update(['tax_total' => 30508]);
    });

    $xml = simplexml_load_string(fiscalIssue($tenant, $sale->refresh())->payload);

    expect((string) $xml->DATE)->toBe('2026-10-16')
        ->and((string) $xml->TIME)->toBe('13:00:00')
        ->and((string) $xml->TIN)->toBe('123456789')
        ->and((string) $xml->RCTNUM)->toBe('1')
        ->and((string) $xml->GC)->toBe('1')
        ->and((string) $xml->RCTVNUM)->toBe('ABC1231')
        ->and((string) $xml->ITEMS->ITEM->DESC)->toBe('Soap')
        ->and((string) $xml->ITEMS->ITEM->QTY)->toBe('2')
        ->and((string) $xml->ITEMS->ITEM->TAXCODE)->toBe('1')
        ->and((string) $xml->ITEMS->ITEM->AMT)->toBe('2000.00')
        ->and((string) $xml->TOTALS->TOTALTAXINCL)->toBe('2000.00')
        ->and((string) $xml->TOTALS->TOTALTAXEXCL)->toBe('1694.92')
        ->and((string) $xml->PAYMENTS->PMTTYPE)->toBe('CASH')
        ->and((string) $xml->PAYMENTS->PMTAMOUNT)->toBe('2000.00')
        ->and((string) $xml->VATTOTALS->VATRATE)->toBe('A')
        ->and((string) $xml->VATTOTALS->NETTAMOUNT)->toBe('1694.92')
        ->and((string) $xml->VATTOTALS->TAXAMOUNT)->toBe('305.08');
});

it('reports the unpaid part of a credit sale as an invoice amount', function () {
    $tenant = createTenant('fis-e');
    $sale = fiscalSale($this, 'fis-e', ['payments' => [['method' => 'cash', 'amount' => 50000]], 'customer_id' => true]);

    $xml = simplexml_load_string(fiscalIssue($tenant, $sale)->payload);

    expect((string) $xml->CUSTNAME)->toBe('Asha')
        ->and((string) $xml->PAYMENTS->PMTTYPE[0])->toBe('CASH')
        ->and((string) $xml->PAYMENTS->PMTAMOUNT[0])->toBe('500.00')
        ->and((string) $xml->PAYMENTS->PMTTYPE[1])->toBe('INVOICE')
        ->and((string) $xml->PAYMENTS->PMTAMOUNT[1])->toBe('1500.00');
});

it('escapes special characters in names so TRA does not reject the receipt', function () {
    $tenant = createTenant('fis-f');
    $sale = fiscalSale($this, 'fis-f');
    app(TenantContext::class)->run($tenant, fn () => $sale->items()->update(['product_name' => 'Salt & <Pepper> "mix"']));

    $payload = fiscalIssue($tenant, $sale->refresh())->payload;

    expect($payload)->toContain('Salt &amp; &lt;Pepper&gt; &quot;mix&quot;');
    expect((string) simplexml_load_string($payload)->ITEMS->ITEM->DESC)->toBe('Salt & <Pepper> "mix"');
});

it('uses the shop chosen code for 0% items and refuses rates TRA does not know', function () {
    $tenant = createTenant('fis-g');
    $sale = fiscalSale($this, 'fis-g');
    $xml = simplexml_load_string(fiscalIssue($tenant, $sale, fiscalIdentity(['zeroTaxCode' => 5]))->payload);
    expect((string) $xml->ITEMS->ITEM->TAXCODE)->toBe('5')->and((string) $xml->VATTOTALS->VATRATE)->toBe('E');

    $tenantB = createTenant('fis-h');
    $saleB = fiscalSale($this, 'fis-h');
    app(TenantContext::class)->run($tenantB, fn () => $saleB->items()->update(['tax_rate' => 7]));

    expect(fn () => fiscalIssue($tenantB, $saleB->refresh()))->toThrow(FiscalException::class);
    expect(app(TenantContext::class)->run($tenantB, fn () => FiscalReceipt::query()->count()))->toBe(0);
});

it('does not use up a number when the receipt cannot be built', function () {
    $tenant = createTenant('fis-i');
    $sale = fiscalSale($this, 'fis-i');

    expect(fn () => fiscalIssue($tenant, $sale, fiscalIdentity(['tin' => '12345'])))->toThrow(FiscalException::class);

    // The failed attempt rolled back, so the next good receipt is still number 1.
    expect(fiscalIssue($tenant, $sale)->gc)->toBe(1);
});
