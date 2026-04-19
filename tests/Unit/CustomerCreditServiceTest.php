<?php

namespace Tests\Unit;

use App\Enums\CustomerStatusEnum;
use App\Models\Customer;
use App\Models\CustomerCategory;
use App\Models\CustomerFinancial;
use App\Service\CustomerCreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerCreditServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_sale_throws_when_credit_exceeded(): void
    {
        $customer = $this->createCustomer();

        CustomerFinancial::query()->create([
            'customer_id' => $customer->id,
            'credit_limit' => 100,
            'current_balance' => 90,
            'payment_terms' => 'NET_30',
        ]);

        $service = app(CustomerCreditService::class);

        $this->expectException(ValidationException::class);
        $service->applySale($customer, 20.0);
    }

    private function createCustomer(): Customer
    {
        $category = CustomerCategory::query()->create([
            'category_name' => 'Retail',
            'label_color' => '#FFFFFF',
            'description' => 'Retail category',
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
