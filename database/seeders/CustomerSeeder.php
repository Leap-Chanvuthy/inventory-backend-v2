<?php

namespace Database\Seeders;

use App\Enums\AddressTypeEnum;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerCategory;
use App\Models\CustomerFinancial;
use App\Models\CustomerTag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        // Deterministic dataset for repeatable local/test environments.
        fake()->seed(20260419);
        mt_srand(20260419);

        $this->ensureCategories();

        $tagIds = $this->seedTags();
        $targetCount = 180;
        $chunkSize = 60;
        $createdIds = [];

        // 1) Seed customers in chunks.
        for ($created = 0; $created < $targetCount; $created += $chunkSize) {
            $batchCount = min($chunkSize, $targetCount - $created);
            $batch = Customer::factory()->count($batchCount)->create();
            array_push($createdIds, ...$batch->pluck('id')->all());
        }

        // 2) Seed related data in order: financials -> addresses -> tags.
        Customer::query()
            ->select(['id', 'customer_code', 'fullname', 'customer_status', 'customer_category_id'])
            ->with('customerCategory:id,category_name')
            ->whereIn('id', $createdIds)
            ->orderBy('id')
            ->chunkById(200, function (Collection $customers) use ($tagIds): void {
                $financialRows = [];
                $addressRows = [];
                $tagMapRows = [];
                $now = now();

                foreach ($customers as $customer) {
                    $categoryName = Str::lower((string) ($customer->customerCategory?->category_name ?? ''));

                    $financial = $this->buildFinancialPayload($customer->id, $customer->customer_code, $categoryName, $now);
                    if ($financial !== null) {
                        $financialRows[] = $financial;
                    }

                    [$billingAddress, $shippingAddress] = $this->buildAddresses(
                        $customer->id,
                        $customer->customer_code,
                        $now
                    );

                    $addressRows[] = $billingAddress;
                    if ($shippingAddress !== null) {
                        $addressRows[] = $shippingAddress;
                    }

                    $statusValue = null;
                    if ($customer->customer_status instanceof \BackedEnum) {
                        $statusValue = $customer->customer_status->value;
                    } else {
                        $statusValue = (string) $customer->customer_status;
                    }

                    foreach ($this->resolveTagIds($tagIds, $customer->customer_code, $categoryName, $statusValue) as $tagId) {
                        $tagMapRows[] = [
                            'customer_id' => $customer->id,
                            'tag_id' => $tagId,
                        ];
                    }
                }

                if (!empty($financialRows)) {
                    CustomerFinancial::query()->upsert(
                        $financialRows,
                        ['customer_id'],
                        ['credit_limit', 'current_balance', 'payment_terms', 'updated_at']
                    );
                }

                if (!empty($addressRows)) {
                    CustomerAddress::query()->insert($addressRows);
                }

                if (!empty($tagMapRows)) {
                    DB::table('customer_tag_map')->insertOrIgnore($tagMapRows);
                }
            }, 'id');
    }

    protected function ensureCategories(): void
    {
        if (CustomerCategory::query()->exists()) {
            return;
        }

        $this->call(CustomerCategorySeeder::class);
    }

    protected function seedTags(): Collection
    {
        $tagNames = ['VIP', 'Frequent Buyer', 'High Risk', 'Wholesale', 'Retail'];
        $now = now();

        $rows = array_map(static fn (string $name) => [
            'name' => $name,
            'created_at' => $now,
            'updated_at' => $now,
        ], $tagNames);

        CustomerTag::query()->upsert($rows, ['name'], ['updated_at']);

        return CustomerTag::query()
            ->whereIn('name', $tagNames)
            ->pluck('id', 'name');
    }

    protected function buildFinancialPayload(int $customerId, string $customerCode, string $categoryName, $now): ?array
    {
        $hash = abs(crc32($customerCode));
        $terms = ['COD', 'Net 15', 'Net 30', 'Net 45', 'Net 60'];

        $range = null;
        if (str_contains($categoryName, 'vip')) {
            $range = [20000, 50000, 'Net 60'];
        } elseif (str_contains($categoryName, 'wholesale') || str_contains($categoryName, 'high volume')) {
            $range = [5000, 20000, 'Net 30'];
        } elseif (str_contains($categoryName, 'corporate') || str_contains($categoryName, 'government')) {
            $range = [8000, 25000, 'Net 45'];
        } elseif (
            (str_contains($categoryName, 'retail') || str_contains($categoryName, 'regular') || str_contains($categoryName, 'small business'))
            && ($hash % 10) < 3
        ) {
            $range = [0, 500, 'COD'];
        }

        if ($range === null) {
            return null;
        }

        [$min, $max, $preferredTerms] = $range;
        $creditLimit = (float) ($min + ($hash % (($max - $min) + 1)));
        $balanceRatio = ($hash % 35) / 100;
        $currentBalance = round($creditLimit * $balanceRatio, 2);

        return [
            'customer_id' => $customerId,
            'credit_limit' => round($creditLimit, 2),
            'current_balance' => $currentBalance,
            'payment_terms' => $preferredTerms ?: $terms[$hash % count($terms)],
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    protected function buildAddresses(int $customerId, string $customerCode, $now): array
    {
        $hash = abs(crc32($customerCode));
        $billingAddress = $this->formatAddress($hash, false);

        $billing = [
            'customer_id' => $customerId,
            'type' => AddressTypeEnum::BILLING->value,
            'address' => $billingAddress,
            'google_map_link' => 'https://maps.google.com/?q=' . rawurlencode($billingAddress),
            'is_default' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $shipping = null;
        if (($hash % 10) >= 4) {
            $shippingAddress = $this->formatAddress($hash, true);
            $shipping = [
                'customer_id' => $customerId,
                'type' => AddressTypeEnum::SHIPPING->value,
                'address' => $shippingAddress,
                'google_map_link' => 'https://maps.google.com/?q=' . rawurlencode($shippingAddress),
                'is_default' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return [$billing, $shipping];
    }

    protected function formatAddress(int $hash, bool $forShipping): string
    {
        $streets = ['St. 51', 'St. 105', 'St. 163', 'St. 245', 'St. 271', 'St. 310', 'NR1', 'NR2'];
        $areas = [
            'Boeung Keng Kang I, Chamkar Mon, Phnom Penh',
            'Tuek Thla, Sen Sok, Phnom Penh',
            'Phsar Doeum Thkov, Chamkar Mon, Phnom Penh',
            'Svay Dangkum, Siem Reap',
            'Sangkat Muoy, Sihanoukville',
            'Svay Por, Battambang',
        ];

        $buildingNo = 5 + ($hash % 990);
        $street = $streets[$hash % count($streets)];
        $area = $areas[$hash % count($areas)];
        $unit = $forShipping ? 'Warehouse ' . (($hash % 9) + 1) . ', ' : '';

        return $unit . 'No. ' . $buildingNo . ', ' . $street . ', ' . $area;
    }

    protected function resolveTagIds(Collection $tagIdsByName, string $customerCode, string $categoryName, string $status): array
    {
        $hash = abs(crc32($customerCode));
        $candidates = [];

        if (str_contains($categoryName, 'vip') && $tagIdsByName->has('VIP')) {
            $candidates[] = (int) $tagIdsByName->get('VIP');
        }

        if ((str_contains($categoryName, 'wholesale') || str_contains($categoryName, 'high volume')) && $tagIdsByName->has('Wholesale')) {
            $candidates[] = (int) $tagIdsByName->get('Wholesale');
        }

        if ((str_contains($categoryName, 'retail') || str_contains($categoryName, 'regular') || str_contains($categoryName, 'walk-in')) && $tagIdsByName->has('Retail')) {
            $candidates[] = (int) $tagIdsByName->get('Retail');
        }

        if (($hash % 10) <= 5 && $tagIdsByName->has('Frequent Buyer')) {
            $candidates[] = (int) $tagIdsByName->get('Frequent Buyer');
        }

        if (($status === 'blacklisted' || ($hash % 20) === 0) && $tagIdsByName->has('High Risk')) {
            $candidates[] = (int) $tagIdsByName->get('High Risk');
        }

        $candidates = array_values(array_unique($candidates));
        $maxTags = $hash % 4; // 0 to 3 tags.

        if ($maxTags === 0 || empty($candidates)) {
            return [];
        }

        return array_slice($candidates, 0, min($maxTags, 3));
    }
}
