<?php

namespace Tests\Feature;

use App\Enums\CustomerStatusEnum;
use App\Models\Customer;
use App\Models\CustomerCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPosApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_search_returns_lightweight_customer_payload(): void
    {
        $this->withoutMiddleware();

        $category = CustomerCategory::query()->create([
            'category_name' => 'Retail',
            'label_color' => '#FFFFFF',
            'description' => 'Retail category',
        ]);

        Customer::query()->create([
            'customer_code' => 'CUSAPI001',
            'fullname' => 'POS Customer',
            'email_address' => 'pos@example.com',
            'phone_number' => '0888888888',
            'social_media' => null,
            'customer_address' => 'Phnom Penh',
            'google_map_link' => null,
            'customer_status' => CustomerStatusEnum::ACTIVE,
            'customer_category_id' => $category->id,
            'customer_note' => null,
        ]);

        $response = $this->getJson('/api/customers/pos-search?keyword=0888&limit=10');

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    '*' => ['id', 'name', 'phone', 'category', 'status'],
                ],
            ]);
    }
}
