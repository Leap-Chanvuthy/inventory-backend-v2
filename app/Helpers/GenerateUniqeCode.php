<?php

namespace App\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class GenerateUniqeCode
{
    /**
     * Generate a unique code for a given Model field.
     *
     * @param  class-string<Model>  $modelClass
     */
    public static function generate(
        string $modelClass,
        string $field,
        int $length = 8,
        ?string $prefix = null,
        int $maxAttempts = 50
    ): string {
        if (!is_subclass_of($modelClass, Model::class)) {
            throw new InvalidArgumentException("{$modelClass} must be an Eloquent Model class.");
        }

        if ($length < 1) {
            throw new InvalidArgumentException("Length must be >= 1.");
        }

        for ($i = 0; $i < $maxAttempts; $i++) {
            // Example: SUP-8CHARS
            $random = Str::upper(Str::random($length));
            $code = $prefix ? Str::upper($prefix) . "-" . $random : $random;

            $exists = $modelClass::query()
                ->where($field, $code)
                ->exists();

            if (!$exists) {
                return $code;
            }
        }

        throw new RuntimeException("Failed to generate unique code for {$modelClass}::{$field} after {$maxAttempts} attempts.");
    }
}