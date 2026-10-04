<?php

use App\Http\Controllers\Catalog\BrandController;
use App\Http\Controllers\Catalog\CategoryController;
use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\Catalog\UnitController;
use App\Http\Controllers\Inventory\StockAdjustmentController;
use App\Http\Controllers\Inventory\StockController;
use App\Http\Controllers\Inventory\WarehouseController;
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

Route::middleware('auth')->group(function (): void {
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::view('/pos', 'pos.index')->name('pos.index');
    $lookup = ['index', 'store', 'update', 'destroy'];
    Route::resource('products', ProductController::class)->except(['show']);
    Route::resource('categories', CategoryController::class)->only($lookup);
    Route::resource('brands', BrandController::class)->only($lookup);
    Route::resource('units', UnitController::class)->only($lookup);
    Route::resource('warehouses', WarehouseController::class)->only($lookup);
    Route::get('/stock', [StockController::class, 'index'])->name('stock.index');
    Route::get('/stock-adjustments', [StockAdjustmentController::class, 'index'])->name('stock-adjustments.index');
    Route::post('/stock-adjustments', [StockAdjustmentController::class, 'store'])->name('stock-adjustments.store');
    Route::view('/sales', 'modules.placeholder', ['title' => 'app.menu.sales'])->name('sales.index');
    Route::view('/purchases', 'modules.placeholder', ['title' => 'app.menu.purchases'])->name('purchases.index');
    Route::view('/people', 'modules.placeholder', ['title' => 'app.menu.people'])->name('people.index');
    Route::view('/reports', 'modules.placeholder', ['title' => 'app.menu.reports'])->name('reports.index');
    Route::view('/settings', 'modules.placeholder', ['title' => 'app.menu.settings'])->name('settings.index');
});
