<?php

namespace App\Service\KPI;

use App\Enums\SaleOrderStatusEnum;
use App\Models\SaleOrder;
use Illuminate\Support\Facades\DB;

class SaleOrderKPI extends BaseKPI
{
    public function summary(array $filters): array
    {
        $payload = $this->basePayload();

        if (!$this->hasTable('sale_orders')) {
            $this->addUnavailableMetric($payload, 'sale_orders', 'sale_orders table was not found.');
            return $payload;
        }

        $statusMap = [
            'draft' => SaleOrderStatusEnum::DRAFT->value,
            'on_hold' => SaleOrderStatusEnum::ON_HOLD->value,
            'processing' => SaleOrderStatusEnum::PROCESSING->value,
            'completed' => SaleOrderStatusEnum::COMPLETED->value,
            'cancelled' => SaleOrderStatusEnum::CANCELLED->value,
            'refunded' => SaleOrderStatusEnum::REFUNDED->value,
        ];

        $currentOrdersQuery = $this->buildBaseOrderQuery($filters);
        $this->applyCurrentPeriod($currentOrdersQuery, $filters, 'so.order_date');
        $currentTotal = $currentOrdersQuery->count('so.id');

        $previousOrdersQuery = $this->buildBaseOrderQuery($filters);
        $this->applyPreviousPeriod($previousOrdersQuery, $filters, 'so.order_date');
        $previousTotal = $previousOrdersQuery->count('so.id');

        $payload['metrics']['total_sale_orders'] = $this->buildTrendMetric($currentTotal, $previousTotal);

        $currentRevenueQuery = $this->buildBaseOrderQuery($filters);
        $this->applyCurrentPeriod($currentRevenueQuery, $filters, 'so.order_date');
        $currentRevenue = (float) ($currentRevenueQuery->sum('so.grand_total_amount_in_usd') ?? 0);

        $previousRevenueQuery = $this->buildBaseOrderQuery($filters);
        $this->applyPreviousPeriod($previousRevenueQuery, $filters, 'so.order_date');
        $previousRevenue = (float) ($previousRevenueQuery->sum('so.grand_total_amount_in_usd') ?? 0);

        $payload['metrics']['total_revenue'] = $this->buildTrendMetric($currentRevenue, $previousRevenue);

        $currentRefundQuery = $this->buildBaseOrderQuery($filters);
        $this->applyCurrentPeriod($currentRefundQuery, $filters, 'so.order_date');
        $currentRefunded = (float) ($currentRefundQuery->sum('so.total_refunded_amount_in_usd') ?? 0);

        $previousRefundQuery = $this->buildBaseOrderQuery($filters);
        $this->applyPreviousPeriod($previousRefundQuery, $filters, 'so.order_date');
        $previousRefunded = (float) ($previousRefundQuery->sum('so.total_refunded_amount_in_usd') ?? 0);

        $payload['metrics']['total_refunded'] = $this->buildTrendMetric($currentRefunded, $previousRefunded);

        $currentDiscountQuery = $this->buildBaseOrderQuery($filters);
        $this->applyCurrentPeriod($currentDiscountQuery, $filters, 'so.order_date');
        $currentDiscount = (float) ($currentDiscountQuery->sum('so.discount_amount') ?? 0);

        $previousDiscountQuery = $this->buildBaseOrderQuery($filters);
        $this->applyPreviousPeriod($previousDiscountQuery, $filters, 'so.order_date');
        $previousDiscount = (float) ($previousDiscountQuery->sum('so.discount_amount') ?? 0);

        $payload['metrics']['total_discount'] = $this->buildTrendMetric($currentDiscount, $previousDiscount);

        $currentAov = $currentTotal > 0 ? $currentRevenue / $currentTotal : 0;
        $previousAov = $previousTotal > 0 ? $previousRevenue / $previousTotal : 0;
        $payload['metrics']['average_order_value'] = $this->buildTrendMetric($currentAov, $previousAov);

        $saleOrdersByStatus = [];
        $saleOrderCountByType = [];

        foreach ($statusMap as $label => $statusValue) {
            $currentStatusQuery = $this->buildBaseOrderQuery($filters)->where('so.order_status', $statusValue);
            $this->applyCurrentPeriod($currentStatusQuery, $filters, 'so.order_date');
            $currentStatusCount = $currentStatusQuery->count('so.id');

            $previousStatusQuery = $this->buildBaseOrderQuery($filters)->where('so.order_status', $statusValue);
            $this->applyPreviousPeriod($previousStatusQuery, $filters, 'so.order_date');
            $previousStatusCount = $previousStatusQuery->count('so.id');

            $trend = $this->buildTrendMetric($currentStatusCount, $previousStatusCount);

            $saleOrdersByStatus[] = array_merge(['status' => $label], $trend);
            $saleOrderCountByType[$label] = $trend;
        }

        $payload['metrics']['sale_orders_by_status'] = $saleOrdersByStatus;
        $payload['metrics']['sale_order_count_by_type'] = $saleOrderCountByType;

        $orderCountSeries = $this->buildTimeSeries(
            query: $this->buildBaseOrderQuery($filters),
            dateColumn: 'so.order_date',
            aggregateType: 'count',
            filters: $filters,
            valueKey: 'orders_count',
        );

        $revenueSeries = $this->buildTimeSeries(
            query: $this->buildBaseOrderQuery($filters),
            dateColumn: 'so.order_date',
            aggregateColumn: 'so.grand_total_amount_in_usd',
            aggregateType: 'sum',
            filters: $filters,
            valueKey: 'revenue',
        );

        $discountSeries = $this->buildTimeSeries(
            query: $this->buildBaseOrderQuery($filters),
            dateColumn: 'so.order_date',
            aggregateColumn: 'so.discount_amount',
            aggregateType: 'sum',
            filters: $filters,
            valueKey: 'discount',
        );

        $refundedSeries = $this->buildTimeSeries(
            query: $this->buildBaseOrderQuery($filters),
            dateColumn: 'so.order_date',
            aggregateColumn: 'so.total_refunded_amount_in_usd',
            aggregateType: 'sum',
            filters: $filters,
            valueKey: 'refunded',
        );

        $merged = [];
        foreach ($revenueSeries as $row) {
            $merged[$row['date']] = [
                'date' => $row['date'],
                'revenue' => $this->normalizeNumber((float) ($row['revenue'] ?? 0)),
                'discount' => 0,
                'refunded' => 0,
                'net_revenue' => 0,
            ];
        }

        foreach ($discountSeries as $row) {
            if (isset($merged[$row['date']])) {
                $merged[$row['date']]['discount'] = $this->normalizeNumber((float) ($row['discount'] ?? 0));
            }
        }

        foreach ($refundedSeries as $row) {
            if (isset($merged[$row['date']])) {
                $merged[$row['date']]['refunded'] = $this->normalizeNumber((float) ($row['refunded'] ?? 0));
            }
        }

        foreach ($merged as &$row) {
            $row['net_revenue'] = $this->normalizeNumber(
                (float) $row['revenue'] - (float) $row['discount'] - (float) $row['refunded']
            );
        }
        unset($row);

        $payload['charts']['revenue_trend_overtime'] = array_values($merged);
        $payload['charts']['order_count_trend_overtime'] = $orderCountSeries;

        return $payload;
    }

