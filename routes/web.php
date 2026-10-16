<?php

use App\Http\Controllers\Auth\RegisterShopController;
use App\Http\Controllers\Catalog\BrandController;
use App\Http\Controllers\Catalog\CategoryController;
use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\Catalog\ProductTransferController;
use App\Http\Controllers\Catalog\UnitController;
use App\Http\Controllers\ClientErrorController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Finance\BranchReportController;
use App\Http\Controllers\Finance\BusinessStatementController;
use App\Http\Controllers\Finance\ExpenseCategoryController;
use App\Http\Controllers\Finance\ExpenseController;
use App\Http\Controllers\Finance\TillClosingController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\Inventory\StockAdjustmentController;
use App\Http\Controllers\Inventory\StockBatchController;
use App\Http\Controllers\Inventory\StockController;
use App\Http\Controllers\Inventory\StocktakeController;
use App\Http\Controllers\Inventory\StockTransferController;
use App\Http\Controllers\Inventory\WarehouseController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\People\CustomerController;
use App\Http\Controllers\People\CustomerStatementController;
use App\Http\Controllers\Platform\PlatformDashboardController;
use App\Http\Controllers\Platform\PlatformPaymentController;
use App\Http\Controllers\Platform\PlatformPaymentNoticeController;
use App\Http\Controllers\Platform\SubscriptionPlanController;
use App\Http\Controllers\Platform\TenantController;
use App\Http\Controllers\Platform\TenantRenewalController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Purchasing\PurchaseController;
use App\Http\Controllers\Purchasing\PurchaseReturnController;
use App\Http\Controllers\Purchasing\SupplierController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Sales\CheckoutController;
use App\Http\Controllers\Sales\MobileMoneyController;
use App\Http\Controllers\Sales\PosController;
use App\Http\Controllers\Sales\PosCustomerController;
use App\Http\Controllers\Sales\PromotionController;
use App\Http\Controllers\Sales\QuotationController;
use App\Http\Controllers\Sales\SaleController;
use App\Http\Controllers\Sales\SaleReturnController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Settings\AuditLogController;
use App\Http\Controllers\Settings\BillingController;
use App\Http\Controllers\Settings\DataExportController;
use App\Http\Controllers\Settings\DomainController;
use App\Http\Controllers\Settings\IntegrationController;
use App\Http\Controllers\Settings\OnboardingController;
use App\Http\Controllers\Settings\PaymentNoticeController;
use App\Http\Controllers\Settings\RoleController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Settings\UserController;
use App\Http\Controllers\StatementVerifyController;
use App\Http\Controllers\Webhooks\AzamPayCallbackController;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::get('/t/{tenant:slug}', function (Request $request, Tenant $tenant) {
    $configuredDomain = (string) config('app.domain');
    $domainUrl = str_contains($configuredDomain, '://') ? $configuredDomain : 'http://'.$configuredDomain;
    $appDomain = strtolower((string) parse_url($domainUrl, PHP_URL_HOST));

    abort_unless(strtolower($request->getHost()) === $appDomain, 404);
    abort_unless(in_array($tenant->status, ['active', 'trial'], true), 404);

    $user = $request->user();
    abort_if($user && ($user->is_super_admin || $user->tenant_id !== $tenant->getKey()), 403);

    // A shop with its own working domain is sent there. Only once the name has been seen pointing at this app.
    if ($tenant->domain && $tenant->domain_routed_at) {
        return redirect()->away($request->getScheme().'://'.$tenant->domain.'/');
    }

    $request->session()->put('tenant_slug', $tenant->slug);

    return redirect()->route($user ? 'dashboard' : 'login');
})->name('tenant.entry');

// "Not your shop?" on a shop's sign-in page: forget the remembered shop and show the general sign-in.
Route::get('/switch-shop', function (Request $request) {
    $request->session()->forget('tenant_slug');

    return redirect()->route('login');
})->name('tenant.leave');

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisterShopController::class, 'create'])->name('register');
    Route::post('/register', [RegisterShopController::class, 'store'])->middleware('throttle:5,60');
});

