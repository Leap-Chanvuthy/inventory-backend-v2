<?php

namespace Database\Factories;

use App\Enums\CustomerStatusEnum;
use App\Models\Customer;
use App\Models\CustomerCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    protected static int $recordSequence = 1;
    protected static bool $codeInitialized = false;
    protected static int $codeSequence = 100000;
    protected static ?array $categoryRows = null;

    /**
     * Backward-compatible helper for seeders that call CustomerFactory::factory().
     * Prefer using Customer::factory() in Laravel 8+.
     */
    public static function factory(int $count = null, array $state = [])
    {
        $factory = static::new();

        if ($count !== null) {
            $factory = $factory->count($count);
        }

        if (!empty($state)) {
            $factory = $factory->state($state);
        }

        return $factory;
    }

    protected function initializeCodeSequence(): void
    {
        if (self::$codeInitialized) {
            return;
        }

        $latestCode = Customer::query()->latest('id')->value('customer_code');
        if (is_string($latestCode) && str_starts_with($latestCode, 'CUS')) {
            $numericPart = (int) preg_replace('/[^0-9]/', '', $latestCode);
            if ($numericPart >= self::$codeSequence) {
                self::$codeSequence = $numericPart + 1;
            }
        }

        self::$codeInitialized = true;
    }

    protected function nextCustomerCode(): string
    {
        $this->initializeCodeSequence();

        return 'CUS' . str_pad((string) self::$codeSequence++, 6, '0', STR_PAD_LEFT);
    }

    protected function categoryRows(): array
    {
        if (is_array(self::$categoryRows)) {
            return self::$categoryRows;
        }

        $rows = CustomerCategory::query()
            ->select(['id', 'category_name'])
            ->orderBy('id')
            ->get()
            ->map(static fn (CustomerCategory $category) => [
                'id' => $category->id,
                'name' => $category->category_name,
            ])
            ->values()
            ->all();

        if (empty($rows)) {
            $fallback = CustomerCategory::query()->firstOrCreate(
                ['category_name' => 'Walk-in Customer'],
                [
                    'label_color' => '#64748B',
                    'description' => 'Default walk-in customers with minimal profile details.',
                ]
            );

            $rows = [
                [
                    'id' => $fallback->id,
                    'name' => $fallback->category_name,
                ],
            ];
        }

        self::$categoryRows = $rows;

        return self::$categoryRows;
    }

    protected function resolveCategoryForIndex(int $index): array
    {
        $categoryRows = $this->categoryRows();
        $preferredOrder = [
            'Retail Customer',
            'Wholesale Distributor',
            'VIP Customer',
            'Corporate Client',
            'Walk-in Customer',
            'Online Customer',
            'Regular Customer',
            'High Volume Buyer',
            'Small Business',
            'Government Account',
        ];

        $normalizedByName = [];
        foreach ($categoryRows as $row) {
            $normalizedByName[Str::lower($row['name'])] = $row;
        }

        $ordered = [];
        foreach ($preferredOrder as $name) {
            $match = $normalizedByName[Str::lower($name)] ?? null;
            if ($match !== null) {
                $ordered[] = $match;
            }
        }

        foreach ($categoryRows as $row) {
            $key = Str::lower($row['name']);
            $alreadyIncluded = collect($ordered)->contains(static fn (array $item) => Str::lower($item['name']) === $key);
            if (!$alreadyIncluded) {
                $ordered[] = $row;
            }
        }

        $selected = $ordered[($index - 1) % count($ordered)];

        return [$selected['id'], $selected['name']];
    }

    protected function buildAddress(int $index): string
    {
        $streets = ['St. 51', 'St. 63', 'St. 245', 'St. 271', 'St. 310', 'National Road 1', 'National Road 2'];
        $areas = [
            'Boeung Keng Kang I, Chamkar Mon, Phnom Penh',
            'Tuol Kork, Phnom Penh',
            'Sen Sok, Phnom Penh',
            'Chbar Ampov, Phnom Penh',
            'Svay Dangkum, Siem Reap',
            'Mittapheap, Sihanoukville',
            'Battambang City, Battambang',
        ];

        $buildingNo = 10 + (($index * 17) % 980);
        $street = $streets[$index % count($streets)];
        $area = $areas[$index % count($areas)];

        return 'No. ' . $buildingNo . ', ' . $street . ', ' . $area;
    }

    protected function buildCustomerName(int $index, string $categoryName): string
    {
        $personNames = [
            'John Lim',
            'Srey Neang',
            'Chan Vuthy',
            'Ratha Sok',
            'Nita Khem',
            'Dara Heng',
            'Pisey Chhun',
            'Mony Roth',
            'Sokun Thea',
            'Vicheka Kim',
        ];

        $businessNames = [
            'Sokha Trading Co., Ltd',
            'Chan Vuthy Store',
            'Mekong Wholesale Hub',
            'Borei Supply Center',
            'Golden Rice Retail',
            'Angkor Distribution Group',
            'Tonle Sap Enterprise',
            'Phnom Penh Mart',
        ];

        $category = Str::lower($categoryName);
        $isBusinessCategory =
            str_contains($category, 'wholesale')
            || str_contains($category, 'corporate')
            || str_contains($category, 'business')
            || str_contains($category, 'government')
            || str_contains($category, 'high volume');

        if ($isBusinessCategory) {
            return $businessNames[$index % count($businessNames)] . ' #' . (100 + $index);
        }

        return $personNames[$index % count($personNames)] . ' ' . chr(65 + ($index % 26));
    }

    protected function buildPhone(int $index): string
    {
        $prefixes = ['+85510', '+85511', '+85512', '+85515', '+85516', '+85517', '+85561', '+85569', '+85570', '+85577', '+85578', '+85588', '+85589', '+85595', '+85596', '+85597'];
        $prefix = $prefixes[$index % count($prefixes)];
        $line = str_pad((string) (100000 + (($index * 73) % 900000)), 6, '0', STR_PAD_LEFT);

        return $prefix . $line;
    }

    protected function buildEmail(string $fullName, string $categoryName, int $index): ?string
    {
        $category = Str::lower($categoryName);
        $shouldHaveEmail =
            str_contains($category, 'corporate')
            || str_contains($category, 'online')
            || str_contains($category, 'wholesale')
            || str_contains($category, 'government')
            || str_contains($category, 'vip')
            || ($index % 3 === 0);

        if (!$shouldHaveEmail) {
            return null;
        }

        $slug = Str::of($fullName)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '.')
            ->trim('.')
            ->value();

        $domains = ['gmail.com', 'outlook.com', 'khbiz.com', 'retailhub.com', 'supplypro.co'];

        $email = $slug . $index . '@' . $domains[$index % count($domains)];

        // Ensure email fits DB column (50 chars). If too long, fall back to null.
        return strlen($email) <= 50 ? $email : null;
    }

    protected function buildSocialMedia(string $fullName, int $index): string
    {
        $handle = Str::of($fullName)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '')
            ->limit(18, '')
            ->value();

        $channelType = $index % 3;
        if ($channelType === 0) {
            return 'https://facebook.com/' . $handle;
        }

        if ($channelType === 1) {
            return 'https://t.me/' . $handle;
        }

        return 'https://wa.me/' . (85510000000 + ($index * 37));
    }

    protected function weightedStatus(int $index): string
    {
        $bucket = $index % 10;

        if ($bucket >= 1 && $bucket <= 7) {
            return CustomerStatusEnum::ACTIVE->value;
        }

        if ($bucket >= 8 && $bucket <= 9) {
            return CustomerStatusEnum::INACTIVE->value;
        }

        return CustomerStatusEnum::BLACKLISTED->value;
    }

    public function definition()
    {
        $index = self::$recordSequence++;
        [$categoryId, $categoryName] = $this->resolveCategoryForIndex($index);
        $fullname = $this->buildCustomerName($index, $categoryName);
        $address = $this->buildAddress($index);

        return [
            'image' => 'https://api.dicebear.com/9.x/adventurer/svg?seed=' . rawurlencode(Str::lower($fullname)),
            'fullname' => $fullname,
            'customer_code' => $this->nextCustomerCode(),
            'phone_number' => $this->buildPhone($index),
            'email_address' => $this->buildEmail($fullname, $categoryName, $index),
            'social_media' => $this->buildSocialMedia($fullname, $index),
            'customer_address' => $address,
            'google_map_link' => Str::limit('https://maps.google.com/?q=' . rawurlencode($address), 80),
            'customer_status' => $this->weightedStatus($index),
            'customer_category_id' => $categoryId,
            'customer_note' => 'Segment: ' . $categoryName . '. Preferred channel: POS walk-in and social ordering.',
            'extra_data' => [
                'seed_profile' => Str::snake(Str::lower($categoryName)),
                'seed_version' => '2026.04',
            ],
        ];
    }
}
