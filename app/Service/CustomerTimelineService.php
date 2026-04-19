<?php

namespace App\Service;

use App\DTOs\CustomerTimelineItemDTO;
use App\Models\Customer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use OwenIt\Auditing\Models\Audit;

class CustomerTimelineService
{
    public function getTimeline(int $customerId, int $limit = 50): Collection
    {
        $limit = max(1, min($limit, 100));

        $auditItems = Audit::query()
            ->where('auditable_type', Customer::class)
            ->where('auditable_id', $customerId)
            ->latest('created_at')
            ->limit($limit)
            ->get(['event', 'old_values', 'new_values', 'created_at'])
            ->map(function ($audit) {
                return new CustomerTimelineItemDTO(
                    source: 'audit',
                    event: (string) $audit->event,
                    payload: [
                        'old' => $audit->old_values,
                        'new' => $audit->new_values,
                    ],
                    occurred_at: (string) $audit->created_at,
                );
            });

        if (!Schema::hasTable('pos_orders')) {
            return $auditItems->values();
        }

        $orderItems = DB::table('pos_orders')
            ->where('customer_id', $customerId)
            ->latest('created_at')
            ->limit($limit)
            ->get(['id', 'order_number', 'total_amount', 'created_at'])
            ->map(function ($order) {
                return new CustomerTimelineItemDTO(
                    source: 'pos_order',
                    event: 'order.created',
                    payload: [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'total_amount' => $order->total_amount,
                    ],
                    occurred_at: (string) $order->created_at,
                );
            });

        return $auditItems
            ->concat($orderItems)
            ->sortByDesc(fn (CustomerTimelineItemDTO $item) => $item->occurred_at)
            ->take($limit)
            ->values();
    }
}
