<?php

namespace Database\Factories;

use App\Models\UOM;
use Illuminate\Database\Eloquent\Factories\Factory;

class UOMFactory extends Factory
{
    protected $model = UOM::class;

    // Fixed list of UOMs
    public static $uoms = [
        ['name' => 'Kilogram', 'symbol' => 'kg', 'uom_type' => 'Weight'],
        ['name' => 'Gram', 'symbol' => 'g', 'uom_type' => 'Weight'],
        ['name' => 'Liter', 'symbol' => 'L', 'uom_type' => 'Volume'],
        ['name' => 'Milliliter', 'symbol' => 'mL', 'uom_type' => 'Volume'],
        ['name' => 'Meter', 'symbol' => 'm', 'uom_type' => 'Length'],
        ['name' => 'Centimeter', 'symbol' => 'cm', 'uom_type' => 'Length'],
        ['name' => 'Piece', 'symbol' => 'pc', 'uom_type' => 'Count'],
        ['name' => 'Pack', 'symbol' => 'pack', 'uom_type' => 'Count'],
        ['name' => 'Box', 'symbol' => 'box', 'uom_type' => 'Count'],
        ['name' => 'Dozen', 'symbol' => 'doz', 'uom_type' => 'Count'],
        ['name' => 'Hour', 'symbol' => 'h', 'uom_type' => 'Time'],
        ['name' => 'Minute', 'symbol' => 'min', 'uom_type' => 'Time'],
        ['name' => 'Second', 'symbol' => 's', 'uom_type' => 'Time'],
        ['name' => 'Inch', 'symbol' => 'in', 'uom_type' => 'Length'],
        ['name' => 'Foot', 'symbol' => 'ft', 'uom_type' => 'Length'],
        ['name' => 'Yard', 'symbol' => 'yd', 'uom_type' => 'Length'],
        ['name' => 'Ton', 'symbol' => 't', 'uom_type' => 'Weight'],
        ['name' => 'Milligram', 'symbol' => 'mg', 'uom_type' => 'Weight'],
        ['name' => 'Cubic Meter', 'symbol' => 'm³', 'uom_type' => 'Volume'],
        ['name' => 'Cubic Centimeter', 'symbol' => 'cm³', 'uom_type' => 'Volume'],
        ['name' => 'Ounce', 'symbol' => 'oz', 'uom_type' => 'Weight'],
        ['name' => 'Pound', 'symbol' => 'lb', 'uom_type' => 'Weight'],
        ['name' => 'Gallon', 'symbol' => 'gal', 'uom_type' => 'Volume'],
        ['name' => 'Quart', 'symbol' => 'qt', 'uom_type' => 'Volume'],
        ['name' => 'Pint', 'symbol' => 'pt', 'uom_type' => 'Volume'],
        ['name' => 'Millimeter', 'symbol' => 'mm', 'uom_type' => 'Length'],
        ['name' => 'Kilometer', 'symbol' => 'km', 'uom_type' => 'Length'],
        ['name' => 'Packet', 'symbol' => 'pkt', 'uom_type' => 'Count'],
        ['name' => 'Set', 'symbol' => 'set', 'uom_type' => 'Count'],
        ['name' => 'Bundle', 'symbol' => 'bundle', 'uom_type' => 'Count'],
    ];

    public function definition()
    {
        // We'll return empty array here because actual seeding will use the array directly
        return [];
    }
}
