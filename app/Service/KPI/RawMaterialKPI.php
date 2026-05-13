<?php

namespace App\Service\KPI;

use App\Models\RawMaterial;
use Illuminate\Support\Facades\DB;

class RawMaterialKPI extends BaseKPI
{
    public function summary(array $filters): array
    {
        $payload = $this->basePayload();
        $period = $this->resolvePeriod($filters);
        $table = (new RawMaterial())->getTable();

        $baseQuery = RawMaterial::query();
        $this->applyExactFilterIfColumnExists($baseQuery, $table, $filters, 'supplier_id', 'supplier_id');
        $this->applyExactFilterIfColumnExists($baseQuery, $table, $filters, 'warehouse_id', 'warehouse_id');
        $this->applyStatusFilterIfColumnExists($baseQuery, $table, $filters);

        $currentTotal = (clone $baseQuery)->where($table . '.created_at', '<=', $period['current_end'])->count($table . '.id');
        $previousTotal = (clone $baseQuery)->where($table . '.created_at', '<=', $period['previous_end'])->count($table . '.id');
        $payload['metrics']['total_raw_materials'] = $this->buildTrendMetric($currentTotal, $previousTotal);

        if ($this->hasTable('rm_stock_movements')) {
            $currentSnapshot = $this->buildStockSnapshotSubQuery($period['current_end'], $filters);
            $previousSnapshot = $this->buildStockSnapshotSubQuery($period['previous_end'], $filters);

            $currentOut = DB::table($table)
                ->leftJoinSub($currentSnapshot, 'snapshot', fn ($join) => $join->on('snapshot.raw_material_id', '=', $table . '.id'))
                ->whereNull($table . '.deleted_at')
                ->whereRaw('COALESCE(snapshot.current_stock, 0) <= 0')
                ->count($table . '.id');

            $previousOut = DB::table($table)
                ->leftJoinSub($previousSnapshot, 'snapshot', fn ($join) => $join->on('snapshot.raw_material_id', '=', $table . '.id'))
                ->whereNull($table . '.deleted_at')
                ->whereRaw('COALESCE(snapshot.current_stock, 0) <= 0')
                ->count($table . '.id');

            $payload['metrics']['out_of_stock_raw_materials'] = $this->buildTrendMetric($currentOut, $previousOut);

            if ($this->hasColumn($table, 'minimum_stock_level')) {
                $currentLow = DB::table($table)
                    ->leftJoinSub($currentSnapshot, 'snapshot', fn ($join) => $join->on('snapshot.raw_material_id', '=', $table . '.id'))
                    ->whereNull($table . '.deleted_at')
                    ->whereRaw('COALESCE(snapshot.current_stock, 0) > 0')
                    ->whereRaw('COALESCE(snapshot.current_stock, 0) <= ' . $table . '.minimum_stock_level')
                    ->count($table . '.id');

                $previousLow = DB::table($table)
                    ->leftJoinSub($previousSnapshot, 'snapshot', fn ($join) => $join->on('snapshot.raw_material_id', '=', $table . '.id'))
                    ->whereNull($table . '.deleted_at')
                    ->whereRaw('COALESCE(snapshot.current_stock, 0) > 0')
                    ->whereRaw('COALESCE(snapshot.current_stock, 0) <= ' . $table . '.minimum_stock_level')
                    ->count($table . '.id');

                $payload['metrics']['low_stock_raw_materials'] = $this->buildTrendMetric($currentLow, $previousLow);
            } else {
                $this->addUnavailableMetric($payload, 'low_stock_raw_materials', 'minimum_stock_level column was not found.');
            }
        } else {
            $this->addUnavailableMetric($payload, 'out_of_stock_raw_materials', 'rm_stock_movements table was not found.');
            $this->addUnavailableMetric($payload, 'low_stock_raw_materials', 'rm_stock_movements table was not found.');
        }

        if ($this->hasTable('reorder_product_raw_materials') && $this->hasTable('product_reorders')) {
            $currentUsageQuery = DB::table('reorder_product_raw_materials as rprm')
                ->join('raw_materials as rm', 'rm.id', '=', 'rprm.raw_material_id')
                ->leftJoin('unit_of_measurements as uom', 'uom.id', '=', 'rm.base_uom_id')
                ->join('product_reorders as pr', 'pr.id', '=', 'rprm.product_reorder_id')
                ->whereNull('rm.deleted_at')
                ->whereBetween('pr.created_at', [$period['current_start'], $period['current_end']]);

            if (!empty($filters['supplier_id']) && $this->hasColumn('raw_materials', 'supplier_id')) {
                $currentUsageQuery->where('rm.supplier_id', $filters['supplier_id']);
            }

            if (!empty($filters['warehouse_id']) && $this->hasColumn('raw_materials', 'warehouse_id')) {
                $currentUsageQuery->where('rm.warehouse_id', $filters['warehouse_id']);
            }

            $currentUsageRows = $currentUsageQuery
                ->selectRaw(
                    'rm.id as raw_material_id, rm.material_name as raw_material_name, ' .
                    'COALESCE(uom.name, NULL) as uom_name, ' .
                    'SUM(rprm.quantity) as total_used_quantity, ' .
                    'COUNT(DISTINCT pr.id) as production_count'
                )
                ->groupBy('rm.id', 'rm.material_name', 'uom.name')
                ->orderByDesc('total_used_quantity')
                ->limit(10)
                ->get();

            $previousUsageRows = DB::table('reorder_product_raw_materials as rprm')
                ->join('product_reorders as pr', 'pr.id', '=', 'rprm.product_reorder_id')
                ->whereBetween('pr.created_at', [$period['previous_start'], $period['previous_end']])
                ->selectRaw('rprm.raw_material_id, SUM(rprm.quantity) as total_used_quantity')
                ->groupBy('rprm.raw_material_id')
                ->get()
                ->keyBy('raw_material_id');

            $payload['tables']['top_10_most_used_raw_materials_in_production'] = $currentUsageRows->map(function ($row) use ($previousUsageRows) {
                $previous = (float) ($previousUsageRows[$row->raw_material_id]->total_used_quantity ?? 0);

                return [
                    'raw_material_id' => (int) $row->raw_material_id,
                    'raw_material_name' => $row->raw_material_name,
                    'total_used_quantity' => $this->normalizeNumber((float) $row->total_used_quantity),
                    'uom_name' => $row->uom_name,
                    'production_count' => (int) $row->production_count,
                    'trend' => $this->buildTrendMetric((float) $row->total_used_quantity, $previous),
                ];
            })->all();
        } else {
            $this->addUnavailableMetric(
                $payload,
                'top_10_most_used_raw_materials_in_production',
                'reorder_product_raw_materials or product_reorders table was not found.'
            );
        }

        if ($this->hasTable('rm_stock_movements')) {
            $currentExpensiveRows = DB::table('rm_stock_movements as rsm')
                ->join('raw_materials as rm', 'rm.id', '=', 'rsm.raw_material_id')
                ->leftJoin('suppliers as s', 's.id', '=', 'rm.supplier_id')
                ->whereNull('rm.deleted_at')
                ->whereBetween('rsm.movement_date', [$period['current_start'], $period['current_end']])
                ->selectRaw(
                    'rm.id as raw_material_id, rm.material_name as raw_material_name, ' .
                    'AVG(rsm.unit_price_in_usd) as avg_unit_price, ' .
                    'MAX(rsm.unit_price_in_usd) as max_unit_price, ' .
                    's.official_name as supplier_name'
                )
                ->groupBy('rm.id', 'rm.material_name', 's.official_name')
                ->orderByDesc('avg_unit_price')
                ->limit(10)
                ->get();

            $previousPriceMap = DB::table('rm_stock_movements as rsm')
                ->whereBetween('rsm.movement_date', [$period['previous_start'], $period['previous_end']])
                ->selectRaw('rsm.raw_material_id, AVG(rsm.unit_price_in_usd) as avg_unit_price')
                ->groupBy('rsm.raw_material_id')
                ->get()
                ->keyBy('raw_material_id');

            $payload['tables']['top_10_expensive_raw_materials'] = $currentExpensiveRows->map(function ($row) use ($previousPriceMap) {
                $previous = (float) ($previousPriceMap[$row->raw_material_id]->avg_unit_price ?? 0);

                return [
                    'raw_material_id' => (int) $row->raw_material_id,
                    'raw_material_name' => $row->raw_material_name,
                    'unit_price' => $this->normalizeNumber((float) $row->avg_unit_price),
                    'currency' => 'USD',
                    'supplier_name' => $row->supplier_name,
                    'trend' => $this->buildTrendMetric((float) $row->avg_unit_price, $previous),
                ];
            })->all();
        } else {
            $this->addUnavailableMetric($payload, 'top_10_expensive_raw_materials', 'rm_stock_movements table was not found.');
        }

        if ($this->hasTable('rm_stock_movements')) {
            $payload['charts']['raw_material_usage_trend_overtime'] = $this->buildTimeSeries(
                query: DB::table('rm_stock_movements')->where('direction', 'OUT'),
                dateColumn: 'movement_date',
                aggregateColumn: 'quantity',
                aggregateType: 'sum',
                filters: $filters,
                valueKey: 'used_quantity',
            );
        }

        return $payload;
    }

    protected function buildStockSnapshotSubQuery($endDate, array $filters)
    {
        $subQuery = DB::table('rm_stock_movements as rsm')
            ->join('raw_materials as rm', 'rm.id', '=', 'rsm.raw_material_id')
            ->whereNull('rm.deleted_at')
            ->where('rsm.movement_date', '<=', $endDate)
            ->selectRaw(
                "rsm.raw_material_id, SUM(CASE WHEN rsm.direction = 'IN' THEN rsm.quantity ELSE -rsm.quantity END) AS current_stock"
            )
            ->groupBy('rsm.raw_material_id');

        if (!empty($filters['supplier_id']) && $this->hasColumn('raw_materials', 'supplier_id')) {
            $subQuery->where('rm.supplier_id', $filters['supplier_id']);
        }

        if (!empty($filters['warehouse_id']) && $this->hasColumn('raw_materials', 'warehouse_id')) {
            $subQuery->where('rm.warehouse_id', $filters['warehouse_id']);
        }

        return $subQuery;
    }
}
