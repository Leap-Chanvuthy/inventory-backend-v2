<?php

namespace Database\Seeders;

use App\Models\CustomerCategory;
use Illuminate\Database\Seeder;

class CustomerCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();

        $categories = [
            [
                'category_name' => 'Retail Customer',
                'label_color' => '#2563EB',
                'description' => 'Individual and household shoppers with frequent low-ticket POS purchases.',
                'discount_percentage' => 2.50,
            ],
            [
                'category_name' => 'Wholesale Distributor',
                'label_color' => '#16A34A',
                'description' => 'Bulk buyers who reorder stock in larger quantities with negotiated pricing.',
                'discount_percentage' => 10.00,
            ],
            [
                'category_name' => 'VIP Customer',
                'label_color' => '#D4AF37',
                'description' => 'Top-value customers prioritized for loyalty rewards and preferred service.',
                'discount_percentage' => 12.50,
            ],
            [
                'category_name' => 'Corporate Client',
                'label_color' => '#0D9488',
                'description' => 'Registered companies with formal invoicing and recurring account orders.',
                'discount_percentage' => 7.50,
            ],
            [
                'category_name' => 'Walk-in Customer',
                'label_color' => '#64748B',
                'description' => 'Over-the-counter cash customers with minimal profile information.',
                'discount_percentage' => 0.00,
            ],
            [
                'category_name' => 'Online Customer',
                'label_color' => '#0284C7',
                'description' => 'Customers purchasing through social commerce and digital ordering channels.',
                'discount_percentage' => 3.00,
            ],
            [
                'category_name' => 'Regular Customer',
                'label_color' => '#4F46E5',
                'description' => 'Returning neighborhood customers with stable purchase behavior.',
                'discount_percentage' => 1.50,
            ],
            [
                'category_name' => 'High Volume Buyer',
                'label_color' => '#166534',
                'description' => 'Operational buyers placing frequent large-quantity replenishment orders.',
                'discount_percentage' => 8.00,
            ],
            [
                'category_name' => 'Small Business',
                'label_color' => '#0E7490',
                'description' => 'Local SMEs buying mixed inventory with predictable reorder cycles.',
                'discount_percentage' => 4.50,
            ],
            [
                'category_name' => 'Government Account',
                'label_color' => '#334155',
                'description' => 'Public-sector entities requiring compliance-oriented documentation.',
                'discount_percentage' => 6.00,
            ],
        ];

        $rows = array_map(static fn (array $item) => [
            ...$item,
            'created_at' => $now,
            'updated_at' => $now,
        ], $categories);

        CustomerCategory::query()->upsert(
            $rows,
            ['category_name'],
            ['label_color', 'description', 'discount_percentage', 'updated_at']
        );
    }
}
