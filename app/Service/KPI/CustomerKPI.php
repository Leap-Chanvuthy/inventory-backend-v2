<?php

namespace App\Service\KPI;

use App\Enums\CustomerStatusEnum;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class CustomerKPI extends BaseKPI
{
    public function summary(array $filters): array
    {
        $payload = $this->basePayload();
        $period = $this->resolvePeriod($filters);
        $table = (new Customer())->getTable();

        $baseQuery = Customer::query();
        $this->applyExactFilterIfColumnExists($baseQuery, $table, $filters, 'customer_id', 'id');
        $this->applyStatusFilterIfColumnExists($baseQuery, $table, $filters, ['customer_status', 'status', 'is_active']);

        $currentTotal = (clone $baseQuery)->where($table . '.created_at', '<=', $period['current_end'])->count($table . '.id');
        $previousTotal = (clone $baseQuery)->where($table . '.created_at', '<=', $period['previous_end'])->count($table . '.id');
        $payload['metrics']['total_customers'] = $this->buildTrendMetric($currentTotal, $previousTotal);

        if ($this->hasColumn($table, 'customer_status')) {
            $currentActive = (clone $baseQuery)
                ->where($table . '.created_at', '<=', $period['current_end'])
                ->where($table . '.customer_status', CustomerStatusEnum::ACTIVE->value)
                ->count($table . '.id');
            $previousActive = (clone $baseQuery)
                ->where($table . '.created_at', '<=', $period['previous_end'])
                ->where($table . '.customer_status', CustomerStatusEnum::ACTIVE->value)
                ->count($table . '.id');
            $payload['metrics']['active_customers'] = $this->buildTrendMetric($currentActive, $previousActive);
        } elseif ($this->hasColumn($table, 'is_active') || $this->hasColumn($table, 'status')) {
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

            $payload['metrics']['active_customers'] = $this->buildTrendMetric($currentActive, $previousActive);
        } else {
            $this->addUnavailableMetric($payload, 'active_customers', 'No customer status/is_active column found.');
        }

        if ($this->hasTable('sale_orders') && $this->hasTable('sale_order_items')) {
            $currentRows = $this->buildCustomerSalesAggregation($filters, 'current')
                ->orderByDesc('total_quantity_purchased')
                ->limit(10)
                ->get();

            $previousRows = $this->buildCustomerSalesAggregation($filters, 'previous')
                ->get()
                ->keyBy('customer_id');

            $payload['tables']['top_10_customers_by_most_purchasing'] = $currentRows->map(function ($row) use ($previousRows) {
                $previousRevenue = (float) ($previousRows[$row->customer_id]->total_revenue ?? 0);

                return [
                    'customer_id' => (int) $row->customer_id,
                    'customer_name' => $row->customer_name,
                    'orders_count' => (int) $row->orders_count,
                    'total_quantity_purchased' => $this->normalizeNumber((float) $row->total_quantity_purchased),
                    'total_revenue' => $this->normalizeNumber((float) $row->total_revenue),
                    'trend' => $this->buildTrendMetric((float) $row->total_revenue, $previousRevenue),
                ];
            })->all();

            $currentRevenueRows = $this->buildCustomerSalesAggregation($filters, 'current')
                ->orderByDesc('total_revenue')
                ->limit(10)
                ->get();

            $payload['tables']['top_10_customers_by_revenue'] = $currentRevenueRows->map(function ($row) use ($previousRows) {
                $previousRevenue = (float) ($previousRows[$row->customer_id]->total_revenue ?? 0);

                return [
                    'customer_id' => (int) $row->customer_id,
                    'customer_name' => $row->customer_name,
                    'orders_count' => (int) $row->orders_count,
                    'total_quantity_purchased' => $this->normalizeNumber((float) $row->total_quantity_purchased),
                    'total_revenue' => $this->normalizeNumber((float) $row->total_revenue),
                    'trend' => $this->buildTrendMetric((float) $row->total_revenue, $previousRevenue),
                ];
            })->all();
        } else {
            $this->addUnavailableMetric($payload, 'top_10_customers_by_most_purchasing', 'sale_orders or sale_order_items table was not found.');
            $this->addUnavailableMetric($payload, 'top_10_customers_by_revenue', 'sale_orders or sale_order_items table was not found.');
        }

        $payload['charts']['customers_trend_overtime'] = $this->buildTimeSeries(
            query: Customer::query(),
            dateColumn: $table . '.created_at',
            aggregateType: 'count',
            filters: $filters,
            valueKey: 'customers_count',
        );

        return $payload;
    }

    protected function buildCustomerSalesAggregation(array $filters, string $periodType)
    {
        $period = $this->resolvePeriod($filters);
        $start = $periodType === 'previous' ? $period['previous_start'] : $period['current_start'];
        $end = $periodType === 'previous' ? $period['previous_end'] : $period['current_end'];

        $query = DB::table('sale_orders as so')
            ->join('sale_order_items as soi', 'soi.sale_order_id', '=', 'so.id')
            ->leftJoin('customers as c', 'c.id', '=', 'so.customer_id')
            ->whereNull('so.deleted_at')
            ->whereBetween('so.order_date', [$start, $end])
            ->selectRaw(
                'so.customer_id as customer_id, c.fullname as customer_name, ' .
                'COUNT(DISTINCT so.id) as orders_count, ' .
                'SUM(soi.quantity) as total_quantity_purchased, ' .
                'SUM(soi.total_price_in_usd) as total_revenue'
            )
            ->groupBy('so.customer_id', 'c.fullname');

        if (!empty($filters['customer_id'])) {
            $query->where('so.customer_id', $filters['customer_id']);
        }

        if (!empty($filters['status']) && $this->hasColumn('sale_orders', 'order_status')) {
            $query->where('so.order_status', $filters['status']);
        }

        if (!empty($filters['user_id']) && $this->hasColumn('sale_orders', 'created_by')) {
            $query->where('so.created_by', $filters['user_id']);
        }

        return $query;
    }
}
