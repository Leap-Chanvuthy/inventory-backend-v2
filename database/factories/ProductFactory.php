<?php

namespace Database\Factories;

use App\Enums\ProductTypeEnum;
use App\Enums\SaleMethodEnum;
use App\Helpers\GenerateUniqueSKU;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UOM;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition()
    {
        $faker = $this->faker;

        $category  = ProductCategory::inRandomOrder()->first();
        $baseUom   = UOM::where('is_base_unit', true)->inRandomOrder()->first() ?? UOM::inRandomOrder()->first();
        $supplier  = Supplier::inRandomOrder()->first();
        $warehouse = Warehouse::inRandomOrder()->first();

        // Build SKU using same helper as seeder/service
        $product = new Product();
        if ($category) {
            $product->category()->associate($category);
        }

        $sku = GenerateUniqueSKU::generate(
            model:        $product,
            field:        'product_sku_code',
            randomLength: 6,
            prefix:       'PRD',
            relations:    ['cat' => 'category.category_name'],
            format:       '{prefix}-{cat}-{random}',
        );

        $productNames = [
            'ម៉ៅស៍ឥតខ្សែ Logitech M221',
            'កាស Bluetooth Sony WH-CH520',
            'ឆ្នាំងសាក USB-C 65W',
            'ក្តារចុចមេកានិច RGB',
            'ម៉ូនីទ័រ 27 អ៊ីញ 4K',
            'ថាសរឹងក្រៅ 1TB',
            'ជើងទូរស័ព្ទលើតុ',
            'ស្រោម Laptop 15.6 អ៊ីញ',
            'Speaker ចល័ត Bluetooth',
            'នាឡិកាឆ្លាតវៃ Smart Watch',
            'Earbuds បំបាត់សំឡេងរំខាន',
            'Webcam Full HD មានមីក្រូហ្វូន',
            'Router Wi-Fi 6',
            'Power Bank 20000mAh',
            'Smart Home Hub',
            'កាមេរ៉ា Action Cam',
            'ឧបករណ៍អានអេឡិចត្រូនិច',
            'VR Headset',
            'ក្តារចុច Gaming',
            'កៅអី Gaming',
            'អំពូល LED លើតុ',
            'Bluetooth Adapter 5.0',
            'SSD ចល័ត 512GB',
            'USB Hub 7 Ports',
            'Cooling Pad សម្រាប់ Laptop',
            'Smart Thermostat',
            'ម៉ាស៊ីនស្កេន Barcode',
            'ម៉ាស៊ីនបោះពុម្ព Label',
            'ម៉ាស៊ីនគិតលេខការិយាល័យ',
            'ម៉ាស៊ីនតេស្តថាមពល DC',
        ];

        return [
            'product_name'        => $faker->randomElement($productNames),
            'product_sku_code'    => $sku,
            'barcode'             => $faker->optional(0.6)->ean13(),
            'product_description' => $faker->optional(0.7)->randomElement([
                'ផលិតផលគុណភាពល្អ សម្រាប់ប្រើប្រាស់ប្រចាំថ្ងៃ និងអាជីវកម្ម',
                'សមស្របសម្រាប់លក់រាយ និងលក់ដុំ មានធានាគុណភាព',
                'មុខទំនិញពេញនិយមក្នុងស្តុកឃ្លាំង និងហាងលក់',
            ]),
            'product_category_id' => $category?->id,
            'base_uom_id'         => $baseUom?->id,
            'supplier_id'         => $supplier?->id,
            'warehouse_id'        => $warehouse?->id,
            'product_type'        => ProductTypeEnum::EXTERNAL_PURCHASED->value,
            'sale_method'         => $faker->randomElement([
                SaleMethodEnum::FIFO->value,
                SaleMethodEnum::LIFO->value,
            ]),
        ];
    }

    public function external()
    {
        return $this->state(fn (array $attributes) => [
            'product_type' => ProductTypeEnum::EXTERNAL_PURCHASED->value,
        ]);
    }

    public function internal()
    {
        return $this->state(fn (array $attributes) => [
            'product_type' => ProductTypeEnum::INTERNAL_PRODUCED->value,
            'supplier_id'  => null,
        ]);
    }
}
