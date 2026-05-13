<?php

namespace App\Service\KPI;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ProductKPI extends BaseKPI
{
    public function summary(array $filters): array
    {
        $payload = $this->basePayload();
        $period = $this->resolvePeriod($filters);
        $table = (new Product())->getTable();

        $baseQuery = Product::query();
        $this->applyExactFilterIfColumnExists($baseQuery, $table, $filters, 'supplier_id', 'supplier_id');
        $this->applyExactFilterIfColumnExists($baseQuery, $table, $filters, 'warehouse_id', 'warehouse_id');
        $this->applyStatusFilterIfColumnExists($baseQuery, $table, $filters);

        $currentTotal = (clone $baseQuery)->where($table . '.created_at', '<=', $period['current_end'])->count($table . '.id');
        $previousTotal = (clone $baseQuery)->where($table . '.created_at', '<=', $period['previous_end'])->count($table . '.id');
        $payload['metrics']['total_products'] = $this->buildTrendMetric($currentTotal, $previousTotal);

        $currentNewQuery = clone $baseQuery;
        $this->applyCurrentPeriod($currentNewQuery, $filters, $table . '.created_at');
        $currentNew = $currentNewQuery->count($table . '.id');

        $previousNewQuery = clone $baseQuery;
        $this->applyPreviousPeriod($previousNewQuery, $filters, $table . '.created_at');
        $previousNew = $previousNewQuery->count($table . '.id');

        $payload['metrics']['new_products_in_period'] = $this->buildTrendMetric($currentNew, $previousNew);

        if ($this->hasColumn($table, 'status') || $this->hasColumn($table, 'is_active')) {
            if ($this->hasColumn($table, 'is_active')) {
                $currentActive = (clone $baseQuery)
                    ->where($table . '.created_at', '<=', $period['current_end'])
                    ->where($table . '.is_active', true)
                    ->count($table . '.id');
                $previousActive = (clone $baseQuery)
                    ->where($table . '.created_at', '<=', $period['previous_end'])
                    ->where($table . '.is_active', true)
                    ->count($table . '.id');
            } else {
                $currentActive = (clone $baseQuery)
                    ->where($table . '.created_at', '<=', $period['current_end'])
                    ->where($table . '.status', 'active')
                    ->count($table . '.id');
                $previousActive = (clone $baseQuery)
                    ->where($table . '.created_at', '<=', $period['previous_end'])
                    ->where($table . '.status', 'active')
                    ->count($table . '.id');
            }

            $payload['metrics']['active_products'] = $this->buildTrendMetric($currentActive, $previousActive);
        } else {
            $this->addUnavailableMetric($payload, 'active_products', 'No product status/is_active column found.');
        }

        if ($this->hasTable('sale_order_items') && $this->hasTable('sale_orders')) {
            $currentTopRows = $this->buildSalesItemBaseQuery($filters, 'current')
                ->selectRaw(
                    'p.id as product_id, p.product_name, ' .
                    'SUM(soi.quantity) as total_quantity_sold, ' .
                    'SUM(soi.total_price_in_usd) as total_revenue'
                )
                ->groupBy('p.id', 'p.product_name')
                ->orderByDesc('total_quantity_sold')
                ->limit(10)
                ->get();

            $previousTopMap = $this->buildSalesItemBaseQuery($filters, 'previous')
                ->selectRaw('p.id as product_id, SUM(soi.quantity) as total_quantity_sold')
                ->groupBy('p.id')
                ->get()
                ->keyBy('product_id');

            $topProductIds = $currentTopRows->pluck('product_id')->all();

            $topCustomerAggRows = collect();
            if (!empty($topProductIds)) {
                $topCustomerAggRows = $this->buildSalesItemBaseQuery($filters, 'current')
                    ->whereIn('p.id', $topProductIds)
                    ->selectRaw(
                        'p.id as product_id, so.customer_id, c.fullname as customer_name, ' .
                        'SUM(soi.quantity) as quantity_purchased, ' .
                        'SUM(soi.total_price_in_usd) as revenue'
                    )
                    ->groupBy('p.id', 'so.customer_id', 'c.fullname')
                    ->orderBy('p.id')
                    ->orderByDesc('revenue')
                    ->get();
            }

            $topCustomerByProduct = [];
            foreach ($topCustomerAggRows as $row) {
                if (!array_key_exists((int) $row->product_id, $topCustomerByProduct)) {
                    $topCustomerByProduct[(int) $row->product_id] = $row;
                }
            }

            $payload['tables']['top_10_most_selling_products_with_customer'] = $currentTopRows->map(function ($row) use ($previousTopMap, $topCustomerByProduct) {
                $previousQuantity = (float) ($previousTopMap[$row->product_id]->total_quantity_sold ?? 0);
                $topCustomerRow = $topCustomerByProduct[(int) $row->product_id] ?? null;

                return [
                    'product_id' => (int) $row->product_id,
                    'product_name' => $row->product_name,
                    'total_quantity_sold' => $this->normalizeNumber((float) $row->total_quantity_sold),
                    'total_revenue' => $this->normalizeNumber((float) $row->total_revenue),
                    'top_customer' => $topCustomerRow ? [
                        'customer_id' => $topCustomerRow->customer_id !== null ? (int) $topCustomerRow->customer_id : null,
                        'customer_name' => $topCustomerRow->customer_name,
                        'quantity_purchased' => $this->normalizeNumber((float) $topCustomerRow->quantity_purchased),
                        'revenue' => $this->normalizeNumber((float) $topCustomerRow->revenue),
                    ] : null,
                    'trend' => $this->buildTrendMetric((float) $row->total_quantity_sold, $previousQuantity),
                ];
            })->all();

            $lastOrdersQuery = DB::table('sale_orders as so')
                ->leftJoin('customers as c', 'c.id', '=', 'so.customer_id')
                ->selectRaw(
                    'so.id as sale_order_id, so.order_no as sale_order_number, so.customer_id, c.fullname as customer_name, ' .
                    'so.order_status as status, so.grand_total_amount_in_usd as total_amount, so.order_date as ordered_at, so.created_at'
                )
                ->whereNull('so.deleted_at')
                ->orderByDesc('so.order_date')
                ->limit(10);

            $this->applyOrderHeaderFilters($lastOrdersQuery, $filters, 'current');
            $payload['tables']['last_10_sale_orders_with_customer'] = $lastOrdersQuery->get()->map(function ($row) {
                return [
                    'sale_order_id' => (int) $row->sale_order_id,
                    'sale_order_number' => $row->sale_order_number,
                    'customer_id' => $row->customer_id !== null ? (int) $row->customer_id : null,
                    'customer_name' => $row->customer_name,
                    'status' => $row->status,
                    'total_amount' => $this->normalizeNumber((float) $row->total_amount),
                    'ordered_at' => $row->ordered_at,
                    'created_at' => $row->created_at,
                ];
            })->all();

            $ordersSeries = $this->buildTimeSeries(
                query: DB::table('sale_orders as so')->whereNull('so.deleted_at'),
                dateColumn: 'so.order_date',
                aggregateType: 'count',
                filters: $filters,
                valueKey: 'orders_count',
            );

            $qtySeries = $this->buildTimeSeries(
                query: DB::table('sale_order_items as soi')
                    ->join('sale_orders as so', 'so.id', '=', 'soi.sale_order_id')
                    ->whereNull('so.deleted_at'),
                dateColumn: 'so.order_date',
                aggregateColumn: 'soi.quantity',
                aggregateType: 'sum',
                filters: $filters,
                valueKey: 'quantity_sold',
            );

            $revenueSeries = $this->buildTimeSeries(
                query: DB::table('sale_order_items as soi')
                    ->join('sale_orders as so', 'so.id', '=', 'soi.sale_order_id')
                    ->whereNull('so.deleted_at'),
                dateColumn: 'so.order_date',
                aggregateColumn: 'soi.total_price_in_usd',
                aggregateType: 'sum',
                filters: $filters,
                valueKey: 'revenue',
            );

            $seriesMap = [];
            foreach ($ordersSeries as $point) {
                $seriesMap[$point['date']] = [
                    'date' => $point['date'],
                    'orders_count' => $point['orders_count'],
                    'quantity_sold' => 0,
                    'revenue' => 0,
                ];
            }

            foreach ($qtySeries as $point) {
                if (isset($seriesMap[$point['date']])) {
                    $seriesMap[$point['date']]['quantity_sold'] = $point['quantity_sold'];
                }
            }

            foreach ($revenueSeries as $point) {
                if (isset($seriesMap[$point['date']])) {
                    $seriesMap[$point['date']]['revenue'] = $point['revenue'];
                }
            }

            $payload['charts']['sale_trend_overtime'] = array_values($seriesMap);
        } else {
            $this->addUnavailableMetric($payload, 'top_10_most_selling_products_with_customer', 'sale_order_items or sale_orders table was not found.');
            $this->addUnavailableMetric($payload, 'last_10_sale_orders_with_customer', 'sale_orders table was not found.');
            $this->addUnavailableMetric($payload, 'sale_trend_overtime', 'sale_order_items or sale_orders table was not found.');
        }

        return $payload;
    }

