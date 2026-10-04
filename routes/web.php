<?php

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
    Route::view('/inventory', 'modules.placeholder', ['title' => 'app.menu.inventory'])->name('inventory.index');
    Route::view('/sales', 'modules.placeholder', ['title' => 'app.menu.sales'])->name('sales.index');
    Route::view('/purchases', 'modules.placeholder', ['title' => 'app.menu.purchases'])->name('purchases.index');
    Route::view('/people', 'modules.placeholder', ['title' => 'app.menu.people'])->name('people.index');
    Route::view('/reports', 'modules.placeholder', ['title' => 'app.menu.reports'])->name('reports.index');
    Route::view('/settings', 'modules.placeholder', ['title' => 'app.menu.settings'])->name('settings.index');
});
