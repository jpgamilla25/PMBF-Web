<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait Filterable
{
    /**
     * Apply where clauses from an associative array of filters.
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        foreach ($filters as $column => $value) {
            if (is_null($value) || $value === '') {
                continue;
            }

            if (is_array($value)) {
                $query->whereIn($column, $value);
            } else {
                $query->where($column, $value);
            }
        }

        return $query;
    }

    /**
     * Apply LIKE search across multiple columns.
     */
    public function scopeSearch(Builder $query, ?string $search, array $columns): Builder
    {
        if (is_null($search) || $search === '') {
            return $query;
        }

        $search = '%' . trim($search) . '%';

        return $query->where(function (Builder $q) use ($search, $columns) {
            foreach ($columns as $column) {
                $q->orWhere($column, 'LIKE', $search);
            }
        });
    }

    /**
     * Apply date range filter on a given column.
     */
    public function scopeDateRange(
        Builder $query,
        ?string $from,
        ?string $to,
        string $column = 'created_at'
    ): Builder {
        if ($from) {
            $query->where($column, '>=', $from);
        }

        if ($to) {
            $query->where($column, '<=', $to);
        }

        return $query;
    }
}
