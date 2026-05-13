<?php

namespace App\Service\KPI;

use App\Service\KPI\Concerns\AppliesDateRange;
use App\Service\KPI\Contracts\KPIInterface;
use App\Service\KPI\Support\KPISchemaInspector;
use Illuminate\Database\Eloquent\Builder;

abstract class BaseKPI implements KPIInterface
{
    use AppliesDateRange;

    protected function schemaInspector(): KPISchemaInspector
    {
        return app(KPISchemaInspector::class);
    }

    protected function hasTable(string $table): bool
    {
        return $this->schemaInspector()->tableExists($table);
    }

    protected function hasColumn(string $table, string $column): bool
    {
        return $this->schemaInspector()->columnExists($table, $column);
    }

    protected function firstExistingColumn(string $table, array $columns): ?string
    {
        return $this->schemaInspector()->firstExistingColumn($table, $columns);
    }

    protected function applyExactFilterIfColumnExists(
        Builder $query,
        string $table,
        array $filters,
        string $filterKey,
        ?string $column = null
    ): Builder {
        if (!array_key_exists($filterKey, $filters) || $filters[$filterKey] === null || $filters[$filterKey] === '') {
            return $query;
        }

        $columnName = $column ?? $filterKey;

        if (!$this->hasColumn($table, $columnName)) {
            return $query;
        }

        return $query->where($table . '.' . $columnName, $filters[$filterKey]);
    }

    protected function applyStatusFilterIfColumnExists(
        Builder $query,
        string $table,
        array $filters,
        array $candidateColumns = ['status', 'is_active']
    ): Builder {
        if (!array_key_exists('status', $filters) || $filters['status'] === null || $filters['status'] === '') {
            return $query;
        }

        foreach ($candidateColumns as $column) {
            if (!$this->hasColumn($table, $column)) {
                continue;
            }

            if ($column === 'is_active') {
                $bool = $this->normalizeBoolean($filters['status']);

                if ($bool !== null) {
                    return $query->where($table . '.' . $column, $bool);
                }

                return $query;
            }

            return $query->where($table . '.' . $column, $filters['status']);
        }

        return $query;
    }

    protected function normalizeBoolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower((string) $value);

        if (in_array($normalized, ['1', 'true', 'yes', 'active'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'inactive'], true)) {
            return false;
        }

        return null;
    }

    protected function groupedCountsToArray(iterable $rows, string $keyField = 'label', string $countField = 'total'): array
    {
        $result = [];

        foreach ($rows as $row) {
            $label = (string) ($row->{$keyField} ?? 'unknown');
            $result[$label] = (int) ($row->{$countField} ?? 0);
        }

        return $result;
    }

    protected function basePayload(): array
    {
        return [
            'metrics' => [],
            'charts' => [],
            'tables' => [],
            'unavailable_metrics' => [],
        ];
    }

    protected function addUnavailableMetric(array &$payload, string $metric, string $reason): void
    {
        $payload['unavailable_metrics'][] = [
            'metric' => $metric,
            'reason' => $reason,
        ];
    }

    protected function normalizeNumber(float|int $value): float|int
    {
        if (is_int($value)) {
            return $value;
        }

        $floatValue = (float) $value;

        if (abs($floatValue - (int) $floatValue) < 0.0000001) {
            return (int) round($floatValue);
        }

        return round($floatValue, 2);
    }
}
