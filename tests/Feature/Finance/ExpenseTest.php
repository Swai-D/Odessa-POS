<?php

use App\Domain\Finance\Models\Expense;
use App\Domain\Finance\Models\ExpenseCategory;
use App\Domain\Finance\Services\ReportService;
use App\Domain\Settings\Actions\ProvisionTenantAction;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;

function expenseShop(string $slug, string $plan = 'medium', array $permissions = ['expenses.view', 'expenses.manage']): array
{
    $tenant = createTenant($slug, $plan);

    return [$tenant, createTenantUser($tenant, $permissions)];
}

function addExpense($tenant, string $category, int $amount, ?string $date = null, array $extra = []): Expense
{
    return app(TenantContext::class)->run($tenant, function () use ($category, $amount, $date, $extra): Expense {
        $cat = ExpenseCategory::query()->firstOrCreate(['name' => $category]);

        return Expense::create($extra + [
            'expense_category_id' => $cat->getKey(), 'amount' => $amount, 'currency' => 'TZS',
            'method' => 'cash', 'spent_on' => $date ?? Carbon::today()->format('Y-m-d'),
        ]);
    });
}

function expenseCategoryId($tenant, string $name): int
{
    return app(TenantContext::class)->run($tenant, fn () => ExpenseCategory::query()->firstOrCreate(['name' => $name])->getKey());
}

it('records an expense in minor units and lists it', function (): void {
    [$tenant, $user] = expenseShop('shop-e1');
    $category = expenseCategoryId($tenant, 'Rent');

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-e1')->post('/expenses', [
        'expense_category_id' => $category, 'amount' => '1500.50', 'method' => 'cash',
        'spent_on' => Carbon::today()->format('Y-m-d'), 'note' => 'October rent',
    ])->assertRedirect('/expenses');

    $expense = app(TenantContext::class)->run($tenant, fn () => Expense::query()->firstOrFail());
    expect($expense->amount)->toBe(150050)->and($expense->user_id)->toBe($user->id);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-e1')->get('/expenses')
        ->assertOk()->assertSee('October rent')->assertSee('1,500.50');
});

it('rejects a zero amount, a future date and an unknown payment method', function (): void {
    [$tenant, $user] = expenseShop('shop-e2');

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-e2')->post('/expenses', [
        'expense_category_id' => expenseCategoryId($tenant, 'Power'), 'amount' => '0', 'method' => 'barter',
        'spent_on' => Carbon::today()->addDay()->format('Y-m-d'),
    ])->assertSessionHasErrors(['amount', 'method', 'spent_on']);
});

it('does not accept a category from another shop', function (): void {
    [$tenant, $user] = expenseShop('shop-e3');
    $other = createTenant('shop-e3b', 'medium');

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-e3')->post('/expenses', [
        'expense_category_id' => expenseCategoryId($other, 'Secret'), 'amount' => '10', 'method' => 'cash',
        'spent_on' => Carbon::today()->format('Y-m-d'),
    ])->assertSessionHasErrors('expense_category_id');
});

it('updates and deletes an expense', function (): void {
    [$tenant, $user] = expenseShop('shop-e4');
    $expense = addExpense($tenant, 'Transport', 5000);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-e4')->put('/expenses/'.$expense->id, [
        'expense_category_id' => $expense->expense_category_id, 'amount' => '75', 'method' => 'mobile_money',
        'spent_on' => Carbon::today()->format('Y-m-d'),
    ])->assertRedirect('/expenses');

    $fresh = app(TenantContext::class)->run($tenant, fn () => Expense::query()->findOrFail($expense->id));
    expect($fresh->amount)->toBe(7500)->and($fresh->method)->toBe('mobile_money');

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-e4')->delete('/expenses/'.$expense->id)->assertRedirect('/expenses');
    expect(app(TenantContext::class)->run($tenant, fn () => Expense::query()->count()))->toBe(0);
});

it('lets a viewer read but not change expenses', function (): void {
    [$tenant, $user] = expenseShop('shop-e5', permissions: ['expenses.view']);
    addExpense($tenant, 'Rent', 100000, extra: ['note' => 'visible note']);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-e5')->get('/expenses')->assertOk()->assertSee('visible note');
});

it('forbids creating without the manage permission', function (): void {
    [$tenant, $user] = expenseShop('shop-e6', permissions: ['expenses.view']);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-e6')->post('/expenses', [
        'expense_category_id' => expenseCategoryId($tenant, 'Rent'), 'amount' => '10', 'method' => 'cash',
        'spent_on' => Carbon::today()->format('Y-m-d'),
    ])->assertForbidden();
});

it('forbids viewing without the view permission', function (): void {
    [, $user] = expenseShop('shop-e7', permissions: ['products.view']);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-e7')->get('/expenses')->assertForbidden();
});

it('is not part of the Basic plan', function (): void {
    [, $user] = expenseShop('shop-e8', 'basic');

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-e8')->get('/expenses')->assertForbidden();
});

it('never shows or changes another shop\'s expenses', function (): void {
    [, $user] = expenseShop('shop-e9');
    $other = createTenant('shop-e9b', 'medium');
    addExpense($other, 'Secret', 999, extra: ['note' => 'other shop note']);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-e9')->get('/expenses')->assertOk()->assertDontSee('other shop note');
});

