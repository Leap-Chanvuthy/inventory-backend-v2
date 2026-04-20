<?php

namespace Tests\Unit;

use App\Enums\CustomerStatusEnum;
use App\Enums\PaymentTermEnum;
use App\Models\Customer;
use App\Models\CustomerCategory;
use App\Models\CustomerFinancial;
use App\Service\CustomerCreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerCreditServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_sale_is_noop_when_credit_tracking_removed(): void
    {
        $customer = $this->createCustomer();

        CustomerFinancial::query()->create([
            'customer_id' => $customer->id,
            'payment_terms' => PaymentTermEnum::NET_30->value,
        ]);

        $service = app(CustomerCreditService::class);
        $service->applySale($customer, 20.0);

        $this->assertDatabaseHas('customer_financials', [
            'customer_id' => $customer->id,
            'payment_terms' => PaymentTermEnum::NET_30->value,
        ]);
    }

    private function createCustomer(): Customer
    {
        $category = CustomerCategory::query()->create([
            'category_name' => 'Retail',
            'label_color' => '#FFFFFF',
            'description' => 'Retail category',
            'discount_percentage' => 0,
        ]);

        return Customer::query()->create([
            'customer_code' => 'CUSUT001',
            'fullname' => 'Test Customer',
            'email_address' => 'test@example.com',
            'phone_number' => '0123456789',
            'social_media' => null,
            'customer_address' => 'Phnom Penh',
            'google_map_link' => null,
            'customer_status' => CustomerStatusEnum::ACTIVE,
            'customer_category_id' => $category->id,
            'customer_note' => null,
        ]);
    }
}
