<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Service\CustomerResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerResolverServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_or_create_walk_in_does_not_duplicate_customer(): void
    {
        $service = app(CustomerResolverService::class);

        $first = $service->getOrCreateWalkIn();
        $second = $service->getOrCreateWalkIn();

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Customer::query()->where('customer_code', 'CUSWALKIN')->count());
    }
}