    protected function buildSalesItemBaseQuery(array $filters, string $periodType = 'current')
    {
        $query = DB::table('sale_order_items as soi')
            ->join('sale_orders as so', 'so.id', '=', 'soi.sale_order_id')
            ->join('products as p', 'p.id', '=', 'soi.product_id')
            ->leftJoin('customers as c', 'c.id', '=', 'so.customer_id')
            ->whereNull('so.deleted_at')
            ->whereNull('p.deleted_at');

        $this->applySaleOrderFilters($query, $filters, $periodType);

        return $query;
    }

    protected function applySaleOrderFilters($query, array $filters, string $periodType = 'current'): void
    {
        $period = $this->resolvePeriod($filters);
        $start = $periodType === 'previous' ? $period['previous_start'] : $period['current_start'];
        $end = $periodType === 'previous' ? $period['previous_end'] : $period['current_end'];

        $query->whereBetween('so.order_date', [$start, $end]);

        if (!empty($filters['customer_id'])) {
            $query->where('so.customer_id', $filters['customer_id']);
        }

        if (!empty($filters['status']) && $this->hasColumn('sale_orders', 'order_status')) {
            $query->where('so.order_status', $filters['status']);
        }

        if (!empty($filters['user_id']) && $this->hasColumn('sale_orders', 'created_by')) {
            $query->where('so.created_by', $filters['user_id']);
        }

        if (!empty($filters['warehouse_id']) && $this->hasColumn('products', 'warehouse_id')) {
            $query->where('p.warehouse_id', $filters['warehouse_id']);
        }

        if (!empty($filters['supplier_id']) && $this->hasColumn('products', 'supplier_id')) {
            $query->where('p.supplier_id', $filters['supplier_id']);
        }
    }

    protected function applyOrderHeaderFilters($query, array $filters, string $periodType = 'current'): void
    {
        $period = $this->resolvePeriod($filters);
        $start = $periodType === 'previous' ? $period['previous_start'] : $period['current_start'];
        $end = $periodType === 'previous' ? $period['previous_end'] : $period['current_end'];

        $query->whereBetween('so.order_date', [$start, $end]);

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
    }
}
