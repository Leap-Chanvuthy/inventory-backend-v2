<?php

namespace App\QueryBuilders;

use App\Helpers\QueryBuilderHelper;
use App\Models\Customer;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Illuminate\Database\Eloquent\Builder;

class CustomerQueryBuilder {

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
            ],

            defaultSort: '-created_at',
            withRelations: [  'customerCategory' => fn ($q) => $q->withTrashed(),],
            
        )
        ->paginate($perPage)
        ->appends($request->query());
    }

}