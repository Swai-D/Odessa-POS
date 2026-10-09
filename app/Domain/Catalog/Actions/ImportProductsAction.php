<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Unit;
use App\Domain\Inventory\Models\Warehouse;
use App\Models\User;
use App\Support\Money;
use App\Support\Plans;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Creates and updates products from a CSV file, all or nothing.
 *
 * Every row is checked first and nothing is written unless the whole file is clean. Products are matched by SKU:
 * a known SKU is updated (only the cells that are filled in), a new SKU is created, with its opening stock put in
 * the default warehouse. Opening stock is never applied to an existing product, so uploading the same file twice
 * is harmless. Categories, brands and units are matched by name and created when missing.
 */
class ImportProductsAction
{
    /** Columns a file may contain, in template order. */
    public const COLUMNS = [
        'sku', 'name', 'barcode', 'type', 'category', 'brand', 'unit', 'cost_price', 'selling_price',
        'tax_rate', 'tax_inclusive', 'track_stock', 'alert_quantity', 'is_active', 'opening_stock',
    ];

    /** Present in an export for information; accepted and ignored on import so an export can be re-uploaded. */
    private const IGNORED = ['stock'];

    private const BOOLEANS = ['tax_inclusive', 'track_stock', 'is_active'];

    private const MAX_PROBLEMS = 50;

    public function __construct(private readonly SaveProductAction $save) {}

    /** @return array{created: int, updated: int, created_lookups: int, notes: list<string>} */
    public function handle(string $path, ?User $user): array
    {
        [$header, $rows] = $this->read($path);
        [$clean, $problems] = $this->validate($header, $rows);

        $existing = Product::query()->withTrashed()->whereIn('sku', array_values(array_filter(array_column($clean, 'sku'))))->get()->keyBy('sku');
        $warehouse = Warehouse::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('id')->first();

        foreach ($clean as $line => $row) {
            $found = $existing->get($row['sku'] ?? '');

            if ($found instanceof Product && $found->trashed()) {
                $problems[] = $this->problem($line, __('catalog.import.deleted_sku', ['sku' => $row['sku'] ?? '']));
            } elseif (! $found && (float) ($row['opening_stock'] ?? 0) > 0 && $warehouse === null) {
                $problems[] = $this->problem($line, __('catalog.import.no_warehouse'));
            }
        }

        if ($problems !== []) {
            throw new ProductImportException(array_slice($problems, 0, self::MAX_PROBLEMS), count($problems));
        }

        return DB::transaction(fn (): array => $this->apply($clean, $existing, $warehouse, $header, $user));
    }

    /**
     * @return array{0: list<string>, 1: array<int, list<string>>} header names and data rows keyed by file line
     */
    private function read(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new ProductImportException([__('catalog.import.unreadable')], 1);
        }

        $first = (string) fgets($handle);
        rewind($handle);
        // Excel in many locales saves CSV with semicolons.
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';

        $rows = [];
        $header = null;
        $line = 0;

        while (($cells = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $line++;

            if ($header === null) {
                $cells[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $cells[0]);
                $header = array_map(fn ($h): string => str_replace(' ', '_', strtolower(trim((string) $h))), $cells);

                continue;
            }

            if (count(array_filter($cells, fn ($c): bool => trim((string) $c) !== '')) === 0) {
                continue;
            }

            $rows[$line] = array_map(fn ($c): string => trim((string) $c), $cells);

            if (count($rows) > (int) config('pos.import_max_rows')) {
                fclose($handle);
                throw new ProductImportException([__('catalog.import.too_many', ['max' => config('pos.import_max_rows')])], 1);
            }
        }

        fclose($handle);

        if ($header === null || $rows === []) {
            throw new ProductImportException([__('catalog.import.empty')], 1);
        }

        $unknown = array_values(array_diff($header, self::COLUMNS, self::IGNORED));
        if ($unknown !== []) {
            throw new ProductImportException([__('catalog.import.unknown_columns', ['columns' => implode(', ', $unknown)])], 1);
        }

        $missing = array_values(array_diff(['sku', 'name'], $header));
        if ($missing !== []) {
            throw new ProductImportException([__('catalog.import.missing_columns', ['columns' => implode(', ', $missing)])], 1);
        }

        return [$header, $rows];
    }

