<?php

namespace Database\Factories;

use App\Models\UnitOfMeasurement;
use Illuminate\Database\Eloquent\Factories\Factory;

class UOMFactory extends Factory
{
    protected $model = UnitOfMeasurement::class;

    /**
     * UOM definitions grouped by category.
     *
     * Each entry:
     *   category     — maps to uom_categories.name
     *   name         — unit name (unique per category)
     *   symbol       — short symbol
     *   is_base_unit — true for the single base unit of the category
     *   conversion_factor — how many base units equal 1 of this unit
     *
     * Base unit always has conversion_factor = 1 and is_base_unit = true.
     *
     * Conversion factors reference:
     *   Weight  base → Gram
     *   Volume  base → Milliliter
     *   Length  base → Millimeter
     *   Count   base → Piece
     *   Time    base → Second
     */
    public static array $uoms = [
        // ── Weight ──────────────────────────────────────────────────────────
        ['category' => 'Weight', 'name' => 'Gram',      'symbol' => 'g',   'is_base_unit' => true,  'conversion_factor' => 1.000000],
        ['category' => 'Weight', 'name' => 'Milligram', 'symbol' => 'mg',  'is_base_unit' => false, 'conversion_factor' => 0.001000],
        ['category' => 'Weight', 'name' => 'Kilogram',  'symbol' => 'kg',  'is_base_unit' => false, 'conversion_factor' => 1000.000000],
        ['category' => 'Weight', 'name' => 'Ton',       'symbol' => 't',   'is_base_unit' => false, 'conversion_factor' => 1000000.000000],
        ['category' => 'Weight', 'name' => 'Ounce',     'symbol' => 'oz',  'is_base_unit' => false, 'conversion_factor' => 28.349523],
        ['category' => 'Weight', 'name' => 'Pound',     'symbol' => 'lb',  'is_base_unit' => false, 'conversion_factor' => 453.592370],

        // ── Volume ──────────────────────────────────────────────────────────
        ['category' => 'Volume', 'name' => 'Milliliter',      'symbol' => 'mL',  'is_base_unit' => true,  'conversion_factor' => 1.000000],
        ['category' => 'Volume', 'name' => 'Liter',           'symbol' => 'L',   'is_base_unit' => false, 'conversion_factor' => 1000.000000],
        ['category' => 'Volume', 'name' => 'Cubic Centimeter','symbol' => 'cm³', 'is_base_unit' => false, 'conversion_factor' => 1.000000],
        ['category' => 'Volume', 'name' => 'Cubic Meter',     'symbol' => 'm³',  'is_base_unit' => false, 'conversion_factor' => 1000000.000000],
        ['category' => 'Volume', 'name' => 'Gallon',          'symbol' => 'gal', 'is_base_unit' => false, 'conversion_factor' => 3785.411784],
        ['category' => 'Volume', 'name' => 'Quart',           'symbol' => 'qt',  'is_base_unit' => false, 'conversion_factor' => 946.352946],
        ['category' => 'Volume', 'name' => 'Pint',            'symbol' => 'pt',  'is_base_unit' => false, 'conversion_factor' => 473.176473],

        // ── Length ──────────────────────────────────────────────────────────
        ['category' => 'Length', 'name' => 'Millimeter', 'symbol' => 'mm', 'is_base_unit' => true,  'conversion_factor' => 1.000000],
        ['category' => 'Length', 'name' => 'Centimeter', 'symbol' => 'cm', 'is_base_unit' => false, 'conversion_factor' => 10.000000],
        ['category' => 'Length', 'name' => 'Meter',      'symbol' => 'm',  'is_base_unit' => false, 'conversion_factor' => 1000.000000],
        ['category' => 'Length', 'name' => 'Kilometer',  'symbol' => 'km', 'is_base_unit' => false, 'conversion_factor' => 1000000.000000],
        ['category' => 'Length', 'name' => 'Inch',       'symbol' => 'in', 'is_base_unit' => false, 'conversion_factor' => 25.400000],
        ['category' => 'Length', 'name' => 'Foot',       'symbol' => 'ft', 'is_base_unit' => false, 'conversion_factor' => 304.800000],
        ['category' => 'Length', 'name' => 'Yard',       'symbol' => 'yd', 'is_base_unit' => false, 'conversion_factor' => 914.400000],

        // ── Count ───────────────────────────────────────────────────────────
        ['category' => 'Count', 'name' => 'Piece',  'symbol' => 'pc',     'is_base_unit' => true,  'conversion_factor' => 1.000000],
        ['category' => 'Count', 'name' => 'Pack',   'symbol' => 'pack',   'is_base_unit' => false, 'conversion_factor' => 10.000000],
        ['category' => 'Count', 'name' => 'Dozen',  'symbol' => 'doz',    'is_base_unit' => false, 'conversion_factor' => 12.000000],
        ['category' => 'Count', 'name' => 'Box',    'symbol' => 'box',    'is_base_unit' => false, 'conversion_factor' => 12.000000],
        ['category' => 'Count', 'name' => 'Packet', 'symbol' => 'pkt',    'is_base_unit' => false, 'conversion_factor' => 10.000000],
        ['category' => 'Count', 'name' => 'Set',    'symbol' => 'set',    'is_base_unit' => false, 'conversion_factor' => 1.000000],
        ['category' => 'Count', 'name' => 'Bundle', 'symbol' => 'bundle', 'is_base_unit' => false, 'conversion_factor' => 50.000000],

        // ── Time ────────────────────────────────────────────────────────────
        ['category' => 'Time', 'name' => 'Second', 'symbol' => 's',   'is_base_unit' => true,  'conversion_factor' => 1.000000],
        ['category' => 'Time', 'name' => 'Minute', 'symbol' => 'min', 'is_base_unit' => false, 'conversion_factor' => 60.000000],
        ['category' => 'Time', 'name' => 'Hour',   'symbol' => 'h',   'is_base_unit' => false, 'conversion_factor' => 3600.000000],
    ];

    public function definition(): array
    {
        // Actual seeding is handled by UOMSeeder using the $uoms array directly.
        return [];
    }
}
