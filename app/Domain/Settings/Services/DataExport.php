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
use App\Models\Tenant;
use App\Support\Money;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonInterface;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The shop's own data as plain tables, so an Owner can take it elsewhere. Rows are read a page at a time, each page
 * inside the given shop's tenant scope (a download streams after the request has ended, when no shop is active), so
 * one shop can never export another's rows and a big shop does not exhaust memory. Money is written in major units.
 */
class DataExport
{
    private const PAGE = 500;

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

    /** @return Generator<int, list<mixed>> */
    public function rows(Tenant $tenant, string $key): Generator
    {
        $context = app(TenantContext::class);
        $after = 0;

        while (true) {
            $page = $context->run($tenant, fn (): array => $this->page($key, $after));

            if ($page === []) {
                return;
            }

            foreach ($page as [$id, $row]) {
                $after = $id;

                yield $row;
            }
        }
    }

    /** @return list<array{0: int, 1: list<mixed>}> */
    private function page(string $key, int $after): array
    {
        return match ($key) {
            'products' => $this->read(Product::withTrashed()->with('category'), $after, fn (Product $p): array => [
                $p->sku, $p->barcode, $p->name, $p->type, $p->category?->name, $this->money($p->cost_price), $this->money($p->selling_price),
                $p->tax_rate, $this->flag($p->tax_inclusive), $this->flag($p->track_stock), $p->alert_quantity, $this->flag($p->is_active), $this->flag($p->deleted_at !== null),
            ]),
            'customers' => $this->read(Customer::query()->withSum('sales as outstanding_sum', 'balance_due'), $after, fn (Customer $c): array => [
                $c->name, $c->phone, $c->email, $c->address, $c->credit_limit === null ? '' : $this->money($c->credit_limit), $this->money($c->outstanding()), $this->flag($c->is_active),
            ]),
            'suppliers' => $this->read(Supplier::query(), $after, fn (Supplier $s): array => [$s->name, $s->phone, $s->email, $s->address, $this->flag($s->is_active)]),
            'sales' => $this->read(Sale::query()->with('customer'), $after, fn (Sale $s): array => [
                $s->number, $this->date($s->sold_at, 'Y-m-d H:i'), $s->customer?->name, $this->money($s->subtotal), $this->money($s->discount_total), $this->money($s->tax_total),
                $this->money($s->total), $this->money($s->returned_total), $this->money($s->amount_paid), $this->money($s->balance_due), $s->payment_status, $s->note,
            ]),
            'sale_items' => $this->read(SaleItem::query()->with('sale'), $after, fn (SaleItem $i): array => [
                $i->sale?->number, $i->sku, $i->product_name, $i->quantity, $this->money($i->unit_price), $this->money($i->discount), $this->money($i->tax), $this->money($i->total),
            ]),
            'payments' => $this->read(Payment::query()->with('sale'), $after, fn (Payment $p): array => [
                $p->sale?->number, $this->date($p->paid_at, 'Y-m-d H:i'), $p->method, $this->money($p->amount), $p->reference,
            ]),
            'expenses' => $this->read(Expense::query()->with('category'), $after, fn (Expense $e): array => [
                $this->date($e->spent_on, 'Y-m-d'), $e->category?->name, $this->money($e->amount), $e->currency, $e->method, $e->reference, $e->note,
            ]),
            'stock' => $this->read(ProductStock::query()->with(['product', 'warehouse']), $after, fn (ProductStock $s): array => [
                $s->product?->sku, $s->product?->name, $s->warehouse?->name, $s->quantity,
            ]),
            default => [],
        };
    }

    /**
     * One page of rows after the given id, each mapped to a flat list.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  callable(TModel): list<mixed>  $map
     * @return list<array{0: int, 1: list<mixed>}>
     */
    private function read(Builder $query, int $after, callable $map): array
    {
        $key = $query->getModel()->getQualifiedKeyName();
        $out = [];

        foreach ($query->where($key, '>', $after)->orderBy($key)->limit(self::PAGE)->get() as $model) {
            $out[] = [(int) $model->getKey(), $map($model)];
        }

        return $out;
    }

    private function money(int $minor): string
    {
        return Money::toMajor($minor);
    }

    private function flag(bool $value): string
    {
        return $value ? '1' : '0';
    }

    private function date(mixed $value, string $format): string
    {
        return $value instanceof CarbonInterface ? $value->format($format) : (string) $value;
    }
}