    /**
     * Checks each row; returns the cleaned ones keyed by file line, and the problems found.
     *
     * @param  list<string>  $header
     * @param  array<int, list<string>>  $rows
     * @return array{0: array<int, array<string, string>>, 1: list<string>} the cleaned rows and the problems found
     */
    private function validate(array $header, array $rows): array
    {
        $problems = [];
        $clean = [];
        $seen = [];

        foreach ($rows as $line => $cells) {
            $row = [];
            foreach ($header as $i => $column) {
                if (in_array($column, self::COLUMNS, true) && ($cells[$i] ?? '') !== '') {
                    $row[$column] = $this->normalise($column, $cells[$i]);
                }
            }

            $validator = Validator::make($row, [
                'sku' => ['required', 'string', 'max:100'],
                'name' => ['required', 'string', 'max:255'],
                'barcode' => ['nullable', 'string', 'max:100'],
                'type' => ['nullable', 'in:'.Product::TYPE_STANDARD.','.Product::TYPE_SERVICE],
                'category' => ['nullable', 'string', 'max:100'],
                'brand' => ['nullable', 'string', 'max:100'],
                'unit' => ['nullable', 'string', 'max:100'],
                'cost_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
                'selling_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
                'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'tax_inclusive' => ['nullable', 'boolean'],
                'track_stock' => ['nullable', 'boolean'],
                'is_active' => ['nullable', 'boolean'],
                'alert_quantity' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
                'opening_stock' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            ], [], array_combine(self::COLUMNS, self::COLUMNS));

            foreach ($validator->errors()->all() as $message) {
                $problems[] = $this->problem($line, $message);
            }

            $sku = $row['sku'] ?? '';
            if ($sku !== '' && isset($seen[$sku])) {
                $problems[] = $this->problem($line, __('catalog.import.duplicate_sku', ['sku' => $sku, 'first' => $seen[$sku]]));
            }
            $seen[$sku] = $line;

            $clean[$line] = $row;
        }

        return [$clean, $problems];
    }

    private function problem(int $line, mixed $message): string
    {
        return (string) __('catalog.import.line', ['line' => $line, 'message' => is_string($message) ? $message : '']);
    }

