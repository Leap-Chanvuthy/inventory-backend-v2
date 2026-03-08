<?php

namespace Tests\Feature;

use App\Models\UnitOfMeasurement;
use App\Models\UomCategory;
use App\Validations\UOMValidation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * UomCategoryTest
 *
 * Tests UOM category management and the validation rules enforced
 * by UOMValidation, particularly:
 *
 *  1. Creating a base unit succeeds.
 *  2. Attempting to create a second base unit in the same category fails.
 *  3. Non-base unit requires a valid base_uom_id.
 *  4. Non-base unit base_uom_id must belong to the same category.
 *  5. base_uom_id must point to an actual base unit (is_base_unit = true).
 *  6. conversion_factor must be > 0.
 *  7. Category name uniqueness.
 */
class UomCategoryTest extends TestCase
{
    use RefreshDatabase;

    private UomCategory $quantityCategory;
    private UomCategory $weightCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->quantityCategory = UomCategory::create(['name' => 'Quantity']);
        $this->weightCategory   = UomCategory::create(['name' => 'Weight']);
    }

    // =========================================================================
    // UomCategory model
    // =========================================================================

    /** @test */
    public function it_creates_a_uom_category(): void
    {
        $category = UomCategory::create([
            'name'        => 'Volume',
            'description' => 'Liquid measurements',
        ]);

        $this->assertDatabaseHas('uom_categories', [
            'name' => 'Volume',
        ]);

        $this->assertEquals('Volume', $category->name);
    }

    /** @test */
    public function it_enforces_unique_category_names(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        UomCategory::create(['name' => 'Quantity']); // duplicate
    }

    // =========================================================================
    // Base unit creation
    // =========================================================================

    /** @test */
    public function it_creates_the_base_unit_for_a_category(): void
    {
        $baseUnit = UnitOfMeasurement::create([
            'uom_code'          => 'UOM00001',
            'name'              => 'Gram',
            'symbol'            => 'g',
            'category_id'       => $this->weightCategory->id,
            'base_uom_id'       => null,
            'conversion_factor' => 1.000000,
            'is_base_unit'      => true,
            'is_active'         => true,
        ]);

        $this->assertDatabaseHas('unit_of_measurements', [
            'name'         => 'Gram',
            'is_base_unit' => true,
            'category_id'  => $this->weightCategory->id,
        ]);

        $this->assertTrue((bool) $baseUnit->is_base_unit);
        $this->assertNull($baseUnit->base_uom_id);
        $this->assertEquals('1.000000', $baseUnit->conversion_factor);
    }

    /** @test */
    public function it_prevents_multiple_base_units_per_category_via_validation(): void
    {
        // Create the first (valid) base unit
        UnitOfMeasurement::create([
            'uom_code'          => 'UOM00001',
            'name'              => 'Very Extra Small Pack',
            'symbol'            => 'VESP',
            'category_id'       => $this->quantityCategory->id,
            'base_uom_id'       => null,
            'conversion_factor' => 1.000000,
            'is_base_unit'      => true,
            'is_active'         => true,
        ]);

        // Attempt to create a second base unit via UOMValidation
        $validation = app(UOMValidation::class);
        $request    = new \Illuminate\Http\Request();
        $request->replace([
            'uom_code'          => 'UOM00002',
            'name'              => 'Another Base',
            'category_id'       => $this->quantityCategory->id,
            'conversion_factor' => 1.0,
            'is_base_unit'      => true,
        ]);

        $this->expectException(ValidationException::class);

        $validation->CreateValidationFields($request);
    }

    // =========================================================================
    // Non-base unit rules
    // =========================================================================

    /** @test */
    public function it_creates_a_non_base_unit_referencing_the_base(): void
    {
        $baseUnit = UnitOfMeasurement::create([
            'uom_code'          => 'UOM00001',
            'name'              => 'Very Extra Small Pack',
            'symbol'            => 'VESP',
            'category_id'       => $this->quantityCategory->id,
            'base_uom_id'       => null,
            'conversion_factor' => 1.000000,
            'is_base_unit'      => true,
            'is_active'         => true,
        ]);

        $bigPack = UnitOfMeasurement::create([
            'uom_code'          => 'UOM00002',
            'name'              => 'Big Pack',
            'symbol'            => 'BP',
            'category_id'       => $this->quantityCategory->id,
            'base_uom_id'       => $baseUnit->id,
            'conversion_factor' => 1000.000000,
            'is_base_unit'      => false,
            'is_active'         => true,
        ]);

        $this->assertDatabaseHas('unit_of_measurements', [
            'name'              => 'Big Pack',
            'base_uom_id'       => $baseUnit->id,
            'conversion_factor' => 1000.000000,
        ]);
    }

    /** @test */
    public function it_rejects_non_base_unit_with_base_uom_from_different_category(): void
    {
        // Base unit in Weight
        $gramBase = UnitOfMeasurement::create([
            'uom_code'          => 'UOM00001',
            'name'              => 'Gram',
            'symbol'            => 'g',
            'category_id'       => $this->weightCategory->id,
            'base_uom_id'       => null,
            'conversion_factor' => 1.000000,
            'is_base_unit'      => true,
            'is_active'         => true,
        ]);

        // Attempting to create a Quantity unit referencing Weight's base
        $validation = app(UOMValidation::class);
        $request    = new \Illuminate\Http\Request();
        $request->replace([
            'uom_code'          => 'UOM00002',
            'name'              => 'Big Pack',
            'category_id'       => $this->quantityCategory->id, // Quantity
            'base_uom_id'       => $gramBase->id,              // Wrong category
            'conversion_factor' => 1000.0,
            'is_base_unit'      => false,
        ]);

        $this->expectException(ValidationException::class);

        $validation->CreateValidationFields($request);
    }

    /** @test */
    public function it_rejects_non_base_unit_pointing_to_a_non_base_uom(): void
    {
        // VESP — base
        $base = UnitOfMeasurement::create([
            'uom_code'          => 'UOM00001',
            'name'              => 'Very Extra Small Pack',
            'symbol'            => 'VESP',
            'category_id'       => $this->quantityCategory->id,
            'base_uom_id'       => null,
            'conversion_factor' => 1.000000,
            'is_base_unit'      => true,
            'is_active'         => true,
        ]);

        // Small Pack (non-base, points to VESP correctly)
        $sp = UnitOfMeasurement::create([
            'uom_code'          => 'UOM00002',
            'name'              => 'Small Pack',
            'symbol'            => 'SP',
            'category_id'       => $this->quantityCategory->id,
            'base_uom_id'       => $base->id,
            'conversion_factor' => 100.000000,
            'is_base_unit'      => false,
            'is_active'         => true,
        ]);

        // Big Pack pointing to Small Pack (not the base) — must fail via validation
        $validation = app(UOMValidation::class);
        $request    = new \Illuminate\Http\Request();
        $request->replace([
            'uom_code'          => 'UOM00003',
            'name'              => 'Big Pack',
            'category_id'       => $this->quantityCategory->id,
            'base_uom_id'       => $sp->id, // SP is NOT the base unit
            'conversion_factor' => 1000.0,
            'is_base_unit'      => false,
        ]);

        $this->expectException(ValidationException::class);

        $validation->CreateValidationFields($request);
    }

    // =========================================================================
    // Category → UOM relationships
    // =========================================================================

    /** @test */
    public function category_has_many_unit_of_measurements(): void
    {
        $base = UnitOfMeasurement::create([
            'uom_code'          => 'UOM00001',
            'name'              => 'Very Extra Small Pack',
            'symbol'            => 'VESP',
            'category_id'       => $this->quantityCategory->id,
            'base_uom_id'       => null,
            'conversion_factor' => 1.000000,
            'is_base_unit'      => true,
            'is_active'         => true,
        ]);

        UnitOfMeasurement::create([
            'uom_code'          => 'UOM00002',
            'name'              => 'Big Pack',
            'symbol'            => 'BP',
            'category_id'       => $this->quantityCategory->id,
            'base_uom_id'       => $base->id,
            'conversion_factor' => 1000.000000,
            'is_base_unit'      => false,
            'is_active'         => true,
        ]);

        $this->assertCount(2, $this->quantityCategory->unitOfMeasurements);
    }

    /** @test */
    public function uom_belongs_to_its_category(): void
    {
        $base = UnitOfMeasurement::create([
            'uom_code'          => 'UOM00001',
            'name'              => 'Gram',
            'symbol'            => 'g',
            'category_id'       => $this->weightCategory->id,
            'base_uom_id'       => null,
            'conversion_factor' => 1.000000,
            'is_base_unit'      => true,
            'is_active'         => true,
        ]);

        $this->assertEquals('Weight', $base->category->name);
    }

    /** @test */
    public function base_uom_relationship_is_null_for_base_unit(): void
    {
        $base = UnitOfMeasurement::create([
            'uom_code'          => 'UOM00001',
            'name'              => 'Gram',
            'symbol'            => 'g',
            'category_id'       => $this->weightCategory->id,
            'base_uom_id'       => null,
            'conversion_factor' => 1.000000,
            'is_base_unit'      => true,
            'is_active'         => true,
        ]);

        $this->assertNull($base->baseUom);
    }

    /** @test */
    public function non_base_uom_has_correct_base_uom_relationship(): void
    {
        $base = UnitOfMeasurement::create([
            'uom_code'          => 'UOM00001',
            'name'              => 'Very Extra Small Pack',
            'category_id'       => $this->quantityCategory->id,
            'base_uom_id'       => null,
            'conversion_factor' => 1.000000,
            'is_base_unit'      => true,
            'is_active'         => true,
        ]);

        $bp = UnitOfMeasurement::create([
            'uom_code'          => 'UOM00002',
            'name'              => 'Big Pack',
            'category_id'       => $this->quantityCategory->id,
            'base_uom_id'       => $base->id,
            'conversion_factor' => 1000.000000,
            'is_base_unit'      => false,
            'is_active'         => true,
        ]);

        $this->assertEquals('Very Extra Small Pack', $bp->baseUom->name);
    }

    /** @test */
    public function base_unit_has_children_relationship(): void
    {
        $base = UnitOfMeasurement::create([
            'uom_code'          => 'UOM00001',
            'name'              => 'Very Extra Small Pack',
            'category_id'       => $this->quantityCategory->id,
            'base_uom_id'       => null,
            'conversion_factor' => 1.000000,
            'is_base_unit'      => true,
            'is_active'         => true,
        ]);

        foreach ([
            ['uom_code' => 'UOM00002', 'name' => 'Extra Small Pack', 'conversion_factor' => 10],
            ['uom_code' => 'UOM00003', 'name' => 'Small Pack',       'conversion_factor' => 100],
            ['uom_code' => 'UOM00004', 'name' => 'Big Pack',         'conversion_factor' => 1000],
        ] as $child) {
            UnitOfMeasurement::create(array_merge($child, [
                'category_id'  => $this->quantityCategory->id,
                'base_uom_id'  => $base->id,
                'is_base_unit' => false,
                'is_active'    => true,
            ]));
        }

        $this->assertCount(3, $base->children);
    }

    // =========================================================================
    // isSameCategoryAs helper
    // =========================================================================

    /** @test */
    public function is_same_category_as_returns_true_within_same_category(): void
    {
        $base = UnitOfMeasurement::create([
            'uom_code'          => 'UOM00001',
            'name'              => 'Very Extra Small Pack',
            'category_id'       => $this->quantityCategory->id,
            'base_uom_id'       => null,
            'conversion_factor' => 1.000000,
            'is_base_unit'      => true,
            'is_active'         => true,
        ]);

        $bp = UnitOfMeasurement::create([
            'uom_code'          => 'UOM00002',
            'name'              => 'Big Pack',
            'category_id'       => $this->quantityCategory->id,
            'base_uom_id'       => $base->id,
            'conversion_factor' => 1000.000000,
            'is_base_unit'      => false,
            'is_active'         => true,
        ]);

        $this->assertTrue($base->isSameCategoryAs($bp));
    }

    /** @test */
    public function is_same_category_as_returns_false_across_categories(): void
    {
        $base = UnitOfMeasurement::create([
            'uom_code'          => 'UOM00001',
            'name'              => 'Very Extra Small Pack',
            'category_id'       => $this->quantityCategory->id,
            'base_uom_id'       => null,
            'conversion_factor' => 1.000000,
            'is_base_unit'      => true,
            'is_active'         => true,
        ]);

        $gram = UnitOfMeasurement::create([
            'uom_code'          => 'UOM00002',
            'name'              => 'Gram',
            'category_id'       => $this->weightCategory->id,
            'base_uom_id'       => null,
            'conversion_factor' => 1.000000,
            'is_base_unit'      => true,
            'is_active'         => true,
        ]);

        $this->assertFalse($base->isSameCategoryAs($gram));
    }
}
