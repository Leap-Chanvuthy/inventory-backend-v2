<?php

namespace App\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use RuntimeException;

class GenerateUniqueSKU
{
    /**
     * Generate a unique SKU using model relationships.
     *
     * @param  Model  $model
     * @param  string  $field
     * @param  array<string, string>  $relations   ['category' => 'code']
     * @param  string  $format
     */
    public static function generate(
        Model $model,
        string $field,
        int $randomLength = 6,
        ?string $prefix = null,
        array $relations = [],
        string $format = '{prefix}-{random}',
        int $maxAttempts = 50
    ): string {
        $modelClass = get_class($model);

        if ($randomLength < 1) {
            throw new InvalidArgumentException("Random length must be >= 1.");
        }

        // Resolve relation values
        $resolved = self::resolveRelations($model, $relations);

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $random = Str::upper(Str::random($randomLength));

            $replacements = array_merge(
                [
                    'prefix' => $prefix ? Str::upper($prefix) : null,
                    'random' => $random,
                ],
                $resolved
            );

            $code = self::applyFormat($format, $replacements);

            $exists = $modelClass::query()
                ->where($field, $code)
                ->exists();

            if (!$exists) {
                return $code;
            }
        }

        throw new RuntimeException(
            "Failed to generate unique SKU for {$modelClass}::{$field}"
        );
    }

    /**
     * Resolve relationship attributes using dot notation.
     *
     * @param array<string, string> $relations
     */
    protected static function resolveRelations(Model $model, array $relations): array
    {
        $data = [];

        foreach ($relations as $key => $path) {
            $value = data_get($model, $path);

            if ($value === null) {
                throw new InvalidArgumentException("Relation path '{$path}' not found on model.");
            }

            $data[$key] = Str::upper((string) $value);
        }

        return $data;
    }

    protected static function applyFormat(string $format, array $replacements): string
    {
        return preg_replace_callback('/\{(\w+)\}/', function ($matches) use ($replacements) {
            return $replacements[$matches[1]] ?? '';
        }, $format);
    }
}