    /** Makes spreadsheet-style cells readable to the validator: "1,500.50" -> "1500.50", "yes" -> "1". */
    private function normalise(string $column, string $value): string
    {
        if (in_array($column, self::BOOLEANS, true)) {
            $lower = strtolower($value);

            return match (true) {
                in_array($lower, ['1', 'yes', 'y', 'true', 'ndio'], true) => '1',
                in_array($lower, ['0', 'no', 'n', 'false', 'hapana'], true) => '0',
                default => $value,
            };
        }

        if (in_array($column, ['cost_price', 'selling_price', 'tax_rate', 'alert_quantity', 'opening_stock'], true)) {
            $plain = str_replace(' ', '', $value);

            return preg_match('/^\d{1,3}(,\d{3})+(\.\d+)?$/', $plain) === 1 ? str_replace(',', '', $plain) : $plain;
        }

        return $column === 'type' ? strtolower($value) : $value;
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     * @param  Collection<string, Product>  $existing
     * @param  list<string>  $header
     * @return array{created: int, updated: int, created_lookups: int, notes: list<string>}
     */
    private function apply(array $rows, $existing, ?Warehouse $warehouse, array $header, ?User $user): array
    {
        $created = $updated = $lookupsCreated = $ignoredStock = 0;
        $notes = [];
        $brandsAllowed = Plans::current()->allows('brands');
        $cache = ['category' => [], 'brand' => [], 'unit' => []];

        $resolve = function (string $kind, string $name) use (&$cache, &$lookupsCreated): int {
            $key = mb_strtolower($name);

            if (! isset($cache[$kind][$key])) {
                $class = ['category' => Category::class, 'brand' => Brand::class, 'unit' => Unit::class][$kind];
                /** @var Model|null $found */
                $found = $class::query()->whereRaw('LOWER(name) = ?', [$key])->first();

                if ($found === null) {
                    $attributes = ['name' => $name, 'is_active' => true];
                    if ($kind === 'category') {
                        $attributes['slug'] = $this->freeSlug($name);
                    }
                    if ($kind === 'unit') {
                        $attributes += ['short_name' => mb_substr($name, 0, 10), 'allow_decimal' => false];
                    }
                    $found = $class::query()->create($attributes);
                    $lookupsCreated++;
                }

                $cache[$kind][$key] = (int) $found->getKey();
            }

            return $cache[$kind][$key];
        };

        if (in_array('brand', $header, true) && ! $brandsAllowed && array_filter(array_column($rows, 'brand')) !== []) {
            $notes[] = __('catalog.import.brand_ignored');
        }

        foreach ($rows as $row) {
            $product = $existing->get($row['sku']);

            $data = $product instanceof Product ? $this->currentValues($product) : $this->newDefaults();
            $data['name'] = $row['name'];
            $data['sku'] = $row['sku'];

            foreach (['barcode', 'type', 'cost_price', 'selling_price', 'tax_rate', 'alert_quantity'] as $field) {
                if (isset($row[$field])) {
                    $data[$field] = $row[$field];
                }
            }
            foreach (self::BOOLEANS as $field) {
                if (isset($row[$field])) {
                    $data[$field] = $row[$field] === '1';
                }
            }
            if (isset($row['category'])) {
                $data['category_id'] = $resolve('category', $row['category']);
            }
            if (isset($row['unit'])) {
                $data['unit_id'] = $resolve('unit', $row['unit']);
            }
            if (isset($row['brand']) && $brandsAllowed) {
                $data['brand_id'] = $resolve('brand', $row['brand']);
            }

            if ($product instanceof Product) {
                $this->save->handle($data, $product, $user);
                $updated++;
                $ignoredStock += isset($row['opening_stock']) && (float) $row['opening_stock'] > 0 ? 1 : 0;
            } else {
                if (isset($row['opening_stock']) && $warehouse !== null) {
                    $data['opening_quantity'] = $row['opening_stock'];
                    $data['warehouse_id'] = $warehouse->getKey();
                }
                $this->save->handle($data, null, $user);
                $created++;
            }
        }

        if ($ignoredStock > 0) {
            $notes[] = __('catalog.import.stock_ignored', ['count' => $ignoredStock]);
        }

        return ['created' => $created, 'updated' => $updated, 'created_lookups' => $lookupsCreated, 'notes' => $notes];
    }

    /** A category slug from the name that no other category of this shop uses yet. */
    private function freeSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;

        for ($suffix = 2; Category::query()->where('slug', $slug)->exists(); $suffix++) {
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }

    /** @return array<string, mixed> */
    private function newDefaults(): array
    {
        return [
            'type' => Product::TYPE_STANDARD, 'category_id' => null, 'brand_id' => null, 'unit_id' => null,
            'cost_price' => 0, 'selling_price' => 0, 'tax_rate' => 0, 'tax_inclusive' => true,
            'track_stock' => true, 'alert_quantity' => 0, 'is_weighed' => false, 'track_batch' => false,
            'track_expiry' => false, 'is_active' => true, 'barcode' => null, 'description' => null,
        ];
    }

    /** The product as the save action expects it (prices in major units), so unchanged cells stay unchanged. @return array<string, mixed> */
    private function currentValues(Product $product): array
    {
        return [
            'type' => $product->type, 'category_id' => $product->category_id, 'brand_id' => $product->brand_id,
            'unit_id' => $product->unit_id, 'barcode' => $product->barcode, 'description' => $product->description,
            'cost_price' => Money::toMajor($product->cost_price), 'selling_price' => Money::toMajor($product->selling_price),
            'tax_rate' => $product->tax_rate, 'tax_inclusive' => $product->tax_inclusive, 'track_stock' => $product->track_stock,
            'alert_quantity' => $product->alert_quantity, 'is_weighed' => $product->is_weighed,
            'track_batch' => $product->track_batch, 'track_expiry' => $product->track_expiry, 'is_active' => $product->is_active,
        ];
    }
}
