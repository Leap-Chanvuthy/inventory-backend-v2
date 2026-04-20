<?php

namespace App\Service;

use App\DTOs\POSCustomerDTO;
use App\Enums\CustomerStatusEnum;
use App\Enums\PaymentTermEnum;
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
                    'discount_percentage' => 0,
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
                    'payment_terms' => PaymentTermEnum::NET_0->value,
                ]
            );

            return $customer->load(['customerCategory', 'customerFinancial']);
        });
    }

    public function toPosDTO(Customer $customer): POSCustomerDTO
    {
        $paymentTerm = $customer->customerFinancial?->payment_terms;
        $paymentTermValue = $paymentTerm instanceof PaymentTermEnum
            ? $paymentTerm->value
            : (is_string($paymentTerm) ? $paymentTerm : PaymentTermEnum::NET_0->value);

        $discountPercentage = round((float) ($customer->customerCategory?->discount_percentage ?? 0), 2);

        return new POSCustomerDTO(
            id: (int) $customer->id,
            name: $this->resolveCustomerName($customer),
            phone: $this->resolveCustomerPhone($customer),
            category: $this->resolveCustomerCategoryName($customer),
            discount_percentage: $discountPercentage,
            payment_terms: $paymentTermValue,
        );
    }
}
