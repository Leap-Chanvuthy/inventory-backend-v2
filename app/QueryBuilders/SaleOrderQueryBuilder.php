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
                AllowedFilter::callback('order_status', function (Builder $query, $value) {
                    $normalized = strtoupper((string) $value);

                    if ($normalized === 'REFUNDED') {
                        $query->where(function (Builder $q) {
                            $q->where('sale_orders.order_status', 'REFUNDED')
                                ->orWhereExists(function ($subQuery) {
                                    $subQuery->selectRaw('1')
                                        ->from('sale_order_refunds')
                                        ->whereColumn('sale_order_refunds.sale_order_id', 'sale_orders.id');
                                });
                        });

                        return;
                    }

                    if ($normalized === 'COMPLETED') {
                        $query->where('sale_orders.order_status', 'COMPLETED')
                            ->whereNotExists(function ($subQuery) {
                                $subQuery->selectRaw('1')
                                    ->from('sale_order_refunds')
                                    ->whereColumn('sale_order_refunds.sale_order_id', 'sale_orders.id');
                            });

                        return;
                    }

                    $query->where('sale_orders.order_status', $normalized);
                }),
                AllowedFilter::exact('payment_status'),
                AllowedFilter::callback('date_from', function (Builder $query, $value) {
                    if (!empty($value)) {
                        $query->whereDate('sale_orders.created_at', '>=', $value);
                    }
                }),
                AllowedFilter::callback('date_to', function (Builder $query, $value) {
                    if (!empty($value)) {
                        $query->whereDate('sale_orders.created_at', '<=', $value);
                    }
                }),
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
                'customer_name',
                'created_at',
                'updated_at',
            ],
            defaultSort: '-created_at',
            withRelations: [
                'customer' => fn ($q) => $q->withTrashed()->with('customerCategory'),
                'orderItems.product' => fn ($q) => $q->withTrashed(),
                'refunds' => fn ($q) => $q->orderByDesc('processed_at'),
            ],
            withCounts: ['orderItems', 'refunds']
        );

        if ($onlyTrashed) {
            $builder = $builder->onlyTrashed();
        }

        return $builder->paginate($perPage)
            ->appends($request->query());
    }
}
