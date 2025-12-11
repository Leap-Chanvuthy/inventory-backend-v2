<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Model;

class ImageDeleteHelper
{
    /** * Delete a single image from R2 and DB. * * @param Model $imageModel The model instance (e.g., WarehouseImage) * @param string $columnName The column containing URL (default: image) * @return bool */ public static function deleteSingle(Model $imageModel, string $columnName = 'image'): bool
    {
        $url = $imageModel->$columnName;
        if ($url) {
            $path = ltrim(parse_url($url, PHP_URL_PATH), '/');
            if (Storage::disk('r2')->exists($path)) {
                Storage::disk('r2')->delete($path);
            }
        }
        return $imageModel->delete();
    }
    /** * Delete multiple images by model collection or array of IDs. * * @param iterable $images Array or Collection of model instances * @param string $columnName * @return int Number of deleted items */ public static function deleteMultiple(iterable $images, string $columnName = 'image'): int
    {
        $count = 0;
        foreach ($images as $image) {
            if (self::deleteSingle($image, $columnName)) {
                $count++;
            }
        }
        return $count;
    }
}
