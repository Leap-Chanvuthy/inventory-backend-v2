<?php

namespace Database\Factories;

use App\Models\RawMaterialCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class RawMaterialCategoryFactory extends Factory
{
    protected $model = RawMaterialCategory::class;

    public function definition()
    {
        $randomCategoryName = [
            'លោហៈ និង ដែក',
            'ប្លាស្ទិក និង ជ័រ',
            'គ្រឿងគីមីឧស្សាហកម្ម',
            'ក្រណាត់ និង សរសៃ',
            'ឈើ និង សម្ភារៈឈើ',
            'កញ្ចក់ និង សេរ៉ាមិច',
            'កៅស៊ូ និង ស៊ីលីកូន',
            'ក្រដាស និង វេចខ្ចប់',
            'គ្រឿងអេឡិចត្រូនិច',
            'សម្ភារៈសំណង់',
            'ថ្នាំលាប និង ថ្នាំកូត',
            'សារធាតុបិទភ្ជាប់',
            'ប្រេង និង សារធាតុរំអិល',
            'គ្រឿងបន្លាស់ម៉ាស៊ីន',
        ];

        static $i = 0;

        return [
            'category_name' => $randomCategoryName[$i++ % count($randomCategoryName)],
            'label_color' => fake()->hexColor(),
            'description' => fake()->randomElement([
                'ក្រុមវត្ថុធាតុដើមសម្រាប់ផលិតកម្មទូទៅ',
                'ប្រើសម្រាប់ផ្នែកផលិត និងដំណើរការរោងចក្រ',
                'សមស្របសម្រាប់ការផលិតផលិតផលប្រចាំថ្ងៃ',
            ]),
        ];
    }
}
