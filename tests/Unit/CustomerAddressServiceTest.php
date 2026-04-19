<?php

namespace Tests\Unit;

use App\Enums\AddressTypeEnum;
use App\Enums\CustomerStatusEnum;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerCategory;
use App\Service\CustomerAddressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAddressServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_set_default_address_keeps_only_one_default_per_type(): void
    {
        $customer = $this->createCustomer();

        $firstBilling = CustomerAddress::query()->create([
            'customer_id' => $customer->id,
            'type' => AddressTypeEnum::BILLING,
            'address' => 'Billing Address 1',
            'google_map_link' => null,
            'is_default' => true,
        ]);

        $secondBilling = CustomerAddress::query()->create([
            'customer_id' => $customer->id,
            'type' => AddressTypeEnum::BILLING,
            'address' => 'Billing Address 2',
            'google_map_link' => null,
            'is_default' => false,
        ]);

        $shipping = CustomerAddress::query()->create([
            'customer_id' => $customer->id,
            'type' => AddressTypeEnum::SHIPPING,
            'address' => 'Shipping Address',
            'google_map_link' => null,
            'is_default' => true,
        ]);

        $service = app(CustomerAddressService::class);
        $service->setDefaultAddress((int) $customer->id, (int) $secondBilling->id);

        $this->assertFalse((bool) $firstBilling->fresh()->is_default);
        $this->assertTrue((bool) $secondBilling->fresh()->is_default);
        $this->assertTrue((bool) $shipping->fresh()->is_default);
    }

    private function createCustomer(): Customer
    {
        $category = CustomerCategory::query()->create([
            'category_name' => 'Retail',
            'label_color' => '#FFFFFF',
            'description' => 'Retail category',
        ]);

        return Customer::query()->create([
            'customer_code' => 'CUSUT002',
            'fullname' => 'Address Customer',
            'email_address' => 'address@example.com',
            'phone_number' => '0123456790',
            'social_media' => null,
            'customer_address' => 'Phnom Penh',
            'google_map_link' => null,
            'customer_status' => CustomerStatusEnum::ACTIVE,
            'customer_category_id' => $category->id,
            'customer_note' => null,
        ]);
    }
}
