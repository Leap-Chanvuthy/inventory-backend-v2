<?php

namespace App\Service;

use App\DTOs\CustomerSearchResultDTO;
use App\Models\Customer;
use App\Traits\FormatsCustomerData;
use Illuminate\Support\Collection;

class CustomerSearchService
{
    use FormatsCustomerData;

    public function search(string $keyword, int $limit = 15): Collection
    {
        $term = trim($keyword);
        $limit = max(1, min($limit, 20));

        if ($term === '') {
            return collect();
        }

        $customers = Customer::query()
            ->select([
                'id',
                'fullname',
                'phone_number',
                'customer_status',
                'customer_category_id',
                'updated_at',
            ])
            ->with(['customerCategory:id,category_name'])
            ->where(function ($query) use ($term) {
                $query->where('phone_number', 'LIKE', $term . '%')
                    ->orWhere('phone_number', $term)
                    ->orWhere('customer_code', 'LIKE', '%' . $term . '%')
                    ->orWhere('fullname', 'LIKE', '%' . $term . '%');
            })
            ->orderByRaw(
                'CASE WHEN phone_number = ? THEN 0 WHEN phone_number LIKE ? THEN 1 ELSE 2 END',
                [$term, $term . '%']
            )
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();

        return $customers->map(function (Customer $customer) {
            $statusValue = is_object($customer->customer_status) && property_exists($customer->customer_status, 'value')
                ? $customer->customer_status->value
                : (string) $customer->customer_status;

            return new CustomerSearchResultDTO(
                id: (int) $customer->id,
                name: $this->resolveCustomerName($customer),
                phone: $this->resolveCustomerPhone($customer),
                category: $this->resolveCustomerCategoryName($customer),
                status: $statusValue,
                discount_percentage: round((float) ($customer->customerCategory?->discount_percentage ?? 0), 2),
            );
        });
    }
}
