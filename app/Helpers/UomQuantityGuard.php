<?php

namespace App\Helpers;

use App\Enums\UomQuantityTypeEnum;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasurement;
use Illuminate\Validation\ValidationException;

class UomQuantityGuard
{
    /**
     * Validate a quantity against the UOM category's configured quantity_type.
     *
     * INTEGER: only whole numbers are allowed.
     * DECIMAL: any numeric value is allowed (existing numeric validation applies).
     *
     * @throws ValidationException
     */
    public static function assertQuantityByUomId(
        mixed $quantity,
        ?int $uomId,
        string $field = 'quantity'
    ): void {
        if (! $uomId) {
            return;
        }

        $uom = UnitOfMeasurement::withTrashed()
            ->with(['category' => fn ($q) => $q->withTrashed()])
            ->find($uomId);
        if (! $uom) {
            return;
        }

        self::assertQuantityByUom($quantity, $uom, $field);
    }

    /**
     * Validate per-unit BOM quantities against each raw material's base UOM.
     *
     * @throws ValidationException
     */
    public static function assertBomQuantities(array $bomItems): void
    {
        if (empty($bomItems)) {
            return;
        }

        $rawMaterialIds = collect($bomItems)
            ->pluck('raw_material_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($rawMaterialIds->isEmpty()) {
            return;
        }

        $rawMaterials = RawMaterial::with(['baseUom' => fn ($q) => $q->withTrashed()])
            ->whereIn('id', $rawMaterialIds->all())
            ->get()
            ->keyBy('id');

        foreach ($bomItems as $index => $item) {
            $rawMaterialId = (int) ($item['raw_material_id'] ?? 0);
            $quantity = $item['quantity_per_unit'] ?? null;

            if (! $rawMaterialId || $quantity === null) {
                continue;
            }

            $rawMaterial = $rawMaterials->get($rawMaterialId);
            if (! $rawMaterial || ! $rawMaterial->base_uom_id) {
                continue;
            }

            self::assertQuantityByUomId(
                $quantity,
                (int) $rawMaterial->base_uom_id,
                "raw_materials.{$index}.quantity_per_unit"
            );
        }
    }

    /**
     * @throws ValidationException
     */
    private static function assertQuantityByUom(
        mixed $quantity,
        UnitOfMeasurement $uom,
        string $field
    ): void {
        $categoryQuantityType = $uom->category?->quantity_type;
        $quantityType = $categoryQuantityType instanceof UomQuantityTypeEnum
            ? $categoryQuantityType->value
            : (string) ($categoryQuantityType ?? UomQuantityTypeEnum::DECIMAL->value);

        if ($quantityType !== UomQuantityTypeEnum::INTEGER->value) {
            return;
        }

        if (self::isWholeNumber($quantity)) {
            return;
        }

        throw ValidationException::withMessages([
            $field => [
                "This UOM category only allows whole-number quantities. Decimal quantity is not allowed.",
            ],
        ]);
    }

    private static function isWholeNumber(mixed $value): bool
    {
        if (is_int($value)) {
            return true;
        }

        if (is_float($value)) {
            return abs($value - round($value)) < 1e-9;
        }

        $stringValue = trim((string) $value);
        if ($stringValue === '') {
            return false;
        }

        if (preg_match('/^-?\d+$/', $stringValue) === 1) {
            return true;
        }

        if (preg_match('/^-?\d+\.0+$/', $stringValue) === 1) {
            return true;
        }

        if (! is_numeric($stringValue)) {
            return false;
        }

        $floatValue = (float) $stringValue;
        return abs($floatValue - round($floatValue)) < 1e-9;
    }
}
