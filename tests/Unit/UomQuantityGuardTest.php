<?php

namespace Tests\Unit;

use App\Enums\UomQuantityTypeEnum;
use App\Helpers\UomQuantityGuard;
use App\Models\RawMaterial;
use App\Models\RawMaterialCategory;
use App\Models\Supplier;
use App\Models\UnitOfMeasurement;
use App\Models\UomCategory;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UomQuantityGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_integer_uom_rejects_decimal_quantity(): void
    {
        $category = UomCategory::create([
            'name' => 'Count',
            'quantity_type' => UomQuantityTypeEnum::INTEGER->value,
        ]);
        $piece = UnitOfMeasurement::create([
            'uom_code' => 'UOM10001',
            'name' => 'Piece',
            'symbol' => 'pc',
            'category_id' => $category->id,
            'base_uom_id' => null,
            'conversion_factor' => 1,
            'is_base_unit' => true,
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);

        UomQuantityGuard::assertQuantityByUomId(1.5, (int) $piece->id, 'quantity');
    }

    public function test_decimal_uom_accepts_decimal_quantity(): void
    {
        $category = UomCategory::create([
            'name' => 'Weight',
            'quantity_type' => UomQuantityTypeEnum::DECIMAL->value,
        ]);
        $gram = UnitOfMeasurement::create([
            'uom_code' => 'UOM10002',
            'name' => 'Gram',
            'symbol' => 'g',
            'category_id' => $category->id,
            'base_uom_id' => null,
            'conversion_factor' => 1,
            'is_base_unit' => true,
            'is_active' => true,
        ]);

        UomQuantityGuard::assertQuantityByUomId(1.75, (int) $gram->id, 'quantity');
        $this->assertTrue(true);
    }

    public function test_bom_quantity_respects_raw_material_uom_type(): void
    {
        $uomCategory = UomCategory::create([
            'name' => 'Count',
            'quantity_type' => UomQuantityTypeEnum::INTEGER->value,
        ]);
        $piece = UnitOfMeasurement::create([
            'uom_code' => 'UOM10003',
            'name' => 'Piece',
            'symbol' => 'pc',
            'category_id' => $uomCategory->id,
            'base_uom_id' => null,
            'conversion_factor' => 1,
            'is_base_unit' => true,
            'is_active' => true,
        ]);

        $rawCategory = RawMaterialCategory::factory()->create();
        $supplier = Supplier::factory()->create();
        $warehouse = Warehouse::factory()->create();

        $rawMaterial = RawMaterial::create([
            'material_name' => 'Panel',
            'material_sku_code' => 'RM-PANEL-001',
            'barcode' => null,
            'minimum_stock_level' => 0,
            'description' => null,
            'production_method' => 'FIFO',
            'raw_material_category_id' => $rawCategory->id,
            'base_uom_id' => $piece->id,
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
        ]);

        $this->expectException(ValidationException::class);

        UomQuantityGuard::assertBomQuantities([
            [
                'raw_material_id' => $rawMaterial->id,
                'quantity_per_unit' => 0.5,
                'scrap_percentage' => 0,
            ],
        ]);
    }
}
