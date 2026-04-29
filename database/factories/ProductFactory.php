<?php

namespace Database\Factories;

use App\Enums\ProductTypeEnum;
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

        // suggest me  30 products name
        $productNames = [
            'Wireless Mouse',
            'Bluetooth Headphones',
            'USB-C Charger',
            'Gaming Keyboard',
            '4K Monitor',
            'External Hard Drive',
            'Smartphone Stand',
            'Laptop Sleeve',
            'Portable Speaker',
            'Fitness Tracker',
            'Noise-Cancelling Earbuds',
            'Webcam with Microphone',
            'Wireless Router',
            'Power Bank',
            'Smart Home Hub',
            'Action Camera',
            'E-Reader',
            'VR Headset',
            'Mechanical Keyboard',
            'Gaming Chair',
            'LED Desk Lamp',
            'Smartwatch',
            'Bluetooth Adapter',
            'Portable SSD',
            'Wireless Earbuds Case',
            'USB Hub',
            'Laptop Cooling Pad',
            'Smart Thermostat',
        ];

        return [
            'product_name'        => $faker->randomElement($productNames),
            'product_sku_code'    => $sku,
            'barcode'             => $faker->optional(0.6)->ean13(),
            'product_description' => $faker->optional(0.5)->sentence(),
            'product_category_id' => $category?->id,
            'base_uom_id'         => $baseUom?->id,
            'supplier_id'         => $supplier?->id,
            'warehouse_id'        => $warehouse?->id,
            'product_type'        => ProductTypeEnum::EXTERNAL_PURCHASED->value,
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
