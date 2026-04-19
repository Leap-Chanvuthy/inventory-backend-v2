<?php

namespace App\Service;

use App\DTOs\CustomerProfileDTO;
use App\Models\Customer;

class CustomerProfileService
{
    public function getProfile(int $customerId): CustomerProfileDTO
    {
        $customer = Customer::query()
            ->with([
                'customerCategory:id,category_name,label_color',
                'customerFinancial:id,customer_id,credit_limit,current_balance,payment_terms',
                'addresses:id,customer_id,type,address,google_map_link,is_default,created_at',
                'tags:id,name',
            ])
            ->findOrFail($customerId);

        return new CustomerProfileDTO(
            basic_info: [
                'id' => $customer->id,
                'customer_code' => $customer->customer_code,
                'fullname' => $customer->fullname,
                'email_address' => $customer->email_address,
                'phone_number' => $customer->phone_number,
                'status' => $customer->customer_status?->value,
                'category' => $customer->customerCategory?->category_name,
                'note' => $customer->customer_note,
                'extra_data' => $customer->extra_data,
            ],
            financial: $customer->customerFinancial ? [
                'credit_limit' => (float) $customer->customerFinancial->credit_limit,
                'current_balance' => (float) $customer->customerFinancial->current_balance,
                'payment_terms' => $customer->customerFinancial->payment_terms,
                'available_credit' => (float) $customer->customerFinancial->credit_limit - (float) $customer->customerFinancial->current_balance,
            ] : null,
            addresses: $customer->addresses
                ->map(fn ($address) => [
                    'id' => $address->id,
                    'type' => $address->type?->value,
                    'address' => $address->address,
                    'google_map_link' => $address->google_map_link,
                    'is_default' => (bool) $address->is_default,
                    'created_at' => $address->created_at?->toDateTimeString(),
                ])
                ->values()
                ->all(),
            tags: $customer->tags
                ->map(fn ($tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                ])
                ->values()
                ->all(),
        );
    }
}
