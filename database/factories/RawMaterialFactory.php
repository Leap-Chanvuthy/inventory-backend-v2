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
            'Steel Sheet',
            'Steel Rod',
            'Aluminum Sheet',
            'Aluminum Bar',
            'Copper Wire',
            'Copper Sheet',
            'Brass Rod',
            'Stainless Steel Pipe',
            'Iron Ore',
            'Cast Iron',
            'Carbon Steel',
            'Galvanized Steel',
            'Plastic Resin (PP)',
            'Plastic Resin (PE)',
            'Plastic Resin (PVC)',
            'ABS Plastic Granules',
            'Nylon Granules',
            'Rubber Sheet',
            'Natural Rubber',
            'Silicone Rubber',
            'Glass Sheet',
            'Tempered Glass',
            'Ceramic Powder',
            'Cement',
            'Limestone',
            'Sand',
            'Gravel',
            'Clay',
            'Gypsum',
            'Fiberglass',
            'Carbon Fiber',
            'Wood Plank',
            'Plywood',
            'MDF Board',
            'Particle Board',
            'Bamboo',
            'Cotton Fiber',
            'Polyester Fiber',
            'Wool Fiber',
            'Leather Hide',
            'Paper Roll',
            'Cardboard Sheet',
            'Ink',
            'Industrial Paint',
            'Powder Coating Material',
            'Solvent',
            'Lubricating Oil',
            'Hydraulic Oil',
            'Grease',
            'Adhesive Glue',
            'Epoxy Resin',
            'Hardener',
            'Packaging Plastic Film',
            'Packaging Shrink Wrap',
            'Foam Sheet',
            'Thermal Insulation Material',
            'Electrical Insulation Tape',
            'Printed Circuit Board (PCB)',
            'Electronic Resistors',
            'Electronic Capacitors',
            'Electronic Transistors',
            'Integrated Circuits (IC)',
            'LED Components',
            'Electric Wire',
            'Power Cable',
            'Battery Cells',
            'Lithium Battery Pack',
            'Motor Coil',
            'Steel Fasteners',
            'Bolts',
            'Nuts',
            'Washers',
            'Bearings',
            'Gears',
            'Springs',
            'Chains',
            'Belts',
            'Seals',
            'O-Rings',
            'Filters',
            'Valves',
            'Pumps',
            'Lubrication Additives',
            'Cooling Fluid',
            'Welding Rods',
            'Solder Wire',
            'Flux',
            'Cleaning Chemicals',
            'Surface Treatment Chemicals',
            'Anti-Corrosion Coating',
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
        $rm->uom()->associate($uom);

        $sku = GenerateUniqueSKU::generate(
            model: $rm,
            field: 'material_sku_code',
            randomLength: 6,
            prefix: 'RM',
            relations: [
                'cat' => 'rm_category.category_name',
                'uom' => 'uom.name',
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
            'expiry_date' => $this->faker->dateTimeBetween('+1 month', '+2 years')->format('Y-m-d'),
            'description' => $this->faker->optional()->paragraph(),
            'raw_material_category_id' => $category->id,
            'production_method' => $productionMethod,
            'uom_id' => $uom->id,
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
        ];
    }
}