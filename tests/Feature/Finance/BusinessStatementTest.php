<?php

use App\Domain\Finance\Models\BusinessStatement;
use App\Domain\Finance\Services\ReportService;
use App\Domain\Settings\Models\AuditLog;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

afterEach(fn () => Carbon::setTestNow());

/** A shop with sales in August and September 2026 and one in October (the month that is still running). */
function lenderShop(object $test, string $slug, string $plan = 'medium'): array
{
    Carbon::setTestNow('2026-08-10 10:00:00');
    $tenant = createTenant($slug, $plan);
    [$product, $warehouse] = posFixture($tenant, 50);
    $seller = createTenantUser($tenant, posPermissions());
    $viewer = createTenantUser($tenant, ['reports.view', 'expenses.view']);

    $sell = function (string $when, string $key) use ($test, $slug, $seller, $product, $warehouse): void {
        Carbon::setTestNow($when);
        checkout($test, $slug, $seller, cart($product, $warehouse, [
            'idempotency_key' => $key,
            'payments' => [['method' => 'cash', 'amount' => 200000]],
        ]))->assertCreated();
    };

    $sell('2026-08-10 10:00:00', 'k-aug');
    $sell('2026-09-12 11:00:00', 'k-sep');
    $sell('2026-10-02 09:00:00', 'k-oct');

    Carbon::setTestNow('2026-10-14 10:00:00');

    return [$tenant, $viewer, $product, $warehouse, $seller];
}

function issueStatement(object $test, string $slug, $user, int $months = 3, string $locale = 'en')
{
    return $test->actingAs($user)->withHeader('X-Tenant', $slug)->post('/business-statements', ['months' => $months, 'locale' => $locale]);
}

function theStatement($tenant): BusinessStatement
{
    return app(TenantContext::class)->run($tenant, fn () => BusinessStatement::query()->latest('id')->firstOrFail());
}

it('covers the last whole months and leaves out the month still running', function (): void {
    [$tenant, $viewer] = lenderShop($this, 'lend-a');

    issueStatement($this, 'lend-a', $viewer)->assertSessionHasNoErrors()->assertRedirect();

    $statement = theStatement($tenant);
    $data = $statement->data;

    expect($statement->number)->toBe('BS-000001')
        ->and($statement->period_from->format('Y-m-d'))->toBe('2026-07-01')
        ->and($statement->period_to->format('Y-m-d'))->toBe('2026-09-30')
        ->and(array_column($data['months'], 'key'))->toBe(['2026-07', '2026-08', '2026-09'])
        ->and(array_column($data['months'], 'sales_count'))->toBe([0, 1, 1])
        ->and($data['totals']['sales_count'])->toBe(2)
        ->and($data['totals']['net_sales'])->toBe(400000)
        ->and($data['totals']['gross_profit'])->toBe(200000)
        ->and($data['averages']['active_months'])->toBe(2)
        ->and($data['averages']['monthly_net_sales'])->toBe(200000)
        ->and($data['activity']['first_sale_on'])->toBe('2026-08-10');
});

it('gives the same figures as the Reports page for the same dates', function (): void {
    [$tenant, $viewer] = lenderShop($this, 'lend-b');

    issueStatement($this, 'lend-b', $viewer);

    $data = theStatement($tenant)->data;
    $pnl = app(TenantContext::class)->run($tenant, fn () => (new ReportService(Carbon::parse('2026-07-01'), Carbon::parse('2026-09-30')))->profitAndLoss());

    expect($data['totals']['gross_profit'])->toBe($pnl['gross_profit'])
        ->and($data['totals']['net_profit'])->toBe($pnl['net_profit'])
        ->and(array_sum(array_column($data['months'], 'net_sales')))->toBe($data['totals']['net_sales']);
});

it('has a verification code and a checksum that holds', function (): void {
    [$tenant, $viewer] = lenderShop($this, 'lend-c');

    issueStatement($this, 'lend-c', $viewer);

    $statement = theStatement($tenant);

    expect($statement->code)->toMatch('/^[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}-[A-HJ-NP-Z2-9]{4}$/')
        ->and($statement->isIntact())->toBeTrue()
        ->and(strlen($statement->checksum))->toBe(64);
});

it('stays as issued when more sales are made afterwards', function (): void {
    [$tenant, $viewer, $product, $warehouse, $seller] = lenderShop($this, 'lend-d');

    issueStatement($this, 'lend-d', $viewer);
    $before = theStatement($tenant)->data;

    Carbon::setTestNow('2026-09-20 10:00:00');
    checkout($this, 'lend-d', $seller, cart($product, $warehouse, ['idempotency_key' => 'late', 'payments' => [['method' => 'cash', 'amount' => 200000]]]))->assertCreated();
    Carbon::setTestNow('2026-10-14 10:00:00');

    expect(theStatement($tenant)->data)->toBe($before)->and(theStatement($tenant)->isIntact())->toBeTrue();
});

it('counts expenses and shows net profit', function (): void {
    [$tenant, $viewer] = lenderShop($this, 'lend-e');
    addExpense($tenant, 'Rent', 50000, '2026-09-05');

    issueStatement($this, 'lend-e', $viewer);

    $data = theStatement($tenant)->data;
    expect($data['totals']['expenses'])->toBe(50000)
        ->and($data['totals']['net_profit'])->toBe(150000)
        ->and($data['expense_categories'][0]['name'])->toBe('Rent');
});

