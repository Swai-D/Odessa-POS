<?php

use App\Domain\Integrations\Models\TenantIntegration;
use App\Domain\Inventory\Models\ProductStock;
use App\Domain\Sales\Models\PaymentRequest;
use App\Domain\Sales\Models\Sale;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

function mmKey(): OpenSSLAsymmetricKey
{
    static $key = null;

    return $key ??= openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
}

function mmFakeAzamPay(array $overrides = []): void
{
    $pem = openssl_pkey_get_details(mmKey())['key'];

    Http::fake($overrides + [
        '*/AppRegistration/GenerateToken' => Http::response(['data' => ['accessToken' => 'tok-123', 'expire' => '2030-01-01'], 'success' => true, 'statusCode' => 200]),
        '*/azampay/mno/checkout' => Http::response(['success' => true, 'transactionId' => 'PG-1', 'message' => 'Prompt sent']),
        '*/azampay/v1/public-key*' => Http::response(['success' => true, 'format' => 'Pem', 'publicKey' => $pem]),
    ]);
}

/** @return array{0: Tenant, 1: mixed} a shop with AzamPay connected, and a cashier */
function mmShop(string $slug, bool $connected = true): array
{
    $tenant = createTenant($slug);

    if ($connected) {
        $tenant->update(['settings' => ['features' => ['mobile_money' => true]]]);
        app(TenantContext::class)->run($tenant, fn () => TenantIntegration::query()->create([
            'channel' => 'payments', 'driver' => 'azampay',
            'settings' => ['environment' => 'sandbox', 'app_name' => 'MyShop', 'client_id' => 'cid-1'],
            'secrets' => ['client_secret' => 'csecret', 'api_key' => 'apikey-1'],
        ]));
    }

    return [$tenant->refresh(), createTenantUser($tenant, posPermissions())];
}

function mmPrompt(object $test, $user, string $slug, array $override = [])
{
    return $test->actingAs($user)->withHeader('X-Tenant', $slug)->postJson('/pos/mobile-money', $override + [
        'idempotency_key' => 'mm-key-'.uniqid(), 'provider' => 'Tigo', 'phone' => '0712 345 678', 'amount' => 200000,
    ]);
}

/** A signed callback body, as AzamPay would send it. */
function mmCallback(PaymentRequest $request, array $override = []): array
{
    $body = $override + [
        'message' => 'Transaction completed successfully', 'user' => 'u', 'password' => 'p', 'clientId' => 'cid-1',
        'transactionstatus' => 'success', 'operator' => 'Tigo', 'reference' => 'R1', 'externalreference' => 'PG-1',
        'utilityref' => $request->reference, 'amount' => '2000', 'transid' => 'T1', 'msisdn' => '255712345678', 'mnoreference' => 'MNO-77',
    ];

    openssl_sign($body['utilityref'].$body['externalreference'].$body['transactionstatus'].$body['operator'], $signature, mmKey(), OPENSSL_ALGO_SHA256);

    return $body + ['signature' => base64_encode($signature)];
}

function mmRequest(Tenant $tenant, array $override = []): PaymentRequest
{
    return app(TenantContext::class)->run($tenant, fn () => PaymentRequest::query()->create($override + [
        'idempotency_key' => 'mm-row-'.uniqid(), 'reference' => 'OD'.strtoupper(uniqid()), 'provider' => 'Tigo', 'phone' => '255712345678',
        'amount' => 200000, 'status' => PaymentRequest::PENDING, 'provider_reference' => 'PG-1', 'expires_at' => now()->addMinutes(5),
    ]));
}

function mmFresh(Tenant $tenant, PaymentRequest $request): PaymentRequest
{
    return app(TenantContext::class)->run($tenant, fn () => PaymentRequest::query()->findOrFail($request->id));
}

afterEach(fn () => Carbon::setTestNow());

it('sends the prompt to the customer phone with the shop credentials', function () {
    mmFakeAzamPay();
    [$tenant, $user] = mmShop('mm-a');

    mmPrompt($this, $user, 'mm-a')->assertCreated()->assertJsonPath('status', 'pending');

    $row = app(TenantContext::class)->run($tenant, fn () => PaymentRequest::query()->sole());
    expect($row->phone)->toBe('255712345678')->and($row->provider_reference)->toBe('PG-1')->and($row->amount)->toBe(200000);

    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/azampay/mno/checkout')
        && $r->hasHeader('Authorization', 'Bearer tok-123') && $r->hasHeader('X-API-Key', 'apikey-1')
        && $r['accountNumber'] === '255712345678' && $r['amount'] === 2000 && $r['currency'] === 'TZS'
        && $r['provider'] === 'Tigo' && str_starts_with($r['externalId'], 'OD'));
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/AppRegistration/GenerateToken') && $r['clientSecret'] === 'csecret' && $r['clientId'] === 'cid-1');
});

