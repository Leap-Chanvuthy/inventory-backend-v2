<?php

namespace App\QueryBuilders;

use App\Helpers\QueryBuilderHelper;
use App\Models\SaleOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;

class SaleOrderQueryBuilder
{
    public function saleOrderBuilder(Request $request, bool $onlyTrashed = false)
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        $builder = QueryBuilderHelper::build(
            model: SaleOrder::class,
            joins: [
                ['customers', 'sale_orders.customer_id', '=', 'customers.id'],
            ],
            selects: [
                'sale_orders.*',
                'customers.fullname as customer_name',
                'customers.phone_number as customer_phone',
            ],
            allowedFilters: [
                AllowedFilter::exact('id'),
                AllowedFilter::exact('customer_id'),
                AllowedFilter::exact('order_status'),
                AllowedFilter::exact('payment_status'),
                AllowedFilter::callback('search', function (Builder $query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('sale_orders.order_no', 'LIKE', "%{$value}%")
                            -> orWhere('sale_orders.order_status', 'LIKE', "%{$value}%")
                            ->orWhere('customers.fullname', 'LIKE', "%{$value}%")
                            ->orWhere('customers.phone_number', 'LIKE', "%{$value}%");
                    });
                }),
            ],
            allowedSorts: [
                'id',
                'order_no',
                'order_date',
                'order_status',
                'payment_status',
                'grand_total_amount_in_usd',
                'created_at',
                'updated_at',
            ],
            defaultSort: '-created_at',
            withRelations: [
                'customer' => fn ($q) => $q->withTrashed(),
            ],
            withCounts: ['orderItems']
        );

        if ($onlyTrashed) {
            $builder = $builder->onlyTrashed();
        }

        return $builder->paginate($perPage)
            ->appends($request->query());
    }
}
