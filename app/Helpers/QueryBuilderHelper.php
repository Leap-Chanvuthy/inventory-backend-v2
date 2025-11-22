<?php

namespace App\Helpers;

use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Illuminate\Database\Eloquent\Builder;

class QueryBuilderHelper
{
    /**
     * Build a dynamic reusable query builder
     *
     * @param string $model          — Model::class
     * @param array  $joins          — Example: [['suppliers', 'model.supplier_id', '=', 'suppliers.id']]
     * @param array  $selects        — Array of selected fields
     * @param array  $allowedFilters — Array of AllowedFilter::...
     * @param array  $allowedSorts   — Array of column names
     * @param string $defaultSort    — default sort
     */
    public static function build(
        string $model,
        array $joins = [],
        array $selects = ['*'],
        array $allowedFilters = [],
        array $allowedSorts = [],
        string $defaultSort = '-created_at'
    ): QueryBuilder {

        $builder = QueryBuilder::for($model);

        // Apply dynamic joins
        foreach ($joins as $join) {
            [$table, $left, $operator, $right] = $join;
            $builder->leftJoin($table, $left, $operator, $right);
        }

        return $builder
            ->select($selects)
            ->allowedFilters($allowedFilters)
            ->allowedSorts($allowedSorts)
            ->defaultSort($defaultSort);
    }
}
