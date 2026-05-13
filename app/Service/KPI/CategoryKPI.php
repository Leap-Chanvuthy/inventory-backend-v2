<?php

namespace App\Service\KPI;

use Illuminate\Support\Facades\DB;

class CategoryKPI extends BaseKPI
{
    public function summary(array $filters): array
    {
        $payload = $this->basePayload();
        $period = $this->resolvePeriod($filters);

        $supportsSeparateTables = $this->hasTable('product_categories') || $this->hasTable('raw_material_categories') || $this->hasTable('customer_categories');
        $supportsSharedTable = $this->hasTable('categories');

        if (!$supportsSeparateTables && !$supportsSharedTable) {
            $this->addUnavailableMetric($payload, 'total_categories', 'No category tables were found.');
            $this->addUnavailableMetric($payload, 'top_10_categories_by_products_count', 'Category relation tables were not found.');
            $this->addUnavailableMetric($payload, 'top_10_categories_by_raw_materials_count', 'Category relation tables were not found.');
            $this->addUnavailableMetric($payload, 'top_10_categories_by_customers_count', 'Category relation tables were not found.');

            return $payload;
        }

        if ($supportsSeparateTables) {
            $currentTotal = $this->countSeparateCategoriesByEndDate($period['current_end']);
            $previousTotal = $this->countSeparateCategoriesByEndDate($period['previous_end']);
            $payload['metrics']['total_categories'] = $this->buildTrendMetric($currentTotal, $previousTotal);

            $payload['tables']['top_10_categories_by_products_count'] = $this->buildTopCategoryByRelatedItems(
                categoryTable: 'product_categories',
                itemTable: 'products',
                itemFkColumn: 'product_category_id',
                itemMetricName: 'products_count',
                filters: $filters,
            );

            $payload['tables']['top_10_categories_by_raw_materials_count'] = $this->buildTopCategoryByRelatedItems(
                categoryTable: 'raw_material_categories',
                itemTable: 'raw_materials',
                itemFkColumn: 'raw_material_category_id',
                itemMetricName: 'raw_materials_count',
                filters: $filters,
            );

            $payload['tables']['top_10_categories_by_customers_count'] = $this->buildTopCategoryByRelatedItems(
                categoryTable: 'customer_categories',
                itemTable: 'customers',
                itemFkColumn: 'customer_category_id',
                itemMetricName: 'customers_count',
                filters: $filters,
            );

            return $payload;
        }

        if ($supportsSharedTable) {
            if (!$this->hasColumn('categories', 'type')) {
                $this->addUnavailableMetric(
                    $payload,
                    'total_categories',
                    'Shared categories table exists but no type column was found for grouping.'
                );
                return $payload;
            }

            $currentTotal = DB::table('categories')
                ->whereNull('deleted_at')
                ->where('created_at', '<=', $period['current_end'])
                ->count('id');

            $previousTotal = DB::table('categories')
                ->whereNull('deleted_at')
                ->where('created_at', '<=', $period['previous_end'])
                ->count('id');

            $payload['metrics']['total_categories'] = $this->buildTrendMetric($currentTotal, $previousTotal);

            $this->addUnavailableMetric(
                $payload,
                'top_10_categories_by_products_count',
                'Shared categories table mapping to products/raw materials/customers is not defined in this schema.'
            );
            $this->addUnavailableMetric(
                $payload,
                'top_10_categories_by_raw_materials_count',
                'Shared categories table mapping to products/raw materials/customers is not defined in this schema.'
            );
            $this->addUnavailableMetric(
                $payload,
                'top_10_categories_by_customers_count',
                'Shared categories table mapping to products/raw materials/customers is not defined in this schema.'
            );
        }

        return $payload;
    }

    protected function countSeparateCategoriesByEndDate($endDate): int
    {
        $count = 0;

        foreach (['product_categories', 'raw_material_categories', 'customer_categories'] as $table) {
            if (!$this->hasTable($table)) {
                continue;
            }

            $count += (int) DB::table($table)
                ->whereNull('deleted_at')
                ->where('created_at', '<=', $endDate)
                ->count('id');
        }

        return $count;
    }

    protected function buildTopCategoryByRelatedItems(
        string $categoryTable,
        string $itemTable,
        string $itemFkColumn,
        string $itemMetricName,
        array $filters
    ): array {
        if (!$this->hasTable($categoryTable) || !$this->hasTable($itemTable) || !$this->hasColumn($itemTable, $itemFkColumn)) {
            return [];
        }

        $period = $this->resolvePeriod($filters);

        $currentSub = DB::table($itemTable)
            ->whereNull($itemTable . '.deleted_at')
            ->where($itemTable . '.created_at', '<=', $period['current_end'])
            ->selectRaw($itemFkColumn . ' as category_id, COUNT(*) as item_count')
            ->groupBy($itemFkColumn);

        $previousMap = DB::table($itemTable)
            ->whereNull($itemTable . '.deleted_at')
            ->where($itemTable . '.created_at', '<=', $period['previous_end'])
            ->selectRaw($itemFkColumn . ' as category_id, COUNT(*) as item_count')
            ->groupBy($itemFkColumn)
            ->get()
            ->keyBy('category_id');

        return DB::table($categoryTable)
            ->leftJoinSub($currentSub, 'cur_items', fn ($join) => $join->on('cur_items.category_id', '=', $categoryTable . '.id'))
            ->whereNull($categoryTable . '.deleted_at')
            ->selectRaw(
                $categoryTable . '.id as category_id, ' .
                $categoryTable . '.category_name as category_name, ' .
                'COALESCE(cur_items.item_count, 0) as item_count'
            )
            ->orderByDesc('item_count')
            ->limit(10)
            ->get()
            ->map(function ($row) use ($previousMap, $itemMetricName) {
                $previous = (int) ($previousMap[$row->category_id]->item_count ?? 0);

                return [
                    'category_id' => (int) $row->category_id,
                    'category_name' => $row->category_name,
                    $itemMetricName => (int) $row->item_count,
                    'trend' => $this->buildTrendMetric((int) $row->item_count, $previous),
                ];
            })
            ->all();
    }
}
