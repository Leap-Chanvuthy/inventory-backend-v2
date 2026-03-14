<?php

namespace App\Helpers;

use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class QueryBuilderHelper
{
    /**
     * Build a dynamic reusable query builder with relationship support
     *
     * @param string $model
     * @param array  $joins
     * @param array  $selects
     * @param array  $allowedFilters
     * @param array  $allowedSorts
     * @param string $defaultSort
     * @param array  $withRelations   — Example: ['images', 'category', 'supplier.address']
     * @param array  $withCounts      — Example: ['products', 'images']
     */
    public static function build(
        string $model,
        array $joins = [],
        array $selects = ['*'],
        array $allowedFilters = [],
        array $allowedSorts = [],
        string $defaultSort = '-created_at',
        array $withRelations = [],
        array $withCounts = []
    ): QueryBuilder {

        $builder = QueryBuilder::for($model);

        // Dynamic joins
        foreach ($joins as $join) {
            [$table, $left, $operator, $right] = $join;
            $builder->leftJoin($table, $left, $operator, $right);
        }

        $builder->select($selects);

        // Load relationships
        if (!empty($withRelations)) {
            $builder->with($withRelations);
        }

        // Load relationship counts after select so count columns are preserved
        if (!empty($withCounts)) {
            $builder->withCount($withCounts);
        }

        return $builder
            ->allowedFilters($allowedFilters)
            ->allowedSorts($allowedSorts)
            ->defaultSort($defaultSort);
    }
}
