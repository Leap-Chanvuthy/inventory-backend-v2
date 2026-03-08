<?php

namespace Tests\Feature;

use App\Exceptions\UomConversionException;
use App\Models\UnitOfMeasurement;
use App\Models\UomCategory;
use App\Service\UomConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UomConversionTest
 *
 * Tests the UomConversionService in isolation using an in-memory SQLite
 * (or the configured test connection) so every test is independent.
 *
 * Hierarchy used throughout this suite (Quantity category):
 *
 *   Very Extra Small Pack (VESP)  → is_base_unit=true,  conversion_factor=1
 *   Extra Small Pack       (ESP)  → is_base_unit=false, conversion_factor=10
 *   Small Pack             (SP)   → is_base_unit=false, conversion_factor=100
 *   Big Pack               (BP)   → is_base_unit=false, conversion_factor=1000
 *
 * A second category (Weight) is set up for cross-category guard tests.
 */
class UomConversionTest extends TestCase
{
    use RefreshDatabase;

    private UomConversionService $service;

    // Quantity category UOMs
    private UomCategory        $quantityCategory;
    private UnitOfMeasurement $baseUnit;   // VESP
    private UnitOfMeasurement $espUnit;    // ESP
    private UnitOfMeasurement $spUnit;     // SP
    private UnitOfMeasurement $bpUnit;     // BP

    // Weight category UOM
    private UomCategory        $weightCategory;
    private UnitOfMeasurement $gramUnit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(UomConversionService::class);

        // ── Quantity category ───────────────────────────────────────────────
        $this->quantityCategory = UomCategory::create([
            'name' => 'Quantity',
        ]);

        $this->baseUnit = UnitOfMeasurement::create([
            'uom_code'          => 'UOM00001',
            'name'              => 'Very Extra Small Pack',
            'symbol'            => 'VESP',
            'category_id'       => $this->quantityCategory->id,
            'base_uom_id'       => null,
            'conversion_factor' => 1.000000,
            'is_base_unit'      => true,
            'is_active'         => true,
        ]);

        $this->espUnit = UnitOfMeasurement::create([
            'uom_code'          => 'UOM00002',
            'name'              => 'Extra Small Pack',
            'symbol'            => 'ESP',
            'category_id'       => $this->quantityCategory->id,
            'base_uom_id'       => $this->baseUnit->id,
            'conversion_factor' => 10.000000,
            'is_base_unit'      => false,
            'is_active'         => true,
        ]);

        $this->spUnit = UnitOfMeasurement::create([
            'uom_code'          => 'UOM00003',
            'name'              => 'Small Pack',
            'symbol'            => 'SP',
            'category_id'       => $this->quantityCategory->id,
            'base_uom_id'       => $this->baseUnit->id,
            'conversion_factor' => 100.000000,
            'is_base_unit'      => false,
            'is_active'         => true,
        ]);

        $this->bpUnit = UnitOfMeasurement::create([
            'uom_code'          => 'UOM00004',
            'name'              => 'Big Pack',
            'symbol'            => 'BP',
            'category_id'       => $this->quantityCategory->id,
            'base_uom_id'       => $this->baseUnit->id,
            'conversion_factor' => 1000.000000,
            'is_base_unit'      => false,
            'is_active'         => true,
        ]);

        // ── Weight category ─────────────────────────────────────────────────
        $this->weightCategory = UomCategory::create([
            'name' => 'Weight',
        ]);

