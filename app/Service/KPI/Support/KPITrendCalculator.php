<?php

namespace App\Service\KPI\Support;

class KPITrendCalculator
{
    public function calculate(float|int $currentValue, float|int $previousValue): array
    {
        $current = (float) $currentValue;
        $previous = (float) $previousValue;
        $change = $current - $previous;

        $percentageChange = null;

        if ($previous != 0.0) {
            $percentageChange = round(($change / $previous) * 100, 2);
        }

        $direction = $this->resolveDirection($current, $previous);

        return [
            'current' => $this->normalizeNumber($currentValue),
            'previous' => $this->normalizeNumber($previousValue),
            'change' => $this->normalizeNumber($change),
            'percentage_change' => $percentageChange,
            'percentage_change_display' => $this->formatPercentageChange($percentageChange, $direction),
            'direction' => $direction,
        ];
    }

    protected function resolveDirection(float $current, float $previous): string
    {
        if ($current > $previous) {
            return 'up';
        }

        if ($current < $previous) {
            return 'down';
        }

        return 'neutral';
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

    protected function formatPercentageChange(?float $percentageChange, string $direction): ?string
    {
        if ($percentageChange === null) {
            return null;
        }

        $absolute = number_format(abs($percentageChange), 2, '.', '');

        if ($direction === 'up') {
            return '+' . $absolute . '%';
        }

        if ($direction === 'down') {
            return '-' . $absolute . '%';
        }

        return '0.00%';
    }
}
