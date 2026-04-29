<?php

namespace App\QueryBuilders;

use App\Helpers\QueryBuilderHelper;
use App\Models\CustomerCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;


class CustomerCategoryQueryBuilder
{
    public function customerCategoryBuilder(Request $request ,  bool $onlyTrashed = false)
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        $builder = QueryBuilderHelper::build(
            model: CustomerCategory::class,

            joins: [],
            selects: [
                'customer_categories.id',
                'customer_categories.category_name',
                'customer_categories.label_color',
                'customer_categories.description',
                'customer_categories.discount_percentage',
                'customer_categories.created_at',
                'customer_categories.updated_at',
            ],

            allowedFilters: [
                AllowedFilter::exact('id'),
                AllowedFilter::exact('role'),
                AllowedFilter::callback('is_deleted', function (Builder $query, $value) {
                    $isDeleted = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if ($isDeleted === true || (string) $value === '1') {
                        $query->onlyTrashed();
                        return;
                    }
                    $query->whereNull('customer_categories.deleted_at');
                }),

                // Search by name / email / phone_number
                AllowedFilter::callback('search', function (Builder $query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('customer_categories.category_name', 'LIKE', "%{$value}%")
                            ->orWhere('customer_categories.description', 'LIKE', "%{$value}%");
                    });
                }),
            ],

            allowedSorts: [
                'id',
                'category_name',
                'discount_percentage',
                'created_at',
                'updated_at',
            ],

            defaultSort: '-created_at',
            withRelations: ['customers'],
            withCounts: ['customers'],
        );
        
        if ($onlyTrashed) {
            $builder = $builder->onlyTrashed();
        }

        return $builder ->paginate($perPage)
        ->appends($request->query());
    }
}