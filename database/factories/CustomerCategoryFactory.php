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

    public function definition()
    {
        $randomCategoryName = [
            'Metals',
            'Plastics',
            'Chemicals',
            'Textiles',
            'Wood',
            'Glass',
            'Ceramics',
            'Rubber',
            'Composites',
            'Paper Products',
            'Electronics Components',
            'Food Ingredients',
            'Pharmaceuticals',
            'Building Materials',
            'Adhesives',
            'Paints and Coatings',
            'Fabrics',
            'Foams',
            'Fibers',
            'Leather',
            'Company',
            'Services',
        ];

        static $i = 0;

        return [
            'category_name' => $randomCategoryName[$i++ % count($randomCategoryName)],
            'label_color' => fake()->hexColor(),
            'description' => fake()->sentence(),
        ];
    }
}
