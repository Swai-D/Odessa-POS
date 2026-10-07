<?php

use App\Http\Controllers\Catalog\BrandController;
use App\Http\Controllers\Catalog\CategoryController;
use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\Catalog\UnitController;
use App\Http\Controllers\Inventory\StockAdjustmentController;
use App\Http\Controllers\Inventory\StockController;
use App\Http\Controllers\Inventory\WarehouseController;
use App\Http\Controllers\People\CustomerController;
use App\Http\Controllers\Platform\TenantController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Purchasing\PurchaseController;
use App\Http\Controllers\Purchasing\SupplierController;
use App\Http\Controllers\Sales\CheckoutController;
use App\Http\Controllers\Sales\PosController;
use App\Http\Controllers\Sales\PosCustomerController;
use App\Http\Controllers\Sales\SaleController;
use App\Http\Controllers\Sales\SaleReturnController;
use App\Http\Controllers\Settings\IntegrationController;
use App\Http\Controllers\Settings\SettingsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

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
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
});

// Platform administration (shops, plans). Super admins only; no shop context needed.
Route::middleware(['auth', 'can:platform'])->prefix('platform')->name('platform.')->group(function (): void {
    Route::resource('tenants', TenantController::class)->only(['index', 'create', 'store', 'edit', 'update']);
});

// Everything below reads or writes shop data, so it needs a resolved tenant.
Route::middleware(['auth', 'tenant.required'])->group(function (): void {
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::get('/pos/products', [PosController::class, 'products'])->name('pos.products');
    Route::post('/pos/checkout', CheckoutController::class)->name('pos.checkout');
    Route::post('/pos/customers', [PosCustomerController::class, 'store'])->name('pos.customers.store');
    Route::get('/pos/held', [PosController::class, 'heldIndex'])->name('pos.held.index');
    Route::post('/pos/held', [PosController::class, 'hold'])->name('pos.held.store');
    Route::post('/pos/held/{heldOrder}/resume', [PosController::class, 'resume'])->name('pos.held.resume');
    Route::delete('/pos/held/{heldOrder}', [PosController::class, 'discard'])->name('pos.held.destroy');
    $lookup = ['index', 'store', 'update', 'destroy'];
    Route::resource('products', ProductController::class)->except(['show']);
    Route::resource('categories', CategoryController::class)->only($lookup);
    Route::resource('brands', BrandController::class)->only($lookup)->middleware('plan:brands');
    Route::resource('units', UnitController::class)->only($lookup);
    Route::resource('warehouses', WarehouseController::class)->only($lookup);
    Route::get('/stock', [StockController::class, 'index'])->name('stock.index');
    Route::get('/stock-adjustments', [StockAdjustmentController::class, 'index'])->name('stock-adjustments.index');
    Route::post('/stock-adjustments', [StockAdjustmentController::class, 'store'])->name('stock-adjustments.store');
    Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
    Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
    Route::get('/sales/{sale}/escpos', [SaleController::class, 'escpos'])->name('sales.escpos')->middleware('plan:integrations');
    Route::get('/sales/{sale}/receipt.pdf', [SaleController::class, 'pdf'])->name('sales.pdf');
    Route::post('/sales/{sale}/payments', [SaleController::class, 'storePayment'])->name('sales.payments.store');
    Route::post('/sales/{sale}/returns', [SaleReturnController::class, 'store'])->name('sales.returns.store')->middleware('plan:returns');
    Route::resource('customers', CustomerController::class)->only($lookup);
    Route::get('/purchases', [PurchaseController::class, 'index'])->name('purchases.index')->middleware('plan:purchasing');
    Route::get('/purchases/create', [PurchaseController::class, 'create'])->name('purchases.create')->middleware('plan:purchasing');
    Route::post('/purchases', [PurchaseController::class, 'store'])->name('purchases.store')->middleware('plan:purchasing');
    Route::get('/purchases/{purchase}', [PurchaseController::class, 'show'])->name('purchases.show')->middleware('plan:purchasing');
    Route::post('/purchases/{purchase}/payments', [PurchaseController::class, 'storePayment'])->name('purchases.payments.store')->middleware('plan:purchasing');
    Route::resource('suppliers', SupplierController::class)->only($lookup)->middleware('plan:purchasing');
    Route::view('/people', 'modules.placeholder', ['title' => 'app.menu.people'])->name('people.index');
    Route::view('/reports', 'modules.placeholder', ['title' => 'app.menu.reports'])->name('reports.index')->middleware('plan:reports');
    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.index');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::get('/settings/integrations', [IntegrationController::class, 'index'])->name('settings.integrations')->middleware('plan:integrations');
    Route::put('/settings/integrations/{channel}', [IntegrationController::class, 'update'])->name('settings.integrations.update')->middleware('plan:integrations');
});
