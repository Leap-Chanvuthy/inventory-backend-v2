<?php

namespace App\Service\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

class StockLotDateService
{
    /**
     * Business rule:
     * A lot is expired only when expiry_date < today (server date).
     * If expiry_date is today, it remains usable until end of day.
     */
    public function isExpired(?string $expiryDate): bool
    {
        if (empty($expiryDate)) {
            return false;
        }

        return Carbon::parse($expiryDate)->startOfDay()->lt(now()->startOfDay());
    }

    public function daysUntilExpiry(?string $expiryDate): ?int
    {
        if (empty($expiryDate)) {
            return null;
        }

        return now()->startOfDay()->diffInDays(Carbon::parse($expiryDate)->startOfDay(), false);
    }

    public function normalizeMovementDate(?string $movementDate): string
    {
        if (empty($movementDate)) {
            return now()->toDateTimeString();
        }

        return Carbon::parse($movementDate)->toDateTimeString();
    }

    public function today(): CarbonInterface
    {
        return now()->startOfDay();
    }
}