it('does not prompt the customer twice for a double click', function () {
    mmFakeAzamPay();
    [, $user] = mmShop('mm-b');

    mmPrompt($this, $user, 'mm-b', ['idempotency_key' => 'mm-same-key'])->assertCreated();
    mmPrompt($this, $user, 'mm-b', ['idempotency_key' => 'mm-same-key'])->assertCreated();

    Http::assertSentCount(2); // one token + one checkout, not two checkouts
});

it('records a refusal or an outage as a failed request', function () {
    mmFakeAzamPay(['*/azampay/mno/checkout' => Http::response(['success' => false, 'transactionId' => null, 'message' => 'Insufficient balance'])]);
    [$tenant, $user] = mmShop('mm-c');
    mmPrompt($this, $user, 'mm-c')->assertCreated()->assertJsonPath('status', 'failed')->assertJsonPath('reason', 'Insufficient balance');

    Http::fake(['*/AppRegistration/GenerateToken' => Http::response(['data' => ['accessToken' => 't']]), '*/azampay/mno/checkout' => fn () => throw new ConnectionException('down')]);
    mmPrompt($this, $user, 'mm-c')->assertCreated()->assertJsonPath('status', 'failed')->assertJsonPath('reason', 'unreachable');
});

it('refuses bad input and shops without AzamPay', function () {
    mmFakeAzamPay();
    [, $user] = mmShop('mm-d');
    mmPrompt($this, $user, 'mm-d', ['amount' => 150])->assertUnprocessable();
    mmPrompt($this, $user, 'mm-d', ['amount' => 500000001])->assertUnprocessable();
    mmPrompt($this, $user, 'mm-d', ['phone' => '123'])->assertUnprocessable();
    mmPrompt($this, $user, 'mm-d', ['provider' => 'Paypal'])->assertUnprocessable();

    [, $other] = mmShop('mm-e', connected: false);
    mmPrompt($this, $other, 'mm-e')->assertUnprocessable();
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'checkout'));
});

it('marks the request paid when a correctly signed success callback arrives, once', function () {
    mmFakeAzamPay();
    [$tenant] = mmShop('mm-f');
    $request = mmRequest($tenant);
    $body = mmCallback($request);

    $this->postJson("/webhooks/azampay/{$tenant->id}", $body)->assertOk()->assertJsonPath('status', 'applied');
    $this->postJson("/webhooks/azampay/{$tenant->id}", $body)->assertOk()->assertJsonPath('status', 'duplicate');

    $fresh = mmFresh($tenant, $request);
    expect($fresh->status)->toBe('succeeded')->and($fresh->mno_reference)->toBe('MNO-77')->and($fresh->completed_at)->not->toBeNull()
        ->and($fresh->callback)->not->toHaveKeys(['password', 'user', 'signature'])->toHaveKey('transid');
});

it('ignores a callback with a wrong or missing signature', function () {
    mmFakeAzamPay();
    [$tenant] = mmShop('mm-g');
    $request = mmRequest($tenant);

    $tampered = mmCallback($request);
    $tampered['transactionstatus'] = 'failure';
    $this->postJson("/webhooks/azampay/{$tenant->id}", $tampered)->assertUnauthorized();

    $unsigned = mmCallback($request);
    unset($unsigned['signature']);
    $this->postJson("/webhooks/azampay/{$tenant->id}", $unsigned)->assertUnauthorized();

    expect(mmFresh($tenant, $request)->status)->toBe('pending');
});

it('does not accept a success for a different amount', function () {
    mmFakeAzamPay();
    [$tenant] = mmShop('mm-h');
    $request = mmRequest($tenant);

    $this->postJson("/webhooks/azampay/{$tenant->id}", mmCallback($request, ['amount' => '1000']))->assertOk();

    $fresh = mmFresh($tenant, $request);
    expect($fresh->status)->toBe('failed')->and($fresh->failure_reason)->toBe('amount_mismatch');
});

it('records a failure callback with its message', function () {
    mmFakeAzamPay();
    [$tenant] = mmShop('mm-i');
    $request = mmRequest($tenant);

    $this->postJson("/webhooks/azampay/{$tenant->id}", mmCallback($request, ['transactionstatus' => 'failure', 'message' => 'Customer declined']))->assertOk();

    $fresh = mmFresh($tenant, $request);
    expect($fresh->status)->toBe('failed')->and($fresh->failure_reason)->toBe('Customer declined');
});

it('still honours a success that arrives after the request expired', function () {
    mmFakeAzamPay();
    Carbon::setTestNow('2026-10-16 09:00:00');
    [$tenant, $user] = mmShop('mm-j');
    $request = mmRequest($tenant);

    Carbon::setTestNow('2026-10-16 09:30:00');
    $this->actingAs($user)->withHeader('X-Tenant', 'mm-j')->getJson("/pos/mobile-money/{$request->id}")->assertOk()->assertJsonPath('status', 'expired');

    $this->postJson("/webhooks/azampay/{$tenant->id}", mmCallback($request))->assertOk();
    expect(mmFresh($tenant, $request)->status)->toBe('succeeded');
});

