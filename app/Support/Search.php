<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Case-insensitive "contains" search over columns, and over columns of relations ("customer.name"). */
class Search
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $columns
     * @return Builder<TModel>
     */
    public static function apply(Builder $query, ?string $term, array $columns): Builder
    {
        $term = trim((string) $term);

        if ($term === '' || $columns === []) {
            return $query;
        }

        // "!" is the escape character, which behaves the same on SQLite, PostgreSQL and MySQL.
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term)).'%';

        return $query->where(function (Builder $group) use ($query, $columns, $like): void {
            foreach ($columns as $column) {
                if (str_contains($column, '.')) {
                    [$relation, $related] = explode('.', $column, 2);
                    $group->orWhereHas($relation, function (Builder $sub) use ($related, $like): void {
                        $sub->whereRaw(self::expression($sub->getModel()->qualifyColumn($related)), [$like]);
                    });
                } else {
                    $group->orWhereRaw(self::expression($query->getModel()->qualifyColumn($column)), [$like]);
                }
            }
        });
    }

    private static function expression(string $qualifiedColumn): string
    {
        return 'LOWER('.$qualifiedColumn.") LIKE ? ESCAPE '!'";
    }
}
