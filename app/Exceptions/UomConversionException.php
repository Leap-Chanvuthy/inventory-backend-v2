<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a UOM conversion cannot be performed due to:
 *  - Cross-category conversion attempts
 *  - Missing or inactive UOM records
 *  - Zero conversion factors
 */
class UomConversionException extends RuntimeException
{
    //
}
