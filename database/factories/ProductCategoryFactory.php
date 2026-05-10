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
            'គ្រឿងអេឡិចត្រូនិច',
            'សម្ភារៈការិយាល័យ',
            'គ្រឿងប្រើប្រាស់ផ្ទះបាយ',
            'គ្រឿងសំណង់',
            'គ្រឿងម៉ាស៊ីន និង បន្លាស់',
            'សម្ភារៈវេចខ្ចប់',
            'គ្រឿងប្រើប្រាស់ឧស្សាហកម្ម',
            'សម្ភារៈអប់រំ',
            'ផលិតផលសុវត្ថិភាពការងារ',
            'ឧបករណ៍ថាមពល',
            'គ្រឿងតុបតែង និង អេឡិចត្រូនិចផ្ទះ',
            'សម្ភារៈកសិកម្ម',
        ];

        static $i = 0;

        return [
            'category_name' => $randomCategoryName[$i++ % count($randomCategoryName)],
            'label_color' => fake()->hexColor(),
            'description' => fake()->randomElement([
                'ប្រភេទផលិតផលសម្រាប់លក់ និងចែកចាយ',
                'ក្រុមផលិតផលប្រើប្រាស់ក្នុងអាជីវកម្ម',
                'សមស្របសម្រាប់ស្តុកក្នុងឃ្លាំងលក់រាយ និងលក់ដុំ',
            ]),
        ];
    }
}
