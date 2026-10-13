<?php

namespace App\Domain\Settings\Services;

use App\Domain\Catalog\Models\Product;
use App\Domain\Finance\Models\Expense;
use App\Domain\Inventory\Models\ProductStock;
use App\Domain\People\Models\Customer;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Sales\Models\Payment;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\SaleItem;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\LazyCollection;

/**
 * The shop's own data as plain tables, so an Owner can take it elsewhere. Every query runs inside the current shop's
 * tenant scope, so one shop can never export another's rows. Money is written in major units (e.g. 1500.00).
 */
class DataExport
{
    /** @return list<string> */
    public function keys(): array
    {
        return ['products', 'customers', 'suppliers', 'sales', 'sale_items', 'payments', 'expenses', 'stock'];
    }

    /** @return list<string> */
    public function header(string $key): array
    {
        return match ($key) {
            'products' => ['sku', 'barcode', 'name', 'type', 'category', 'cost_price', 'selling_price', 'tax_rate', 'tax_inclusive', 'track_stock', 'alert_quantity', 'active', 'deleted'],
            'customers' => ['name', 'phone', 'email', 'address', 'credit_limit', 'outstanding', 'active'],
            'suppliers' => ['name', 'phone', 'email', 'address', 'active'],
            'sales' => ['number', 'date', 'customer', 'subtotal', 'discount', 'tax', 'total', 'returned', 'paid', 'balance', 'status', 'note'],
            'sale_items' => ['sale_number', 'sku', 'product', 'quantity', 'unit_price', 'discount', 'tax', 'total'],
            'payments' => ['sale_number', 'date', 'method', 'amount', 'reference'],
            'expenses' => ['date', 'category', 'amount', 'currency', 'method', 'reference', 'note'],
            'stock' => ['sku', 'product', 'warehouse', 'quantity'],
            default => [],
        };
    }

    /** @return LazyCollection<int, list<mixed>> */
    public function rows(string $key): LazyCollection
    {
        return match ($key) {
            'products' => Product::withTrashed()->with('category')->lazyById()->map(fn (Product $p): array => [
                $p->sku, $p->barcode, $p->name, $p->type, $p->category?->name, $this->money($p->cost_price), $this->money($p->selling_price),
                $p->tax_rate, $this->flag($p->tax_inclusive), $this->flag($p->track_stock), $p->alert_quantity, $this->flag($p->is_active), $this->flag($p->deleted_at !== null),
            ]),
            'customers' => Customer::query()->withSum('sales as outstanding_sum', 'balance_due')->lazyById()->map(fn (Customer $c): array => [
                $c->name, $c->phone, $c->email, $c->address, $c->credit_limit === null ? '' : $this->money($c->credit_limit), $this->money($c->outstanding()), $this->flag($c->is_active),
            ]),
            'suppliers' => Supplier::query()->lazyById()->map(fn (Supplier $s): array => [$s->name, $s->phone, $s->email, $s->address, $this->flag($s->is_active)]),
            'sales' => Sale::query()->with('customer')->lazyById()->map(fn (Sale $s): array => [
                $s->number, $s->sold_at?->format('Y-m-d H:i'), $s->customer?->name, $this->money($s->subtotal), $this->money($s->discount_total), $this->money($s->tax_total),
                $this->money($s->total), $this->money($s->returned_total), $this->money($s->amount_paid), $this->money($s->balance_due), $s->payment_status, $s->note,
            ]),
            'sale_items' => SaleItem::query()->with('sale')->lazyById()->map(fn (SaleItem $i): array => [
                $i->sale?->number, $i->sku, $i->product_name, $i->quantity, $this->money($i->unit_price), $this->money($i->discount), $this->money($i->tax), $this->money($i->total),
            ]),
            'payments' => Payment::query()->with('sale')->lazyById()->map(fn (Payment $p): array => [
                $p->sale?->number, $p->paid_at?->format('Y-m-d H:i'), $p->method, $this->money($p->amount), $p->reference,
            ]),
            'expenses' => Expense::query()->with('category')->lazyById()->map(fn (Expense $e): array => [
                $e->spent_on instanceof Carbon ? $e->spent_on->format('Y-m-d') : (string) $e->spent_on, $e->category?->name, $this->money($e->amount), $e->currency, $e->method, $e->reference, $e->note,
            ]),
            'stock' => ProductStock::query()->with(['product', 'warehouse'])->lazyById()->map(fn (ProductStock $s): array => [
                $s->product?->sku, $s->product?->name, $s->warehouse?->name, $s->quantity,
            ]),
            default => LazyCollection::empty(),
        };
    }

    private function money(int $minor): string
    {
        return Money::toMajor($minor);
    }

    private function flag(bool $value): string
    {
        return $value ? '1' : '0';
    }
}
