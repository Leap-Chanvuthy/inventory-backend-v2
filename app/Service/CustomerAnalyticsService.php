<?php

namespace App\Service;

use App\DTOs\CustomerStatsDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CustomerAnalyticsService
{
    public function getStats(int $customerId): CustomerStatsDTO
    {
        if (!Schema::hasTable('pos_orders')) {
            return new CustomerStatsDTO(0.0, 0, null);
        }

        $row = DB::table('pos_orders')
            ->where('customer_id', $customerId)
            ->selectRaw('COALESCE(SUM(total_amount), 0) as total_spent')
            ->selectRaw('COUNT(*) as order_count')
            ->selectRaw('MAX(created_at) as last_purchase_date')
            ->first();

        return new CustomerStatsDTO(
            total_spent: (float) ($row->total_spent ?? 0),
            order_count: (int) ($row->order_count ?? 0),
            last_purchase_date: $row->last_purchase_date,
        );
    }
}
