<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/** Column sorting for list pages. Only the columns a controller whitelists can ever reach the query. */
class Sort
{
    /**
     * Orders the query by the `sort` and `dir` query parameters and returns the effective key and direction,
     * which the view uses to draw the arrows. Unknown keys fall back to the default; ties are broken by id.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, string>  $allowed  sort key => column
     * @return array{0: string, 1: 'asc'|'desc'}
     */
    public static function apply(Builder $query, Request $request, array $allowed, string $defaultKey, string $defaultDir = 'asc'): array
    {
        $key = (string) $request->query('sort');
        $key = array_key_exists($key, $allowed) ? $key : $defaultKey;

        // A direction in the URL only counts together with an explicit, valid sort key.
        $asked = $key === (string) $request->query('sort') ? strtolower((string) $request->query('dir')) : '';
        $wanted = in_array($asked, ['asc', 'desc'], true) ? $asked : ($key === $defaultKey ? $defaultDir : 'asc');
        $dir = $wanted === 'desc' ? 'desc' : 'asc';

        $query->orderBy($query->getModel()->qualifyColumn($allowed[$key]), $dir)
            ->orderBy($query->getModel()->qualifyColumn('id'), 'desc');

        return [$key, $dir];
    }
}
