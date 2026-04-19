<?php

namespace Database\Factories;

use App\Models\CustomerCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CustomerCategory>
 */
class CustomerCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = CustomerCategory::class;

    protected static int $categorySequence = 0;
    protected static int $colorSequence = 0;

    public function definition()
    {
        $categories = [
            [
                'name' => 'Retail Customer',
                'description' => 'Individual and household shoppers with frequent low-ticket POS purchases.',
                'colors' => ['#2563EB', '#1D4ED8', '#3B82F6'],
            ],
            [
                'name' => 'Wholesale Distributor',
                'description' => 'Bulk buyers who reorder stock in larger quantities with negotiated pricing.',
                'colors' => ['#15803D', '#16A34A', '#22C55E'],
            ],
            [
                'name' => 'VIP Customer',
                'description' => 'Top-value customers prioritized for loyalty rewards and preferred service.',
                'colors' => ['#7E22CE', '#9333EA', '#D4AF37'],
            ],
            [
                'name' => 'Corporate Client',
                'description' => 'Registered companies with formal invoicing and recurring account orders.',
                'colors' => ['#0F766E', '#0D9488', '#14B8A6'],
            ],
            [
                'name' => 'Walk-in Customer',
                'description' => 'Over-the-counter cash customers with minimal profile information.',
                'colors' => ['#64748B', '#475569', '#94A3B8'],
            ],
            [
                'name' => 'Online Customer',
                'description' => 'Customers purchasing through social commerce and digital ordering channels.',
                'colors' => ['#0EA5E9', '#0284C7', '#0369A1'],
            ],
            [
                'name' => 'Regular Customer',
                'description' => 'Returning neighborhood customers with stable purchase behavior.',
                'colors' => ['#4F46E5', '#6366F1', '#4338CA'],
            ],
            [
                'name' => 'High Volume Buyer',
                'description' => 'Operational buyers placing frequent large-quantity replenishment orders.',
                'colors' => ['#166534', '#15803D', '#14532D'],
            ],
            [
                'name' => 'Small Business',
                'description' => 'Local SMEs buying mixed inventory with moderate credit demand.',
                'colors' => ['#0891B2', '#0E7490', '#06B6D4'],
            ],
            [
                'name' => 'Government Account',
                'description' => 'Public-sector entities requiring compliance-oriented documentation.',
                'colors' => ['#334155', '#1E293B', '#475569'],
            ],
        ];

        $selectedCategory = $categories[self::$categorySequence++ % count($categories)];
        $selectedColor = $selectedCategory['colors'][self::$colorSequence++ % count($selectedCategory['colors'])];

        return [
            'category_name' => $selectedCategory['name'],
            'label_color' => $selectedColor,
            'description' => $selectedCategory['description'],
        ];
    }
}