    protected function buildBaseOrderQuery(array $filters)
    {
        $query = DB::table('sale_orders as so')
            ->whereNull('so.deleted_at');

        if (!empty($filters['customer_id'])) {
            $query->where('so.customer_id', $filters['customer_id']);
        }

        if (!empty($filters['status']) && $this->hasColumn('sale_orders', 'order_status')) {
            $query->where('so.order_status', $filters['status']);
        }

        if (!empty($filters['user_id']) && $this->hasColumn('sale_orders', 'created_by')) {
            $query->where('so.created_by', $filters['user_id']);
        }

        if (!empty($filters['warehouse_id']) || !empty($filters['supplier_id'])) {
            if (
                $this->hasTable('sale_order_items') &&
                $this->hasTable('products') &&
                $this->hasColumn('sale_order_items', 'sale_order_id') &&
                $this->hasColumn('sale_order_items', 'product_id')
            ) {
                $query->whereExists(function ($subQuery) use ($filters) {
                    $subQuery->select(DB::raw(1))
                        ->from('sale_order_items as soi')
                        ->join('products as p', 'p.id', '=', 'soi.product_id')
                        ->whereNull('p.deleted_at')
                        ->whereColumn('soi.sale_order_id', 'so.id');

                    if (!empty($filters['warehouse_id']) && $this->hasColumn('products', 'warehouse_id')) {
                        $subQuery->where('p.warehouse_id', $filters['warehouse_id']);
                    }

                    if (!empty($filters['supplier_id']) && $this->hasColumn('products', 'supplier_id')) {
                        $subQuery->where('p.supplier_id', $filters['supplier_id']);
                    }
                });
            }
        }

        return $query;
    }
}
