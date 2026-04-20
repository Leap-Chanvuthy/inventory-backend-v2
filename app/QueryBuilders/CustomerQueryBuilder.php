<?php

namespace App\QueryBuilders;

use App\Enums\CustomerStatusEnum;
use App\Helpers\QueryBuilderHelper;
use App\Models\Customer;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Illuminate\Database\Eloquent\Builder;

class CustomerQueryBuilder {

    public function filterByTags(Builder $query, array $tagIds): Builder
    {
        if (empty($tagIds)) {
            return $query;
        }

        return $query->whereHas('tags', function (Builder $tagQuery) use ($tagIds) {
            $tagQuery->whereIn('customer_tags.id', $tagIds);
        });
    }

    public function filterByStatus(Builder $query, CustomerStatusEnum $status): Builder
    {
        return $query->where('customers.customer_status', $status->value);
    }

    public function filterByCategory(Builder $query, int $categoryId): Builder
    {
        return $query->where('customers.customer_category_id', $categoryId);
    }

    public function customerBuilder(Request $request)
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        return QueryBuilderHelper::build(
            model: Customer::class,

            joins: [
                // Enable searching/sorting by category name
                ['customer_categories', 'customers.customer_category_id', '=', 'customer_categories.id'],
            ],

            selects: [
                'customers.*',
                // Useful for UI; remove if you don't need it
                'customer_categories.category_name as customer_category_name',
                'customer_categories.discount_percentage as customer_category_discount_percentage',
            ],

            allowedFilters: [
                AllowedFilter::exact('id'),
                AllowedFilter::exact('customer_status'),
                AllowedFilter::exact('customer_category_id'),

                // Search by fullname / email_address / phone_number / category name
                AllowedFilter::callback('search', function (Builder $query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('customers.fullname', 'LIKE', "%{$value}%")
                          ->orWhere('customers.customer_code', 'LIKE', "%{$value}%")
                          ->orWhere('customers.email_address', 'LIKE', "%{$value}%")
                          ->orWhere('customers.phone_number', 'LIKE', "%{$value}%")
                          ->orWhere('customer_categories.category_name', 'LIKE', "%{$value}%");
                    });
                }),

                // Optional: dedicated filter e.g. ?filter[category_name]=Retail
                AllowedFilter::callback('category_name', function (Builder $query, $value) {
                    $query->where('customer_categories.category_name', 'LIKE', "%{$value}%");
                }),
            ],

            allowedSorts: [
                'created_at',
                'updated_at',
                'fullname',
                'email_address',
                'phone_number',
                'customer_status',
                'customer_category_id',
                'customer_categories.category_name',
                'customer_categories.discount_percentage',
            ],

            defaultSort: '-created_at',
            withRelations: [  'customerCategory' => fn ($q) => $q->withTrashed(),],
            
        )
        ->when($request->filled('tag_ids'), function (Builder $query) use ($request) {
            $tagIds = array_filter((array) $request->query('tag_ids'), fn ($id) => is_numeric($id));
            $this->filterByTags($query, array_map('intval', $tagIds));
        })
        ->when($request->filled('status'), function (Builder $query) use ($request) {
            $status = CustomerStatusEnum::tryFrom(strtolower((string) $request->query('status')));

            if ($status) {
                $this->filterByStatus($query, $status);
            }
        })
        ->when($request->filled('category_id'), function (Builder $query) use ($request) {
            $this->filterByCategory($query, (int) $request->query('category_id'));
        })
        ->paginate($perPage)
        ->appends($request->query());
    }

    public function segmentedBuilder(Request $request)
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        $query = Customer::query()
            ->select([
                'customers.id',
                'customers.customer_code',
                'customers.fullname',
                'customers.phone_number',
                'customers.customer_status',
                'customers.customer_category_id',
                'customers.updated_at',
            ])
            ->with(['customerCategory:id,category_name']);

        if ($request->filled('tag_ids')) {
            $tagIds = array_filter((array) $request->query('tag_ids'), fn ($id) => is_numeric($id));
            $this->filterByTags($query, array_map('intval', $tagIds));
        }

        if ($request->filled('status')) {
            $status = CustomerStatusEnum::tryFrom(strtolower((string) $request->query('status')));
            if ($status) {
                $this->filterByStatus($query, $status);
            }
        }

        if ($request->filled('category_id')) {
            $this->filterByCategory($query, (int) $request->query('category_id'));
        }

        return $query
            ->orderByDesc('customers.updated_at')
            ->paginate($perPage)
            ->appends($request->query());
    }

}