        $this->gramUnit = UnitOfMeasurement::create([
            'uom_code'          => 'UOM00005',
            'name'              => 'Gram',
            'symbol'            => 'g',
            'category_id'       => $this->weightCategory->id,
            'base_uom_id'       => null,
            'conversion_factor' => 1.000000,
            'is_base_unit'      => true,
            'is_active'         => true,
        ]);
    }

    // =========================================================================
    // convertToBase()
    // =========================================================================

    /** @test */
    public function it_converts_base_unit_to_base_returns_same_quantity(): void
    {
        $result = $this->service->convertToBase(50, $this->baseUnit->id);

        $this->assertEquals('50.0000000000', $result);
    }

    /** @test */
    public function it_converts_big_pack_to_base_unit(): void
    {
        // 5 Big Packs × 1000 = 5000 VESP
        $result = $this->service->convertToBase(5, $this->bpUnit->id);

        $this->assertEquals('5000.0000000000', $result);
    }

    /** @test */
    public function it_converts_small_pack_to_base_unit(): void
    {
        // 3 Small Packs × 100 = 300 VESP
        $result = $this->service->convertToBase(3, $this->spUnit->id);

        $this->assertEquals('300.0000000000', $result);
    }

    /** @test */
    public function it_converts_extra_small_pack_to_base_unit(): void
    {
        // 7 ESP × 10 = 70 VESP
        $result = $this->service->convertToBase(7, $this->espUnit->id);

        $this->assertEquals('70.0000000000', $result);
    }

    // =========================================================================
    // convertFromBase()
    // =========================================================================

    /** @test */
    public function it_converts_base_quantity_to_big_pack(): void
    {
        // 5000 VESP ÷ 1000 = 5 Big Packs
        $result = $this->service->convertFromBase(5000, $this->bpUnit->id);

        $this->assertEquals('5.0000000000', $result);
    }

    /** @test */
    public function it_converts_base_quantity_to_small_pack(): void
    {
        // 300 VESP ÷ 100 = 3 Small Packs
        $result = $this->service->convertFromBase(300, $this->spUnit->id);

        $this->assertEquals('3.0000000000', $result);
    }

    // =========================================================================
    // convert() — cross-UOM
    // =========================================================================

    /** @test */
    public function it_converts_big_pack_to_very_extra_small_pack(): void
    {
        // 1 BP → 1000 VESP
        $result = $this->service->convert(1, $this->bpUnit->id, $this->baseUnit->id);

        $this->assertEquals('1000.0000000000', $result);
    }

    /** @test */
    public function it_converts_big_pack_to_small_pack(): void
    {
        // 1 BP = 1000 VESP; 1000 ÷ 100 = 10 SP
        $result = $this->service->convert(1, $this->bpUnit->id, $this->spUnit->id);

        $this->assertEquals('10.0000000000', $result);
    }

    /** @test */
    public function it_converts_big_pack_to_extra_small_pack(): void
    {
        // 1 BP = 1000 VESP; 1000 ÷ 10 = 100 ESP
        $result = $this->service->convert(1, $this->bpUnit->id, $this->espUnit->id);

        $this->assertEquals('100.0000000000', $result);
    }

    /** @test */
    public function it_converts_small_pack_to_extra_small_pack(): void
    {
        // 2 SP = 200 VESP; 200 ÷ 10 = 20 ESP
        $result = $this->service->convert(2, $this->spUnit->id, $this->espUnit->id);

        $this->assertEquals('20.0000000000', $result);
    }

    /** @test */
    public function it_converts_fractional_quantities_accurately(): void
    {
        // 0.5 BP = 500 VESP; 500 ÷ 100 = 5 SP
        $result = $this->service->convert(0.5, $this->bpUnit->id, $this->spUnit->id);

        $this->assertEquals('5.0000000000', $result);
    }

    /** @test */
    public function it_returns_same_quantity_when_from_and_to_are_identical(): void
    {
        $result = $this->service->convert(42, $this->bpUnit->id, $this->bpUnit->id);

        $this->assertEquals('42.0000000000', $result);
    }

    /** @test */
    public function it_converts_zero_quantity(): void
    {
        $result = $this->service->convert(0, $this->bpUnit->id, $this->baseUnit->id);

        $this->assertEquals('0.0000000000', $result);
    }

    // =========================================================================
    // Cross-category guard
    // =========================================================================

    /** @test */
    public function it_throws_when_converting_between_different_categories(): void
    {
        $this->expectException(UomConversionException::class);
        $this->expectExceptionMessageMatches('/different UOM categories/i');

        // Quantity (BP) → Weight (Gram) must fail
        $this->service->convert(1, $this->bpUnit->id, $this->gramUnit->id);
    }

    /** @test */
    public function it_throws_when_from_uom_does_not_exist(): void
    {
        $this->expectException(UomConversionException::class);
        $this->expectExceptionMessageMatches('/does not exist/i');

        $this->service->convert(1, 99999, $this->baseUnit->id);
    }

    /** @test */
    public function it_throws_when_to_uom_does_not_exist(): void
    {
        $this->expectException(UomConversionException::class);

        $this->service->convert(1, $this->baseUnit->id, 99999);
    }

    /** @test */
    public function it_throws_when_uom_is_inactive(): void
    {
        $this->bpUnit->update(['is_active' => false]);

        $this->expectException(UomConversionException::class);
        $this->expectExceptionMessageMatches('/inactive/i');

        $this->service->convert(1, $this->bpUnit->id, $this->baseUnit->id);
    }

    // =========================================================================
    // Edge cases
    // =========================================================================

    /** @test */
    public function it_handles_large_quantities_without_precision_loss(): void
    {
        // 1,000,000 BP × 1000 = 1,000,000,000 VESP
        $result = $this->service->convertToBase(1_000_000, $this->bpUnit->id);

        $this->assertEquals('1000000000.0000000000', $result);
    }

    /** @test */
    public function it_handles_very_small_fractional_quantities(): void
    {
        // 0.001 BP × 1000 = 1 VESP
        $result = $this->service->convertToBase(0.001, $this->bpUnit->id);

        $this->assertEquals('1.0000000000', $result);
    }
}
