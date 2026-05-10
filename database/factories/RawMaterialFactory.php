<?php

namespace Database\Factories;
use App\Helpers\GenerateUniqueSKU;
use App\Models\RawMaterial;
use App\Models\RawMaterialCategory;
use App\Models\Supplier;
use App\Models\UOM;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RawMaterial>
 */
class RawMaterialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {

        $rawMaterials = [
            'ដែកសន្លឹកគុណភាពខ្ពស់',
            'ដែកដុំមូលសំណង់',
            'អាលុយមីញ៉ូមសន្លឹក',
            'ខ្សែស្ពាន់សម្រាប់ភ្លើង',
            'ដែកអ៊ីណុកបំពង់',
            'ជ័រ PP សម្រាប់ចាក់ម៉ូល',
            'ជ័រ PE ថ្នាក់ឧស្សាហកម្ម',
            'ជ័រ PVC សម្រាប់បំពង់',
            'គ្រាប់ ABS សម្រាប់ផលិតផ្នែកប្លាស្ទិក',
            'កៅស៊ូធម្មជាតិ',
            'កៅស៊ូស៊ីលីកូន',
            'កញ្ចក់សន្លឹកតឹង',
            'ម្សៅសេរ៉ាមិច',
            'ស៊ីម៉ង់ត៍ខ្នាតរោងចក្រ',
            'ខ្សាច់សំណង់',
            'ថ្មកំទេច',
            'ឈើបន្ទះសម្រាប់គ្រឿងសង្ហារឹម',
            'បន្ទះ Plywood',
            'បន្ទះ MDF',
            'បន្ទះក្រដាសកាតុង',
            'ទឹកថ្នាំបោះពុម្ព',
            'ថ្នាំលាបឧស្សាហកម្ម',
            'សារធាតុរលាយ Solvent',
            'ប្រេងរំអិលម៉ាស៊ីន',
            'ប្រេងហ៊ីដ្រូលិច',
            'កាវបិទឧស្សាហកម្ម',
            'ជ័រ Epoxy',
            'សារធាតុ Hardener',
            'សន្លឹកប្លាស្ទិកវេចខ្ចប់',
            'បន្ទះ Foam អ៊ីសូឡង់',
            'ខ្សែអ៊ីសូឡង់អគ្គិសនី',
            'បន្ទះ PCB',
            'រេស៊ីស្ទ័រ (Resistor)',
            'កាប៉ាស៊ីទ័រ (Capacitor)',
            'ត្រានស៊ីស្ទ័រ (Transistor)',
            'IC សៀគ្វីរួម',
            'គ្រឿងផ្សំ LED',
            'ខ្សែភ្លើងស្ពាន់',
            'ខ្សែថាមពល Power Cable',
            'សែលថ្ម Lithium',
            'ខ្សែរុំម៉ូទ័រ',
            'ប៊ូលុងដែក',
            'គ្រាប់ណាត់ដែក',
            'រង្វាស់ Washer',
            'កង់ហ្គៀ Gear',
            'ខ្សែសង្វាក់',
            'ខ្សែខ្សោង Belt',
            'សៀលបិទជិត',
            'តម្រងឧស្សាហកម្ម',
            'សន្ទះ Valve',
            'បូមទឹកឧស្សាហកម្ម',
            'ដំបងផ្សារ',
            'ខ្សែ Solder',
            'សារធាតុលាងសម្អាតឧស្សាហកម្ម',
            'សារធាតុកាត់ច្រេះ',
        ];


    // Do not use Faker unique() here; the pool can be exhausted depending on seed count.
    $name = $this->faker->randomElement($rawMaterials);

        // Prefer existing related records; fall back to creating via factories (more reliable than "?? 1").
        $category = RawMaterialCategory::query()->inRandomOrder()->first() ?? RawMaterialCategory::factory()->create();
        $uom = UOM::query()->inRandomOrder()->first() ?? UOM::factory()->create();
        $supplier = Supplier::query()->inRandomOrder()->first() ?? Supplier::factory()->create();
        $warehouse = Warehouse::query()->inRandomOrder()->first() ?? Warehouse::factory()->create();

        $rm = new RawMaterial();
        $rm->rm_category()->associate($category);
        $rm->baseUom()->associate($uom);

        $sku = GenerateUniqueSKU::generate(
            model: $rm,
            field: 'material_sku_code',
            randomLength: 6,
            prefix: 'RM',
            relations: [
                'cat' => 'rm_category.category_name',
                'uom' => 'baseUom.name',
            ],
            format: '{prefix}-{cat}-{uom}-{random}'
        );

        // prod method
        $method = ['FIFO', 'LIFO'];
        $productionMethod = $this->faker->randomElement($method);


        return [
            'material_name' => $name,
            'material_sku_code' => $sku,
            'barcode' => $this->faker->optional()->ean13(),
            'minimum_stock_level' => $this->faker->numberBetween(10, 100),
            'description' => $this->faker->optional()->randomElement([
                'វត្ថុធាតុដើមសម្រាប់ការផលិតប្រចាំថ្ងៃ',
                'ប្រើសម្រាប់ខ្សែផលិតកម្ម និងស្តុកឃ្លាំង',
                'មានគុណភាពសមស្របសម្រាប់ការប្រើប្រាស់ឧស្សាហកម្ម',
            ]),
            'raw_material_category_id' => $category->id,
            'production_method' => $productionMethod,
            'base_uom_id' => $uom->id,
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
        ];
    }
}