it('returns 404 when editing another shop\'s expense', function (): void {
    [$tenant, $user] = expenseShop('shop-ea');
    $other = createTenant('shop-eab', 'medium');
    $foreign = addExpense($other, 'Secret', 999);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-ea')->put('/expenses/'.$foreign->id, [
        'expense_category_id' => expenseCategoryId($tenant, 'Rent'), 'amount' => '1', 'method' => 'cash',
        'spent_on' => Carbon::today()->format('Y-m-d'),
    ])->assertNotFound();
});

it('filters by date, category and search, and totals the filtered list', function (): void {
    [$tenant, $user] = expenseShop('shop-eb');
    addExpense($tenant, 'Rent', 100000, Carbon::today()->format('Y-m-d'), ['note' => 'office rent']);
    addExpense($tenant, 'Power', 20000, Carbon::today()->format('Y-m-d'), ['note' => 'luku token']);
    addExpense($tenant, 'Rent', 50000, Carbon::today()->subMonths(3)->format('Y-m-d'), ['note' => 'old rent']);

    // Default period is this month: the old one is left out and the total is the sum of the other two.
    $default = $this->actingAs($user)->withHeader('X-Tenant', 'shop-eb')->get('/expenses')->assertOk()->getContent();
    expect($default)->toContain('office rent')->toContain('luku token')->not->toContain('old rent')->toContain('1,200.00');

    $search = $this->actingAs($user)->withHeader('X-Tenant', 'shop-eb')->get('/expenses?q=LUKU')->assertOk()->getContent();
    expect($search)->toContain('luku token')->not->toContain('office rent');

    $categoryId = expenseCategoryId($tenant, 'Rent');
    $byCategory = $this->actingAs($user)->withHeader('X-Tenant', 'shop-eb')->get('/expenses?category='.$categoryId)->assertOk()->getContent();
    expect($byCategory)->toContain('office rent')->not->toContain('luku token');
});

it('exports the filtered expenses as CSV', function (): void {
    [$tenant, $user] = expenseShop('shop-ec');
    addExpense($tenant, 'Rent', 123456, extra: ['note' => '=SUM(A1)']);

    $response = $this->actingAs($user)->withHeader('X-Tenant', 'shop-ec')->get('/expenses/export');
    $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    $csv = $response->streamedContent();

    expect($csv)->toContain('1234.56')->toContain('Rent')->toContain("'=SUM(A1)");
});

it('does not delete a category that has expenses', function (): void {
    [$tenant, $user] = expenseShop('shop-ed');
    $expense = addExpense($tenant, 'Rent', 100);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-ed')
        ->delete('/expense-categories/'.$expense->expense_category_id)->assertSessionHasErrors('delete');

    expect(app(TenantContext::class)->run($tenant, fn () => ExpenseCategory::query()->count()))->toBe(1);
});

it('gives the finance roles expense permissions when a shop is provisioned', function (): void {
    $roles = ProvisionTenantAction::rolePermissions();

    expect($roles['Owner'])->toContain('expenses.view', 'expenses.manage')
        ->and($roles['Manager'])->toContain('expenses.manage')
        ->and($roles['Accountant'])->toContain('expenses.view', 'expenses.manage')
        ->and($roles['Cashier'])->not->toContain('expenses.view')
        ->and($roles['Storekeeper'])->not->toContain('expenses.view');
});

it('works out profit and loss: gross profit less expenses', function (): void {
    $tenant = createTenant('shop-ee', 'medium');
    $user = createTenantUser($tenant, posPermissions());
    [$product, $warehouse] = posFixture($tenant);

    checkout($this, 'shop-ee', $user, cart($product, $warehouse, [
        'payments' => [['method' => 'cash', 'amount' => 200000]],
    ]))->assertCreated();

    addExpense($tenant, 'Rent', 30000);
    addExpense($tenant, 'Power', 10000);
    addExpense($tenant, 'Old', 99999, Carbon::today()->subMonths(2)->format('Y-m-d'));

    $pnl = reportsFor($tenant, callback: fn (ReportService $r) => $r->profitAndLoss());

    expect($pnl['revenue'])->toBe(200000)
        ->and($pnl['gross_profit'])->toBe(100000)
        ->and($pnl['cost'])->toBe(100000)
        ->and($pnl['expenses'])->toBe(40000)
        ->and($pnl['net_profit'])->toBe(60000)
        ->and($pnl['by_category'])->toBe([['name' => 'Rent', 'amount' => 30000], ['name' => 'Power', 'amount' => 10000]]);
});

it('shows profit and loss on the reports page only when expenses are in the plan', function (): void {
    $tenant = createTenant('shop-ef', 'medium');
    $user = createTenantUser($tenant, ['reports.view', 'expenses.view']);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-ef')->get('/reports')->assertOk()
        ->assertSee('Profit and loss')
        ->assertSee('report-profit-chart', false);
});

it('hides profit and loss from users who cannot see expenses', function (): void {
    $tenant = createTenant('shop-eg', 'medium');
    $user = createTenantUser($tenant, ['reports.view']);

    $this->actingAs($user)->withHeader('X-Tenant', 'shop-eg')->get('/reports')->assertOk()
        ->assertDontSee('Profit and loss')
        ->assertDontSee('report-profit-chart', false);
});