it('keeps shops apart in callbacks and polling', function () {
    mmFakeAzamPay();
    [$tenantA] = mmShop('mm-k');
    [$tenantB, $userB] = mmShop('mm-l');
    $request = mmRequest($tenantA);

    // A's reference sent to B's address finds nothing in B.
    $this->postJson("/webhooks/azampay/{$tenantB->id}", mmCallback($request))->assertOk()->assertJsonPath('status', 'unknown');
    expect(mmFresh($tenantA, $request)->status)->toBe('pending');

    $this->actingAs($userB)->withHeader('X-Tenant', 'mm-l')->getJson("/pos/mobile-money/{$request->id}")->assertNotFound();
    $this->postJson('/webhooks/azampay/not-a-shop', mmCallback($request))->assertNotFound();
});

function mmSell(object $test, Tenant $tenant, $user, string $slug, array $payment): TestResponse
{
    static $fixtures = [];
    $fixtures[$slug] ??= posFixture($tenant, 10);
    [$product, $warehouse] = $fixtures[$slug];

    return checkout($test, $slug, $user, cart($product, $warehouse, ['payments' => [$payment]]));
}

function mmStock(Tenant $tenant): float
{
    return app(TenantContext::class)->run($tenant, fn () => (float) ProductStock::query()->sum('quantity'));
}

it('turns a paid request into a sale payment, once', function () {
    mmFakeAzamPay();
    [$tenant, $user] = mmShop('mm-m');
    $request = mmRequest($tenant, ['status' => PaymentRequest::SUCCEEDED, 'mno_reference' => 'MNO-9']);

    mmSell($this, $tenant, $user, 'mm-m', ['method' => 'mobile_money', 'amount' => 200000, 'payment_request_id' => $request->id])->assertCreated();

    $sale = app(TenantContext::class)->run($tenant, fn () => Sale::query()->with('payments')->sole());
    expect($sale->payment_status)->toBe('paid')->and($sale->payments->first()->reference)->toBe('MNO-9')
        ->and(mmFresh($tenant, $request)->sale_id)->toBe($sale->id)
        ->and(mmStock($tenant))->toBe(8.0);

    // The same payment cannot pay a second sale; the whole second sale is rolled back.
    mmSell($this, $tenant, $user, 'mm-m', ['method' => 'mobile_money', 'amount' => 200000, 'payment_request_id' => $request->id])->assertUnprocessable();
    expect(app(TenantContext::class)->run($tenant, fn () => Sale::query()->count()))->toBe(1)->and(mmStock($tenant))->toBe(8.0);
});

it('refuses a sale paid with a request that is pending, failed, the wrong amount or another shop', function () {
    mmFakeAzamPay();
    [$tenant, $user] = mmShop('mm-n');
    [$other] = mmShop('mm-o');
    $line = fn (PaymentRequest $r, int $amount = 200000) => ['method' => 'mobile_money', 'amount' => $amount, 'payment_request_id' => $r->id];

    mmSell($this, $tenant, $user, 'mm-n', $line(mmRequest($tenant)))->assertUnprocessable();
    mmSell($this, $tenant, $user, 'mm-n', $line(mmRequest($tenant, ['status' => PaymentRequest::FAILED])))->assertUnprocessable();
    mmSell($this, $tenant, $user, 'mm-n', $line(mmRequest($tenant, ['status' => PaymentRequest::SUCCEEDED]), 100000))->assertUnprocessable();
    mmSell($this, $tenant, $user, 'mm-n', $line(mmRequest($other, ['status' => PaymentRequest::SUCCEEDED])))->assertUnprocessable();

    expect(app(TenantContext::class)->run($tenant, fn () => Sale::query()->count()))->toBe(0)->and(mmStock($tenant))->toBe(10.0);
});

it('still accepts a manual mobile money line with no provider request', function () {
    [$tenant, $user] = mmShop('mm-p', connected: false);

    mmSell($this, $tenant, $user, 'mm-p', ['method' => 'mobile_money', 'amount' => 200000, 'reference' => 'MPESA-ABC'])->assertCreated();

    expect(app(TenantContext::class)->run($tenant, fn () => Sale::query()->with('payments')->sole()->payments->first()->reference))->toBe('MPESA-ABC');
});

it('turns the till prompt on only for a shop with AzamPay connected', function () {
    [$on, $cashierOn] = mmShop('mm-q');
    [$off, $cashierOff] = mmShop('mm-r', connected: false);

    $with = $this->actingAs($cashierOn)->withHeader('X-Tenant', 'mm-q')->get('/pos')->assertOk()->getContent();
    $without = $this->actingAs($cashierOff)->withHeader('X-Tenant', 'mm-r')->get('/pos')->assertOk()->getContent();

    expect($with)->toContain('mobileMoney: {')->and($with)->toContain('enabled: true')->and($with)->toContain('Halopesa')
        ->and($without)->toContain('enabled: false');
});