Route::get('/verify', [StatementVerifyController::class, 'show'])->middleware('throttle:20,1')->name('statements.verify');
// Result callbacks from AzamPay; each shop registers its own address. Verified by signature, so no login or CSRF token.
Route::post('/client-errors', ClientErrorController::class)->middleware('throttle:20,1')->name('client-errors');
Route::post('/webhooks/azampay/{tenant}', AzamPayCallbackController::class)->middleware('throttle:120,1')->name('webhooks.azampay');
Route::get('/terms', [LegalController::class, 'terms'])->name('legal.terms');
Route::get('/privacy', [LegalController::class, 'privacy'])->name('legal.privacy');

Route::post('/language/{locale}', function (Request $request, string $locale) {
    abort_unless(in_array($locale, ['en', 'sw'], true), 404);

    if ($request->user()) {
        $request->user()->forceFill(['locale' => $locale])->save();
    } else {
        session(['locale' => $locale]);
    }

    return redirect()->back();
})->name('locale.switch');

// The landing page works without a shop (a platform super admin has none).
Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/help', [HelpController::class, 'index'])->name('help.index');
    Route::get('/help/{category}/{slug}', [HelpController::class, 'show'])->where(['category' => '[a-z0-9-]+', 'slug' => '[a-z0-9-]+'])->name('help.show');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
});

// Platform administration (shops, plans). Super admins only; no shop context needed.
Route::middleware(['auth', 'can:platform'])->prefix('platform')->name('platform.')->group(function (): void {
    Route::get('/', PlatformDashboardController::class)->name('dashboard');
    Route::resource('plans', SubscriptionPlanController::class)->except(['show']);
    Route::get('payments/export', [PlatformPaymentController::class, 'export'])->name('payments.export');
    Route::get('payments', [PlatformPaymentController::class, 'index'])->name('payments.index');
    Route::get('notices', [PlatformPaymentNoticeController::class, 'index'])->name('notices.index');
    Route::post('notices/{notice}/handle', [PlatformPaymentNoticeController::class, 'handle'])->name('notices.handle');
    Route::resource('tenants', TenantController::class)->only(['index', 'show', 'create', 'store', 'edit', 'update', 'destroy']);
    Route::post('tenants/{tenant}/renewals', [TenantRenewalController::class, 'store'])->name('tenants.renewals.store');
});

// A shop that is read-only (or has no subscription left) must still be able to tell us it has paid, so this sits outside the subscription check.
Route::middleware(['auth', 'tenant.required', 'throttle:10,1'])->post('/billing/notices', [PaymentNoticeController::class, 'store'])->name('billing.notices.store');

