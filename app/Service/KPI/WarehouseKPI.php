<?php

namespace App\Service\KPI;

use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class WarehouseKPI extends BaseKPI
{
    public function summary(array $filters): array
    {
        $payload = $this->basePayload();
        $period = $this->resolvePeriod($filters);
        $table = (new Warehouse())->getTable();

        $baseQuery = Warehouse::query();
        $this->applyExactFilterIfColumnExists($baseQuery, $table, $filters, 'warehouse_id', 'id');
        $this->applyStatusFilterIfColumnExists($baseQuery, $table, $filters);

        $currentTotal = (clone $baseQuery)->where($table . '.created_at', '<=', $period['current_end'])->count($table . '.id');
        $previousTotal = (clone $baseQuery)->where($table . '.created_at', '<=', $period['previous_end'])->count($table . '.id');
        $payload['metrics']['total_warehouses'] = $this->buildTrendMetric($currentTotal, $previousTotal);

        $currentNewQuery = clone $baseQuery;
        $this->applyCurrentPeriod($currentNewQuery, $filters, $table . '.created_at');
        $currentNew = $currentNewQuery->count($table . '.id');

        $previousNewQuery = clone $baseQuery;
        $this->applyPreviousPeriod($previousNewQuery, $filters, $table . '.created_at');
        $previousNew = $previousNewQuery->count($table . '.id');

        $payload['metrics']['new_warehouses_in_period'] = $this->buildTrendMetric($currentNew, $previousNew);

        if ($this->hasColumn($table, 'is_active') || $this->hasColumn($table, 'status')) {
            if ($this->hasColumn($table, 'is_active')) {
                $currentActive = (clone $baseQuery)->where($table . '.created_at', '<=', $period['current_end'])->where($table . '.is_active', true)->count($table . '.id');
                $previousActive = (clone $baseQuery)->where($table . '.created_at', '<=', $period['previous_end'])->where($table . '.is_active', true)->count($table . '.id');
            } else {
                $currentActive = (clone $baseQuery)->where($table . '.created_at', '<=', $period['current_end'])->where($table . '.status', 'active')->count($table . '.id');
                $previousActive = (clone $baseQuery)->where($table . '.created_at', '<=', $period['previous_end'])->where($table . '.status', 'active')->count($table . '.id');
            }

            $payload['metrics']['active_warehouses'] = $this->buildTrendMetric($currentActive, $previousActive);
        } else {
            $this->addUnavailableMetric($payload, 'active_warehouses', 'No warehouse status/is_active column found.');
        }

        if ($this->hasTable('raw_materials') || $this->hasTable('products')) {
            $currentWithStock = $this->countWarehousesWithStockByEndDate($period['current_end'], $filters);
            $previousWithStock = $this->countWarehousesWithStockByEndDate($period['previous_end'], $filters);

            $payload['metrics']['warehouses_with_stock'] = $this->buildTrendMetric($currentWithStock, $previousWithStock);
        } else {
            $this->addUnavailableMetric($payload, 'warehouses_with_stock', 'raw_materials and products tables were not found.');
        }

        $payload['charts']['warehouses_trend_overtime'] = $this->buildTimeSeries(
            query: Warehouse::query(),
            dateColumn: $table . '.created_at',
            aggregateType: 'count',
            filters: $filters,
            valueKey: 'warehouses_count',
        );

        return $payload;
    }

    protected function countWarehousesWithStockByEndDate($endDate, array $filters): int
    {
        $query = DB::table('warehouses')
            ->whereNull('warehouses.deleted_at');

        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouses.id', $filters['warehouse_id']);
        }

        $query->where(function ($whereQuery) use ($endDate, $filters) {
            if ($this->hasTable('raw_materials') && $this->hasColumn('raw_materials', 'warehouse_id')) {
                $whereQuery->whereExists(function ($subQuery) use ($endDate, $filters) {
                    $subQuery->select(DB::raw(1))
                        ->from('raw_materials')
                        ->whereColumn('raw_materials.warehouse_id', 'warehouses.id')
                        ->whereNull('raw_materials.deleted_at')
                        ->where('raw_materials.created_at', '<=', $endDate);

                    if (!empty($filters['supplier_id']) && $this->hasColumn('raw_materials', 'supplier_id')) {
                        $subQuery->where('raw_materials.supplier_id', $filters['supplier_id']);
                    }
                });
            }

            if ($this->hasTable('products') && $this->hasColumn('products', 'warehouse_id')) {
                $method = ($this->hasTable('raw_materials') && $this->hasColumn('raw_materials', 'warehouse_id')) ? 'orWhereExists' : 'whereExists';
                $whereQuery->{$method}(function ($subQuery) use ($endDate, $filters) {
                    $subQuery->select(DB::raw(1))
                        ->from('products')
                        ->whereColumn('products.warehouse_id', 'warehouses.id')
                        ->whereNull('products.deleted_at')
                        ->where('products.created_at', '<=', $endDate);

                    if (!empty($filters['supplier_id']) && $this->hasColumn('products', 'supplier_id')) {
                        $subQuery->where('products.supplier_id', $filters['supplier_id']);
                    }
                });
            }
        });

        return (int) $query->count('warehouses.id');
    }
}
