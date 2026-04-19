<?php

namespace App\Service;

use App\DTOs\POSCustomerDTO;
use App\Enums\CustomerStatusEnum;
use App\Models\Customer;
use App\Models\CustomerCategory;
use App\Models\CustomerFinancial;
use App\Traits\FormatsCustomerData;
use Illuminate\Support\Facades\DB;

class CustomerResolverService
{
    use FormatsCustomerData;

    private const WALK_IN_CODE = 'CUSWALKIN';

    public function getOrCreateWalkIn(): Customer
    {
        return DB::transaction(function () {
            $category = CustomerCategory::query()->firstOrCreate(
                ['category_name' => 'Walk-in'],
                [
                    'label_color' => '#D1D5DB',
                    'description' => 'System default category for walk-in POS customers',
                ]
            );

            $customer = Customer::query()->firstOrCreate(
                ['customer_code' => self::WALK_IN_CODE],
                [
                    'fullname' => 'Walk-in Customer',
                    'email_address' => null,
                    'phone_number' => '0000000000',
                    'social_media' => null,
                    'customer_address' => 'N/A',
                    'google_map_link' => null,
                    'customer_status' => CustomerStatusEnum::ACTIVE,
                    'customer_category_id' => $category->id,
                    'customer_note' => 'Auto-generated POS walk-in customer profile',
                    'extra_data' => ['system' => true, 'type' => 'walk-in'],
                ]
            );

            CustomerFinancial::query()->firstOrCreate(
                ['customer_id' => $customer->id],
                [
                    'credit_limit' => 0,
                    'current_balance' => 0,
                    'payment_terms' => null,
                ]
            );

            return $customer->load(['customerCategory', 'customerFinancial']);
        });
    }

    public function toPosDTO(Customer $customer): POSCustomerDTO
    {
        $availableCredit = '0.00';

        if ($customer->relationLoaded('customerFinancial') && $customer->customerFinancial !== null) {
            $availableCredit = number_format(
                (float) $customer->customerFinancial->credit_limit - (float) $customer->customerFinancial->current_balance,
                2,
                '.',
                ''
            );
        }

        return new POSCustomerDTO(
            id: (int) $customer->id,
            name: $this->resolveCustomerName($customer),
            phone: $this->resolveCustomerPhone($customer),
            category: $this->resolveCustomerCategoryName($customer),
            available_credit: $availableCredit,
        );
    }
}
