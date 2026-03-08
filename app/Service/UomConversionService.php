<?php

namespace App\Service;

use App\Exceptions\UomConversionException;
use App\Models\UnitOfMeasurement;
use InvalidArgumentException;

/**
 * UomConversionService
 *
 * Converts quantities between unit of measurements that belong to the same
 * UOM category (e.g. Quantity, Weight, Volume).
 *
 * Design goals:
 *  - O(1) conversions  — no recursive queries required.
 *  - High precision    — PHP BCMath via bcscale / bcdiv / bcmul.
 *  - Fail-fast         — throws UomConversionException on invalid input.
 *
 * ──────────────────────────────────────────────────────────────────────────
 * Formula
 * ──────────────────────────────────────────────────────────────────────────
 *   base_quantity     = quantity × from_uom.conversion_factor
 *   converted_qty     = base_quantity ÷ to_uom.conversion_factor
 *
 * Example — Big Pack → Very Extra Small Pack (VESP is the base unit):
 *   base_qty  = 5 × 1000 = 5000 VESP
 *   result    = 5000 ÷ 1    = 5000 VESP   ✓
 *
 * Example — Big Pack → Small Pack:
 *   base_qty  = 5 × 1000 = 5000 VESP
 *   result    = 5000 ÷ 100  = 50 Small Packs  ✓
 * ──────────────────────────────────────────────────────────────────────────
 */
class UomConversionService
{
    /** BCMath scale (decimal places) used throughout conversions. */
    private const SCALE = 10;

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Convert a quantity from one UOM to another.
     *
     * Both UOMs must belong to the same category, otherwise an exception is
     * thrown to prevent nonsensical conversions (e.g. Kg → Litre).
     *
     * @param  float|string  $quantity   The quantity to convert.
     * @param  int           $fromUomId  Source UOM primary key.
     * @param  int           $toUomId    Target UOM primary key.
     * @return string                    Converted quantity as a high-precision decimal string.
     *
     * @throws UomConversionException
     */
    public function convert(float|string $quantity, int $fromUomId, int $toUomId): string
    {
        // Short-circuit: same unit, no calculation needed
        if ($fromUomId === $toUomId) {
            return $this->normalise($quantity);
        }

        [$fromUom, $toUom] = $this->loadAndValidatePair($fromUomId, $toUomId);

        $base = $this->toBaseString($quantity, $fromUom);

        return $this->fromBaseString($base, $toUom);
    }

    /**
     * Convert a quantity from a given UOM to the base unit of its category.
     *
     * @param  float|string  $quantity
     * @param  int           $uomId
     * @return string  Quantity in base units (high-precision decimal string).
     *
     * @throws UomConversionException
     */
    public function convertToBase(float|string $quantity, int $uomId): string
    {
        $uom = $this->loadUom($uomId);

        return $this->toBaseString($quantity, $uom);
    }

    /**
     * Convert a quantity that is currently expressed in the base unit into a
     * given target UOM.
     *
     * @param  float|string  $baseQuantity  Quantity expressed in the base unit.
     * @param  int           $uomId         Target UOM primary key.
     * @return string  Quantity in target UOM (high-precision decimal string).
     *
     * @throws UomConversionException
     */
    public function convertFromBase(float|string $baseQuantity, int $uomId): string
    {
        $uom = $this->loadUom($uomId);

        return $this->fromBaseString($baseQuantity, $uom);
    }

    // -------------------------------------------------------------------------
    // Typed convenience wrappers
    // -------------------------------------------------------------------------

    /**
     * Same as convert() but returns a float.
     * Use when BCMath precision is not required downstream.
     */
    public function convertFloat(float|string $quantity, int $fromUomId, int $toUomId): float
    {
        return (float) $this->convert($quantity, $fromUomId, $toUomId);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Load a single UOM or throw if not found / inactive.
     *
     * @throws UomConversionException
     */
    private function loadUom(int $id): UnitOfMeasurement
    {
        $uom = UnitOfMeasurement::find($id);

        if (! $uom) {
            throw new UomConversionException("UOM with ID {$id} does not exist.");
        }

        if (! $uom->is_active) {
            throw new UomConversionException(
                "UOM '{$uom->name}' (ID {$id}) is inactive and cannot be used for conversions."
            );
        }

        return $uom;
    }

    /**
     * Load both UOMs and validate they belong to the same category.
     *
     * @return array{0: UnitOfMeasurement, 1: UnitOfMeasurement}
     * @throws UomConversionException
     */
    private function loadAndValidatePair(int $fromId, int $toId): array
    {
        $fromUom = $this->loadUom($fromId);
        $toUom   = $this->loadUom($toId);

        if ($fromUom->category_id !== $toUom->category_id) {
            throw new UomConversionException(
                "Cannot convert between different UOM categories: " .
                "'{$fromUom->name}' belongs to category ID {$fromUom->category_id}, " .
                "'{$toUom->name}' belongs to category ID {$toUom->category_id}."
            );
        }

        return [$fromUom, $toUom];
    }

    /**
     * Multiply quantity by the UOM's conversion_factor to get the base quantity.
     *
     * base_qty = quantity × conversion_factor
     *
     * @throws UomConversionException
     */
    private function toBaseString(float|string $quantity, UnitOfMeasurement $uom): string
    {
        $this->guardQuantity($quantity);

        return bcmul(
            $this->normalise($quantity),
            $this->normalise($uom->conversion_factor),
            self::SCALE
        );
    }

    /**
     * Divide a base-unit quantity by the UOM's conversion_factor.
     *
     * converted_qty = base_qty ÷ conversion_factor
     *
     * @throws UomConversionException
     */
    private function fromBaseString(string $baseQty, UnitOfMeasurement $uom): string
    {
        $factor = $this->normalise($uom->conversion_factor);

        if (bccomp($factor, '0', self::SCALE) === 0) {
            throw new UomConversionException(
                "Conversion factor for UOM '{$uom->name}' (ID {$uom->id}) is zero, " .
                "which would cause a division-by-zero error."
            );
        }

        return bcdiv($baseQty, $factor, self::SCALE);
    }

    /**
     * Guard against negative or non-numeric quantities.
     *
     * @throws InvalidArgumentException
     */
    private function guardQuantity(float|string $quantity): void
    {
        if (! is_numeric($quantity)) {
            throw new InvalidArgumentException(
                "Quantity must be numeric, '{$quantity}' given."
            );
        }

        if (bccomp($this->normalise($quantity), '0', self::SCALE) < 0) {
            throw new InvalidArgumentException(
                "Quantity must be non-negative, '{$quantity}' given."
            );
        }
    }

    /**
     * Normalise a numeric value to a string for BCMath.
     */
    private function normalise(float|string $value): string
    {
        return number_format((float) $value, self::SCALE, '.', '');
    }
}
