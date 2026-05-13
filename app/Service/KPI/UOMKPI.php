<?php

namespace App\Service\KPI;

use App\Models\UnitOfMeasurement;
use Illuminate\Support\Facades\DB;

class UOMKPI extends BaseKPI
{
    public function summary(array $filters): array
    {
        $payload = $this->basePayload();
        $period = $this->resolvePeriod($filters);
        $table = (new UnitOfMeasurement())->getTable();

        $baseQuery = UnitOfMeasurement::query();
        $this->applyStatusFilterIfColumnExists($baseQuery, $table, $filters, ['is_active', 'status']);

        $currentTotal = (clone $baseQuery)->where($table . '.created_at', '<=', $period['current_end'])->count($table . '.id');
        $previousTotal = (clone $baseQuery)->where($table . '.created_at', '<=', $period['previous_end'])->count($table . '.id');
        $payload['metrics']['total_uoms'] = $this->buildTrendMetric($currentTotal, $previousTotal);

        if ($this->hasColumn($table, 'is_active') || $this->hasColumn($table, 'status')) {
            if ($this->hasColumn($table, 'is_active')) {
                $currentActive = (clone $baseQuery)->where($table . '.created_at', '<=', $period['current_end'])->where($table . '.is_active', true)->count($table . '.id');
                $previousActive = (clone $baseQuery)->where($table . '.created_at', '<=', $period['previous_end'])->where($table . '.is_active', true)->count($table . '.id');
            } else {
                $currentActive = (clone $baseQuery)->where($table . '.created_at', '<=', $period['current_end'])->where($table . '.status', 'active')->count($table . '.id');
                $previousActive = (clone $baseQuery)->where($table . '.created_at', '<=', $period['previous_end'])->where($table . '.status', 'active')->count($table . '.id');
            }

            $payload['metrics']['active_uoms'] = $this->buildTrendMetric($currentActive, $previousActive);
        } else {
            $this->addUnavailableMetric($payload, 'active_uoms', 'No UOM status/is_active column found.');
        }

        if ($this->hasTable('products') && $this->hasColumn('products', 'base_uom_id')) {
            $currentRows = DB::table('products')
                ->whereNull('deleted_at')
                ->where('created_at', '<=', $period['current_end'])
                ->selectRaw('base_uom_id, COUNT(*) as products_count')
                ->groupBy('base_uom_id')
                ->get()
                ->keyBy('base_uom_id');

            $previousRows = DB::table('products')
                ->whereNull('deleted_at')
                ->where('created_at', '<=', $period['previous_end'])
                ->selectRaw('base_uom_id, COUNT(*) as products_count')
                ->groupBy('base_uom_id')
                ->get()
                ->keyBy('base_uom_id');

            $payload['tables']['top_10_uom_by_products_count'] = DB::table($table)
                ->leftJoinSub(
                    DB::table('products')
                        ->whereNull('deleted_at')
                        ->where('created_at', '<=', $period['current_end'])
                        ->selectRaw('base_uom_id, COUNT(*) as products_count')
                        ->groupBy('base_uom_id'),
                    'prod_current',
                    fn ($join) => $join->on('prod_current.base_uom_id', '=', $table . '.id')
                )
                ->whereNull($table . '.deleted_at')
                ->selectRaw($table . '.id as uom_id, ' . $table . '.name as uom_name, COALESCE(prod_current.products_count, 0) as products_count')
                ->orderByDesc('products_count')
                ->limit(10)
                ->get()
                ->map(function ($row) use ($previousRows) {
                    $previous = (int) ($previousRows[$row->uom_id]->products_count ?? 0);

                    return [
                        'uom_id' => (int) $row->uom_id,
                        'uom_name' => $row->uom_name,
                        'products_count' => (int) $row->products_count,
                        'trend' => $this->buildTrendMetric((int) $row->products_count, $previous),
                    ];
                })->all();
        } else {
            $this->addUnavailableMetric($payload, 'top_10_uom_by_products_count', 'products table or base_uom_id column was not found.');
        }

        if ($this->hasTable('raw_materials') && $this->hasColumn('raw_materials', 'base_uom_id')) {
            $currentRows = DB::table('raw_materials')
                ->whereNull('deleted_at')
                ->where('created_at', '<=', $period['current_end'])
                ->selectRaw('base_uom_id, COUNT(*) as raw_materials_count')
                ->groupBy('base_uom_id')
                ->get()
                ->keyBy('base_uom_id');

            $previousRows = DB::table('raw_materials')
                ->whereNull('deleted_at')
                ->where('created_at', '<=', $period['previous_end'])
                ->selectRaw('base_uom_id, COUNT(*) as raw_materials_count')
                ->groupBy('base_uom_id')
                ->get()
                ->keyBy('base_uom_id');

            $payload['tables']['top_10_uom_by_raw_materials_count'] = DB::table($table)
                ->leftJoinSub(
                    DB::table('raw_materials')
                        ->whereNull('deleted_at')
                        ->where('created_at', '<=', $period['current_end'])
                        ->selectRaw('base_uom_id, COUNT(*) as raw_materials_count')
                        ->groupBy('base_uom_id'),
                    'rm_current',
                    fn ($join) => $join->on('rm_current.base_uom_id', '=', $table . '.id')
                )
                ->whereNull($table . '.deleted_at')
                ->selectRaw($table . '.id as uom_id, ' . $table . '.name as uom_name, COALESCE(rm_current.raw_materials_count, 0) as raw_materials_count')
                ->orderByDesc('raw_materials_count')
                ->limit(10)
                ->get()
                ->map(function ($row) use ($previousRows) {
                    $previous = (int) ($previousRows[$row->uom_id]->raw_materials_count ?? 0);

                    return [
                        'uom_id' => (int) $row->uom_id,
                        'uom_name' => $row->uom_name,
                        'raw_materials_count' => (int) $row->raw_materials_count,
                        'trend' => $this->buildTrendMetric((int) $row->raw_materials_count, $previous),
                    ];
                })->all();
        } else {
            $this->addUnavailableMetric($payload, 'top_10_uom_by_raw_materials_count', 'raw_materials table or base_uom_id column was not found.');
        }

        $decimalColumn = $this->firstExistingColumn($table, ['is_decimal', 'allow_decimal', 'decimal_supported', 'type', 'precision']);

        if ($decimalColumn === null) {
            $this->addUnavailableMetric(
                $payload,
                'uom_count_by_decimal_and_integer_support',
                'No decimal support indicator column (is_decimal/allow_decimal/decimal_supported/type/precision) was found.'
            );
        } else {
            [$decimalCondition, $integerCondition] = $this->buildDecimalSupportConditions($decimalColumn);

            $currentDecimal = DB::table($table)
                ->whereNull('deleted_at')
                ->where('created_at', '<=', $period['current_end'])
                ->whereRaw($decimalCondition)
                ->count('id');
            $previousDecimal = DB::table($table)
                ->whereNull('deleted_at')
                ->where('created_at', '<=', $period['previous_end'])
                ->whereRaw($decimalCondition)
                ->count('id');

            $currentInteger = DB::table($table)
                ->whereNull('deleted_at')
                ->where('created_at', '<=', $period['current_end'])
                ->whereRaw($integerCondition)
                ->count('id');
            $previousInteger = DB::table($table)
                ->whereNull('deleted_at')
                ->where('created_at', '<=', $period['previous_end'])
                ->whereRaw($integerCondition)
                ->count('id');

            $payload['tables']['uom_count_by_decimal_and_integer_support'] = [
                [
                    'type' => 'decimal_supported',
                    'count' => (int) $currentDecimal,
                    'trend' => $this->buildTrendMetric((int) $currentDecimal, (int) $previousDecimal),
                ],
                [
                    'type' => 'integer_only',
                    'count' => (int) $currentInteger,
                    'trend' => $this->buildTrendMetric((int) $currentInteger, (int) $previousInteger),
                ],
            ];
        }

        $payload['charts']['uoms_trend_overtime'] = $this->buildTimeSeries(
            query: UnitOfMeasurement::query(),
            dateColumn: $table . '.created_at',
            aggregateType: 'count',
            filters: $filters,
            valueKey: 'uoms_count',
        );

        return $payload;
    }

    protected function buildDecimalSupportConditions(string $column): array
    {
        return match ($column) {
            'is_decimal', 'allow_decimal', 'decimal_supported' => [
                "{$column} = 1",
                "{$column} = 0 OR {$column} IS NULL",
            ],
            'type' => [
                "LOWER({$column}) IN ('decimal', 'float', 'double')",
                "LOWER({$column}) IN ('integer', 'int')",
            ],
            'precision' => [
                "{$column} > 0",
                "{$column} = 0 OR {$column} IS NULL",
            ],
            default => [
                '1 = 0',
                '1 = 0',
            ],
        };
    }
}
