<?php

namespace App\Models;

/**
 * UOM — backward-compatible alias for UnitOfMeasurement.
 *
 * All legacy code referencing UOM::class continues to work unchanged
 * while new code should prefer UnitOfMeasurement::class.
 */
class UOM extends UnitOfMeasurement
{
    //
}
