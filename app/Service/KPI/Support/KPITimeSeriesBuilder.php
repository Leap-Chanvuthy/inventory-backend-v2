<?php

namespace App\Service\KPI\Support;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class KPITimeSeriesBuilder
{
    public function __construct(protected KPIPeriodResolver $periodResolver)
    {
    }

    public function build(
        EloquentBuilder|QueryBuilder $query,
        string $dateColumn,
        ?string $aggregateColumn = null,
        string $aggregateType = 'count',
        array $filters = [],
        string $valueKey = 'value'
    ): array {
        $period = $filters['__period'] ?? $this->periodResolver->resolve($filters);

        $query->whereBetween($dateColumn, [
            $period['current_start']->copy()->startOfDay(),
            $period['current_end']->copy()->endOfDay(),
        ]);

        [$periodKeySql] = $this->resolveGroupingSql($dateColumn, $period['granularity']);
        $aggregateSql = $this->resolveAggregateSql($aggregateType, $aggregateColumn);

        $rows = $query
            ->selectRaw("{$periodKeySql} as period_key, {$aggregateSql} as metric_value")
            ->groupByRaw($periodKeySql)
            ->orderByRaw($periodKeySql . ' ASC')
            ->get();

        $rowMap = [];

        foreach ($rows as $row) {
            $rowMap[(string) $row->period_key] = (float) ($row->metric_value ?? 0);
        }

        $series = [];

        foreach ($this->generateBuckets($period) as $bucket) {
            $value = $rowMap[$bucket['key']] ?? 0;

            $series[] = [
                'date' => $bucket['date'],
                'label' => $bucket['label'],
                $valueKey => $this->normalizeNumber($value),
            ];
        }

        return $series;
    }

    protected function resolveGroupingSql(string $dateColumn, string $granularity): array
    {
        if ($granularity === 'week') {
            return [
                "CAST(YEARWEEK({$dateColumn}, 1) AS CHAR)",
            ];
        }

        if ($granularity === 'month') {
            return [
                "DATE_FORMAT({$dateColumn}, '%Y-%m-01')",
            ];
        }

        return [
            "DATE({$dateColumn})",
        ];
    }

    protected function resolveAggregateSql(string $aggregateType, ?string $aggregateColumn = null): string
    {
        $column = $aggregateColumn ?: '*';

        return match (strtolower($aggregateType)) {
            'sum' => "COALESCE(SUM({$column}), 0)",
            'avg' => "COALESCE(AVG({$column}), 0)",
            default => 'COUNT(*)',
        };
    }

    protected function generateBuckets(array $period): array
    {
        $start = $period['current_start']->copy();
        $end = $period['current_end']->copy();
        $granularity = $period['granularity'];
        $buckets = [];

        if ($granularity === 'week') {
            $cursor = $start->copy()->startOfWeek(Carbon::MONDAY);
            $last = $end->copy()->endOfWeek(Carbon::SUNDAY);

            while ($cursor->lte($last)) {
                $buckets[] = [
                    'key' => $cursor->format('oW'),
                    'date' => $cursor->toDateString(),
                    'label' => 'Week of ' . $cursor->toDateString(),
                ];

                $cursor->addWeek();
            }

            return $buckets;
        }

        if ($granularity === 'month') {
            $cursor = $start->copy()->startOfMonth();
            $last = $end->copy()->startOfMonth();

            while ($cursor->lte($last)) {
                $buckets[] = [
                    'key' => $cursor->format('Y-m-01'),
                    'date' => $cursor->format('Y-m-01'),
                    'label' => $cursor->format('M Y'),
                ];

                $cursor->addMonth();
            }

            return $buckets;
        }

        $cursor = $start->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $buckets[] = [
                'key' => $cursor->toDateString(),
                'date' => $cursor->toDateString(),
                'label' => $cursor->toDateString(),
            ];

            $cursor->addDay();
        }

        return $buckets;
    }

    protected function normalizeNumber(float $value): float|int
    {
        if (abs($value - (int) $value) < 0.0000001) {
            return (int) round($value);
        }

        return round($value, 2);
    }
}