// Everything below reads or writes shop data, so it needs a resolved tenant.
Route::middleware(['auth', 'tenant.required', 'subscription'])->group(function (): void {
    Route::get('/search', SearchController::class)->name('search');
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::get('/pos/products', [PosController::class, 'products'])->name('pos.products');
    Route::get('/pos/snapshot', [PosController::class, 'snapshot'])->name('pos.snapshot');
    Route::post('/pos/coupon', [PosController::class, 'coupon'])->middleware('throttle:30,1')->name('pos.coupon');
    Route::get('/pos/session', [PosController::class, 'session'])->name('pos.session');
    Route::post('/pos/checkout', CheckoutController::class)->name('pos.checkout');
    Route::post('/pos/mobile-money', [MobileMoneyController::class, 'store'])->middleware('throttle:30,1')->name('pos.mobile-money.store');
    Route::get('/pos/mobile-money/{paymentRequest}', [MobileMoneyController::class, 'show'])->name('pos.mobile-money.show');
    Route::get('/pos/customers', [PosCustomerController::class, 'index'])->name('pos.customers.index');
    Route::post('/pos/customers', [PosCustomerController::class, 'store'])->name('pos.customers.store');
    Route::get('/pos/held', [PosController::class, 'heldIndex'])->name('pos.held.index');
    Route::post('/pos/held', [PosController::class, 'hold'])->name('pos.held.store');
    Route::post('/pos/held/{heldOrder}/resume', [PosController::class, 'resume'])->name('pos.held.resume');
    Route::delete('/pos/held/{heldOrder}', [PosController::class, 'discard'])->name('pos.held.destroy');
    $lookup = ['index', 'store', 'update', 'destroy'];
    Route::get('/products/export', [ProductTransferController::class, 'export'])->name('products.export');
    Route::get('/products/import', [ProductTransferController::class, 'create'])->name('products.import');
    Route::get('/products/import/template', [ProductTransferController::class, 'template'])->name('products.import.template');
    Route::post('/products/import', [ProductTransferController::class, 'store'])->name('products.import.store');
    Route::get('/products/labels', [ProductController::class, 'labels'])->name('products.labels');
    Route::get('/products/lookup', [ProductController::class, 'lookup'])->name('products.lookup');
    Route::resource('products', ProductController::class)->except(['show']);
    Route::resource('categories', CategoryController::class)->only($lookup);
    Route::resource('brands', BrandController::class)->only($lookup)->middleware('plan:brands');
    Route::resource('units', UnitController::class)->only($lookup);
    Route::resource('warehouses', WarehouseController::class)->only($lookup);
    Route::get('/stock', [StockController::class, 'index'])->name('stock.index');
    Route::get('/stock/batches', [StockBatchController::class, 'index'])->name('stock.batches');
    Route::get('/stock-adjustments', [StockAdjustmentController::class, 'index'])->name('stock-adjustments.index');
    Route::post('/stock-adjustments', [StockAdjustmentController::class, 'store'])->name('stock-adjustments.store');
    Route::resource('stocktakes', StocktakeController::class)->only(['index', 'create', 'store', 'show', 'update']);
    Route::post('/stocktakes/{stocktake}/cancel', [StocktakeController::class, 'cancel'])->name('stocktakes.cancel');
    Route::get('/stock-transfers', [StockTransferController::class, 'index'])->name('stock-transfers.index');
    Route::get('/stock-transfers/create', [StockTransferController::class, 'create'])->name('stock-transfers.create');
    Route::post('/stock-transfers', [StockTransferController::class, 'store'])->name('stock-transfers.store');
    Route::get('/stock-transfers/{stockTransfer}', [StockTransferController::class, 'show'])->name('stock-transfers.show');
    Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
    Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
    Route::get('/sales/{sale}/escpos', [SaleController::class, 'escpos'])->name('sales.escpos')->middleware('plan:printer');
    Route::get('/sales/{sale}/receipt.pdf', [SaleController::class, 'pdf'])->name('sales.pdf');
    Route::post('/sales/{sale}/review', [SaleController::class, 'markReviewed'])->name('sales.review');
    Route::post('/sales/{sale}/payments', [SaleController::class, 'storePayment'])->name('sales.payments.store');
    Route::post('/sales/{sale}/returns', [SaleReturnController::class, 'store'])->name('sales.returns.store')->middleware('plan:returns');
    Route::get('/promotions', [PromotionController::class, 'index'])->name('promotions.index');
    Route::get('/promotions/create', [PromotionController::class, 'create'])->name('promotions.create');
    Route::post('/promotions', [PromotionController::class, 'store'])->name('promotions.store');
    Route::get('/promotions/{promotion}/edit', [PromotionController::class, 'edit'])->name('promotions.edit');
    Route::put('/promotions/{promotion}', [PromotionController::class, 'update'])->name('promotions.update');
    Route::post('/promotions/{promotion}/toggle', [PromotionController::class, 'toggle'])->name('promotions.toggle');
    Route::delete('/promotions/{promotion}', [PromotionController::class, 'destroy'])->name('promotions.destroy');
    Route::get('/quotations', [QuotationController::class, 'index'])->name('quotations.index');
    Route::get('/quotations/create', [QuotationController::class, 'create'])->name('quotations.create');
    Route::post('/quotations', [QuotationController::class, 'store'])->name('quotations.store');
    Route::get('/quotations/{quotation}', [QuotationController::class, 'show'])->name('quotations.show');
    Route::get('/quotations/{quotation}/pdf', [QuotationController::class, 'pdf'])->name('quotations.pdf');
    Route::post('/quotations/{quotation}/status', [QuotationController::class, 'status'])->name('quotations.status');
    Route::post('/quotations/{quotation}/convert', [QuotationController::class, 'convert'])->name('quotations.convert');
    Route::get('/customers/{customer}/statement', [CustomerStatementController::class, 'show'])->name('customers.statement');
    Route::resource('customers', CustomerController::class)->only($lookup);
    Route::get('/purchases', [PurchaseController::class, 'index'])->name('purchases.index')->middleware('plan:purchasing');
    Route::get('/purchases/create', [PurchaseController::class, 'create'])->name('purchases.create')->middleware('plan:purchasing');
    Route::post('/purchases', [PurchaseController::class, 'store'])->name('purchases.store')->middleware('plan:purchasing');
    Route::get('/purchases/{purchase}', [PurchaseController::class, 'show'])->name('purchases.show')->middleware('plan:purchasing');
    Route::post('/purchases/{purchase}/payments', [PurchaseController::class, 'storePayment'])->name('purchases.payments.store')->middleware('plan:purchasing');
    Route::post('/purchases/{purchase}/returns', [PurchaseReturnController::class, 'store'])->name('purchases.returns.store')->middleware('plan:purchasing');
    Route::resource('suppliers', SupplierController::class)->only($lookup)->middleware('plan:purchasing');
    Route::view('/people', 'modules.placeholder', ['title' => 'app.menu.people'])->name('people.index');
    Route::get('/reports/branches', [BranchReportController::class, 'index'])->name('reports.branches')->middleware('plan:branch_reports');
    Route::get('/reports/branches/export', [BranchReportController::class, 'export'])->name('reports.branches.export')->middleware('plan:branch_reports');
    Route::get('/business-statements', [BusinessStatementController::class, 'index'])->name('business-statements.index')->middleware('plan:reports');
    Route::post('/business-statements', [BusinessStatementController::class, 'store'])->name('business-statements.store')->middleware('plan:reports');
    Route::get('/business-statements/{statement}', [BusinessStatementController::class, 'show'])->name('business-statements.show')->middleware('plan:reports');
    Route::get('/business-statements/{statement}/pdf', [BusinessStatementController::class, 'pdf'])->name('business-statements.pdf')->middleware('plan:reports');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index')->middleware('plan:reports');
    Route::get('/reports/export/{report}', [ReportController::class, 'export'])
        ->where('report', 'daily|products|payments|balances|stock')->name('reports.export')->middleware('plan:reports');
    Route::get('/expenses/export', [ExpenseController::class, 'export'])->name('expenses.export')->middleware('plan:expenses');
    Route::resource('expenses', ExpenseController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('plan:expenses');
    Route::resource('expense-categories', ExpenseCategoryController::class)->only($lookup)->middleware('plan:expenses');
    Route::get('/till-closings', [TillClosingController::class, 'index'])->name('till-closings.index');
    Route::get('/till-closings/create', [TillClosingController::class, 'create'])->name('till-closings.create');
    Route::post('/till-closings', [TillClosingController::class, 'store'])->name('till-closings.store');
    Route::get('/till-closings/{tillClosing}', [TillClosingController::class, 'show'])->name('till-closings.show');
    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.index');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::get('/settings/export', [DataExportController::class, 'index'])->name('settings.export');
    Route::get('/settings/export/{dataset}', [DataExportController::class, 'download'])->where('dataset', '[a-z_]+')->middleware('throttle:20,1')->name('settings.export.download');
    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::post('/onboarding/dismiss', [OnboardingController::class, 'dismiss'])->name('onboarding.dismiss');
    Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit.index')->middleware('plan:audit_log');
    Route::middleware('plan:custom_roles')->group(function (): void {
        Route::resource('roles', RoleController::class)->only(['index', 'store', 'update', 'destroy']);
    });
    Route::get('/settings/domain', [DomainController::class, 'show'])->name('settings.domain');
    Route::post('/settings/domain', [DomainController::class, 'store'])->middleware('throttle:10,1')->name('settings.domain.store');
    Route::post('/settings/domain/check', [DomainController::class, 'check'])->middleware('throttle:20,1')->name('settings.domain.check');
    Route::delete('/settings/domain', [DomainController::class, 'destroy'])->name('settings.domain.destroy');
    Route::get('/settings/integrations', [IntegrationController::class, 'index'])->name('settings.integrations')->middleware('plan:printer,mobile_money,fiscal');
    Route::put('/settings/integrations/{channel}', [IntegrationController::class, 'update'])->name('settings.integrations.update')->middleware('plan:printer,mobile_money,fiscal');
});
