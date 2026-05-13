<?php

namespace App\Service\KPI\Concerns;

use App\Service\KPI\Support\KPIPeriodResolver;
use App\Service\KPI\Support\KPITimeSeriesBuilder;
use App\Service\KPI\Support\KPITrendCalculator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

trait AppliesDateRange
{
    protected function applyDateRange(
        EloquentBuilder|QueryBuilder $query,
        array $filters,
        string $column = 'created_at'
    ): EloquentBuilder|QueryBuilder {
        return $this->applyCurrentPeriod($query, $filters, $column);
    }

    protected function applyCurrentPeriod(
        EloquentBuilder|QueryBuilder $query,
        array $filters,
        string $column = 'created_at'
    ): EloquentBuilder|QueryBuilder {
        $period = $this->resolvePeriod($filters);

        return $query->whereBetween($column, [
            $period['current_start']->copy()->startOfDay(),
            $period['current_end']->copy()->endOfDay(),
        ]);
    }

    protected function applyPreviousPeriod(
        EloquentBuilder|QueryBuilder $query,
        array $filters,
        string $column = 'created_at'
    ): EloquentBuilder|QueryBuilder {
        $period = $this->resolvePeriod($filters);

        return $query->whereBetween($column, [
            $period['previous_start']->copy()->startOfDay(),
            $period['previous_end']->copy()->endOfDay(),
        ]);
    }

    protected function buildTrendMetric(float|int $currentValue, float|int $previousValue): array
    {
        return $this->trendCalculator()->calculate($currentValue, $previousValue);
    }

    protected function buildTimeSeries(
        EloquentBuilder|QueryBuilder $query,
        string $dateColumn,
        ?string $aggregateColumn = null,
        string $aggregateType = 'count',
        array $filters = [],
        string $valueKey = 'value'
    ): array {
        return $this->timeSeriesBuilder()->build(
            query: $query,
            dateColumn: $dateColumn,
            aggregateColumn: $aggregateColumn,
            aggregateType: $aggregateType,
            filters: $filters,
            valueKey: $valueKey,
        );
    }

    protected function resolvePeriod(array $filters): array
    {
        if (!isset($filters['__period']) || !is_array($filters['__period'])) {
            $filters['__period'] = $this->periodResolver()->resolve($filters);
        }

        return $filters['__period'];
    }

    protected function normalizedDateString(array $filters, string $key): ?string
    {
        if (isset($filters['__period']) && is_array($filters['__period'])) {
            if ($key === 'start_date') {
                return $filters['__period']['start_date'];
            }

            if ($key === 'end_date') {
                return $filters['__period']['end_date'];
            }
        }

        $value = $filters[$key] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable $exception) {
            return null;
        }
    }

    protected function periodResolver(): KPIPeriodResolver
    {
        return app(KPIPeriodResolver::class);
    }

    protected function trendCalculator(): KPITrendCalculator
    {
        return app(KPITrendCalculator::class);
    }

    protected function timeSeriesBuilder(): KPITimeSeriesBuilder
    {
        return app(KPITimeSeriesBuilder::class);
    }
}
