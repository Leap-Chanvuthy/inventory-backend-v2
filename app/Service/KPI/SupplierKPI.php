<?php

namespace App\Service\KPI;

use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

class SupplierKPI extends BaseKPI
{
    public function summary(array $filters): array
    {
        $payload = $this->basePayload();
        $period = $this->resolvePeriod($filters);

        $table = (new Supplier())->getTable();

        $baseQuery = Supplier::query();
        $this->applyExactFilterIfColumnExists($baseQuery, $table, $filters, 'supplier_id', 'id');
        $this->applyStatusFilterIfColumnExists($baseQuery, $table, $filters);

        $currentTotal = (clone $baseQuery)->where($table . '.created_at', '<=', $period['current_end'])->count($table . '.id');
        $previousTotal = (clone $baseQuery)->where($table . '.created_at', '<=', $period['previous_end'])->count($table . '.id');

        $payload['metrics']['total_suppliers'] = $this->buildTrendMetric($currentTotal, $previousTotal);

        if ($this->hasColumn($table, 'is_active') || $this->hasColumn($table, 'status')) {
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

            $payload['metrics']['active_suppliers'] = $this->buildTrendMetric($currentActive, $previousActive);
        } else {
            $this->addUnavailableMetric($payload, 'active_suppliers', 'No supplier status/is_active column found.');
        }

        if ($this->hasTable('raw_materials') && $this->hasTable('products')) {
            $rawCurrent = DB::table('raw_materials')
                ->selectRaw('supplier_id, COUNT(*) as raw_count')
                ->whereNull('deleted_at')
                ->where('created_at', '<=', $period['current_end'])
                ->groupBy('supplier_id');

            $productCurrent = DB::table('products')
                ->selectRaw('supplier_id, COUNT(*) as product_count')
                ->whereNull('deleted_at')
                ->where('created_at', '<=', $period['current_end'])
                ->groupBy('supplier_id');

            $rawPrevious = DB::table('raw_materials')
                ->selectRaw('supplier_id, COUNT(*) as raw_count')
                ->whereNull('deleted_at')
                ->where('created_at', '<=', $period['previous_end'])
                ->groupBy('supplier_id');

            $productPrevious = DB::table('products')
                ->selectRaw('supplier_id, COUNT(*) as product_count')
                ->whereNull('deleted_at')
                ->where('created_at', '<=', $period['previous_end'])
                ->groupBy('supplier_id');

            $rows = DB::table($table)
                ->leftJoinSub($rawCurrent, 'raw_current', fn ($join) => $join->on('raw_current.supplier_id', '=', $table . '.id'))
                ->leftJoinSub($productCurrent, 'product_current', fn ($join) => $join->on('product_current.supplier_id', '=', $table . '.id'))
                ->leftJoinSub($rawPrevious, 'raw_previous', fn ($join) => $join->on('raw_previous.supplier_id', '=', $table . '.id'))
                ->leftJoinSub($productPrevious, 'product_previous', fn ($join) => $join->on('product_previous.supplier_id', '=', $table . '.id'))
                ->whereNull($table . '.deleted_at')
                ->selectRaw(
                    $table . '.id as supplier_id, ' .
                    $table . '.official_name as supplier_name, ' .
                    'COALESCE(raw_current.raw_count, 0) as raw_materials_count, ' .
                    'COALESCE(product_current.product_count, 0) as products_count, ' .
                    '(COALESCE(raw_current.raw_count, 0) + COALESCE(product_current.product_count, 0)) as current_supplied_items_count, ' .
                    '(COALESCE(raw_previous.raw_count, 0) + COALESCE(product_previous.product_count, 0)) as previous_supplied_items_count'
                )
                ->orderByDesc('current_supplied_items_count')
                ->limit(10)
                ->get();

            $payload['tables']['top_10_suppliers_by_supplied_items'] = $rows->map(function ($row) {
                return [
                    'supplier_id' => (int) $row->supplier_id,
                    'supplier_name' => $row->supplier_name,
                    'supplied_items_count' => (int) $row->current_supplied_items_count,
                    'raw_materials_count' => (int) $row->raw_materials_count,
                    'products_count' => (int) $row->products_count,
                    'trend' => $this->buildTrendMetric(
                        (int) $row->current_supplied_items_count,
                        (int) $row->previous_supplied_items_count
                    ),
                ];
            })->all();
        } else {
            $this->addUnavailableMetric(
                $payload,
                'top_10_suppliers_by_supplied_items',
                'Supplier supplied item tables were not found.'
            );
        }

        $this->addUnavailableMetric(
            $payload,
            'top_10_suppliers_by_revenue',
            'Purchase order or supplier invoice table was not found.'
        );

        $payload['charts']['suppliers_trend_overtime'] = $this->buildTimeSeries(
            query: Supplier::query(),
            dateColumn: $table . '.created_at',
            aggregateType: 'count',
            filters: $filters,
            valueKey: 'suppliers_count',
        );

        return $payload;
    }
}
