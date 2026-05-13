<?php

namespace App\Service\KPI\Support;

use Carbon\Carbon;
use InvalidArgumentException;

class KPIPeriodResolver
{
    public function resolve(array $filters): array
    {
        $startDate = $this->parseDate($filters['start_date'] ?? null, 'start_date');
        $endDate = $this->parseDate($filters['end_date'] ?? null, 'end_date');

        [$currentStart, $currentEnd] = $this->resolveCurrentPeriod($startDate, $endDate);

        if ($currentEnd->lt($currentStart)) {
            throw new InvalidArgumentException('The end_date must be after or equal to start_date.');
        }

        $totalDays = $currentStart->diffInDays($currentEnd) + 1;

        $previousEnd = $currentStart->copy()->subDay()->endOfDay();
        $previousStart = $previousEnd->copy()->subDays($totalDays - 1)->startOfDay();

        return [
            'current_start' => $currentStart,
            'current_end' => $currentEnd,
            'previous_start' => $previousStart,
            'previous_end' => $previousEnd,
            'total_days' => $totalDays,
            'granularity' => $this->resolveGranularity($totalDays),
            'start_date' => $currentStart->toDateString(),
            'end_date' => $currentEnd->toDateString(),
            'previous_start_date' => $previousStart->toDateString(),
            'previous_end_date' => $previousEnd->toDateString(),
        ];
    }

    protected function resolveCurrentPeriod(?Carbon $startDate, ?Carbon $endDate): array
    {
        if ($startDate && $endDate) {
            return [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()];
        }

        if ($startDate) {
            return [$startDate->copy()->startOfDay(), $startDate->copy()->addDays(29)->endOfDay()];
        }

        if ($endDate) {
            return [$endDate->copy()->subDays(29)->startOfDay(), $endDate->copy()->endOfDay()];
        }

        $now = now();

        return [$now->copy()->startOfMonth()->startOfDay(), $now->copy()->endOfDay()];
    }

    protected function resolveGranularity(int $totalDays): string
    {
        if ($totalDays <= 31) {
            return 'day';
        }

        if ($totalDays <= 183) {
            return 'week';
        }

        return 'month';
    }

    protected function parseDate(mixed $value, string $fieldName): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $exception) {
            throw new InvalidArgumentException("The {$fieldName} value is invalid.");
        }
    }
}