it('works for a shop with no sales yet', function (): void {
    Carbon::setTestNow('2026-10-14 10:00:00');
    $tenant = createTenant('lend-f');
    $user = createTenantUser($tenant, ['reports.view']);

    issueStatement($this, 'lend-f', $user, 6)->assertSessionHasNoErrors();

    $data = theStatement($tenant)->data;
    expect($data['totals']['net_sales'])->toBe(0)
        ->and($data['averages']['active_months'])->toBe(0)
        ->and($data['averages']['monthly_net_sales'])->toBe(0)
        ->and($data['activity']['first_sale_on'])->toBeNull()
        ->and($data['months'])->toHaveCount(6);
});

it('numbers statements in order and lists them', function (): void {
    [$tenant, $viewer] = lenderShop($this, 'lend-g');

    issueStatement($this, 'lend-g', $viewer);
    issueStatement($this, 'lend-g', $viewer, 6);

    $this->actingAs($viewer)->withHeader('X-Tenant', 'lend-g')->get('/business-statements')
        ->assertOk()->assertSee('BS-000001')->assertSee('BS-000002');
});

it('shows the statement and a PDF, in the language it was issued in', function (): void {
    [$tenant, $viewer] = lenderShop($this, 'lend-h');

    issueStatement($this, 'lend-h', $viewer, 3, 'sw');
    $statement = theStatement($tenant);

    $this->actingAs($viewer)->withHeader('X-Tenant', 'lend-h')->get('/business-statements/'.$statement->id)
        ->assertOk()->assertSee('Taarifa ya biashara')->assertSee($statement->code)->assertSee('Mauzo halisi');

    $pdf = $this->actingAs($viewer)->withHeader('X-Tenant', 'lend-h')->get('/business-statements/'.$statement->id.'/pdf');
    $pdf->assertOk();
    expect($pdf->headers->get('content-type'))->toContain('application/pdf')->and(substr($pdf->getContent(), 0, 4))->toBe('%PDF');
});

it('verifies a statement on a public page, whatever way the code is typed', function (): void {
    [$tenant, $viewer] = lenderShop($this, 'lend-i');

    issueStatement($this, 'lend-i', $viewer);
    $statement = theStatement($tenant);
    auth()->logout();
    $this->flushSession();

    foreach ([$statement->code, strtolower($statement->code), str_replace('-', ' ', $statement->code)] as $typed) {
        $this->get('/verify?code='.urlencode($typed))
            ->assertOk()->assertSee('This statement is genuine')->assertSee($statement->number)->assertSee('Lend-i')->assertSee('4,000');
    }
});

it('says so when the code is unknown', function (): void {
    $this->get('/verify')->assertOk()->assertDontSee('genuine');
    $this->get('/verify?code=AAAA-BBBB-CCCC')->assertOk()->assertSee('We have no statement with this code');
});

it('warns when the stored figures were altered after issue', function (): void {
    [$tenant, $viewer] = lenderShop($this, 'lend-j');

    issueStatement($this, 'lend-j', $viewer);
    $statement = theStatement($tenant);

    $data = $statement->data;
    $data['totals']['net_sales'] = 99999999;
    DB::table('business_statements')->where('id', $statement->id)->update(['data' => json_encode($data)]);

    $this->get('/verify?code='.$statement->code)->assertOk()->assertSee('does not match what was issued')->assertDontSee('This statement is genuine');
});

it('limits how often codes can be tried', function (): void {
    foreach (range(1, 20) as $i) {
        $this->get('/verify?code=AAAA-BBBB-CC'.sprintf('%02d', $i))->assertOk();
    }

    $this->get('/verify?code=AAAA-BBBB-CCZZ')->assertStatus(429);
});

it('needs the reports permission and the reports plan feature', function (): void {
    [$tenant, $viewer] = lenderShop($this, 'lend-k');
    $nobody = createTenantUser($tenant, ['products.view']);

    issueStatement($this, 'lend-k', $nobody)->assertForbidden();
    $this->actingAs($nobody)->withHeader('X-Tenant', 'lend-k')->get('/business-statements')->assertForbidden();

    $basic = createTenant('lend-k2', 'basic');
    $user = createTenantUser($basic, ['reports.view']);

    issueStatement($this, 'lend-k2', $user)->assertForbidden();
    expect(app(TenantContext::class)->run($basic, fn () => BusinessStatement::query()->count()))->toBe(0);
});

it('does not show another shop\'s statement', function (): void {
    [$tenant, $viewer] = lenderShop($this, 'lend-l');
    issueStatement($this, 'lend-l', $viewer);
    $statement = theStatement($tenant);

    $other = createTenant('lend-l2');
    $user = createTenantUser($other, ['reports.view']);

    $this->actingAs($user)->withHeader('X-Tenant', 'lend-l2')->get('/business-statements/'.$statement->id)->assertNotFound();
    $this->actingAs($user)->withHeader('X-Tenant', 'lend-l2')->get('/business-statements/'.$statement->id.'/pdf')->assertNotFound();
});

it('validates the period and language', function (): void {
    [$tenant, $viewer] = lenderShop($this, 'lend-m');

    issueStatement($this, 'lend-m', $viewer, 5)->assertSessionHasErrors('months');
    issueStatement($this, 'lend-m', $viewer, 6, 'fr')->assertSessionHasErrors('locale');

    expect(app(TenantContext::class)->run($tenant, fn () => BusinessStatement::query()->count()))->toBe(0);
});

it('records the issue in the audit log', function (): void {
    [$tenant, $viewer] = lenderShop($this, 'lend-n');

    issueStatement($this, 'lend-n', $viewer);

    $log = app(TenantContext::class)->run($tenant, fn () => AuditLog::query()->where('event', 'generated')->first());

    expect($log)->not->toBeNull()->and($log->subject_type)->toBe('BusinessStatement')->and($log->subject_label)->toBe('BS-000001');
});
