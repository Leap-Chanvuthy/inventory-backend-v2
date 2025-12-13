<?php

namespace Database\Factories;

use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductCategoryFactory extends Factory
{
    protected $model = ProductCategory::class;

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
            'Leather'
        ];

        static $i = 0;

        return [
            'category_name' => $randomCategoryName[$i++ % count($randomCategoryName)],
            'label_color' => fake()->hexColor(),
            'description' => fake()->sentence(),
        ];
    }
}