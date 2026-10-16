<?php

use App\Support\ErrorTracking\SentryReporter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

const TEST_DSN = 'https://abc123key@o1.ingest.sentry.io/4500';

beforeEach(function (): void {
    Cache::flush();
    config(['errors.dsn' => TEST_DSN, 'errors.dedupe_seconds' => 60, 'errors.sample_rate' => 1.0, 'errors.browser' => true]);
});

/** @return array<string, mixed> the event inside the first recorded envelope */
function sentEvent(): array
{
    $requests = Http::recorded();
    expect($requests)->toHaveCount(1);
    $lines = explode("\n", $requests[0][0]->body());

    return json_decode($lines[2], true);
}

it('sends nothing when no DSN is set', function (): void {
    Http::fake();
    config(['errors.dsn' => null]);

    app(SentryReporter::class)->capture(new RuntimeException('boom'));

    Http::assertNothingSent();
});

it('sends an envelope to the project endpoint with the key', function (): void {
    Http::fake();

    app(SentryReporter::class)->capture(new RuntimeException('boom'));

    Http::assertSent(fn ($request): bool => $request->url() === 'https://o1.ingest.sentry.io/api/4500/envelope/'
        && str_contains($request->header('X-Sentry-Auth')[0], 'sentry_key=abc123key'));
    $event = sentEvent();
    expect($event['exception']['values'][0]['type'])->toBe(RuntimeException::class)
        ->and($event['exception']['values'][0]['value'])->toBe('boom')
        ->and($event['platform'])->toBe('php');
});

it('reports the same error only once in the dedupe window', function (): void {
    Http::fake();
    $reporter = app(SentryReporter::class);

    foreach (range(1, 3) as $ignored) {
        $reporter->capture(new RuntimeException('same place'));
    }

    expect(Http::recorded())->toHaveCount(1);
});

it('hides emails and database values from the message', function (): void {
    Http::fake();
    $previous = new PDOException('Key (email)=(secret@shop.co.tz) already exists, contact owner@demo.test');

    app(SentryReporter::class)->capture(new QueryException('pgsql', 'insert into users (email) values (?)', ['secret@shop.co.tz'], $previous));

    $body = Http::recorded()[0][0]->body();
    expect($body)->not->toContain('secret@shop.co.tz')->not->toContain('owner@demo.test')
        ->and($body)->toContain('(email)=(***)');
});

it('identifies the shop and never sends request data', function (): void {
    Http::fake();
    $tenant = createTenant('shop-a');
    $user = createTenantUser($tenant, ['dashboard.view']);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-a')->get('/dashboard?secret=1');
    app(SentryReporter::class)->capture(new RuntimeException('with context'));

    $body = Http::recorded()[0][0]->body();
    expect($body)->not->toContain($user->email)->not->toContain('secret=1');
});

it('survives a failing error server', function (): void {
    Http::fake(fn () => throw new RuntimeException('network down'));

    app(SentryReporter::class)->capture(new RuntimeException('boom'));

    expect(true)->toBeTrue();
});

it('forwards browser errors without the query string and stores nothing', function (): void {
    Http::fake();

    $this->postJson('/client-errors', [
        'message' => 'x is not a function', 'source' => 'https://shop.test/js/pos.js?v=3', 'line' => 10, 'column' => 4,
        'stack' => 'at pos.js:10', 'page' => 'https://shop.test/pos?customer=5',
    ])->assertOk();

    $event = sentEvent();
    expect($event['platform'])->toBe('javascript')
        ->and($event['request']['url'])->toBe('https://shop.test/pos')
        ->and($event['exception']['values'][0]['stacktrace']['frames'][0]['filename'])->toBe('https://shop.test/js/pos.js');
});

it('ignores browser errors when browser reporting is off', function (): void {
    Http::fake();
    config(['errors.browser' => false]);

    $this->postJson('/client-errors', ['message' => 'x'])->assertOk();

    Http::assertNothingSent();
});

it('checks the connection with errors:test', function (): void {
    Http::fake(['*' => Http::response(['id' => 'x'], 200)]);

    $this->artisan('errors:test')->expectsOutput('HTTP 200')->assertSuccessful();
});
