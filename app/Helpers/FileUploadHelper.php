<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;

class FileUploadHelper
{
    /**
     * Upload a single file to R2 and delete old one.
     */
    public static function uploadSingle($file, string $folder, ?string $oldUrl = null): ?string
    {
        if (!$file) return $oldUrl;

        // Delete old file
        if (!empty($oldUrl)) {
            $oldPath = ltrim(parse_url($oldUrl, PHP_URL_PATH) ?? '', '/');
            if ($oldPath !== '' && Storage::disk('r2')->exists($oldPath)) {
                Storage::disk('r2')->delete($oldPath);
            }
        }

        // Upload new file
        $path = Storage::disk('r2')->putFile($folder, $file, 'public');
        return config('app.r2_public_dev_domain') . '/' . $path;
    }


    /**
     * Multiple Upload (Replace Mode)
     * - Deletes ALL old images
     * - Uploads NEW images
     */
    public static function uploadMultipleReplace(array $newFiles, string $folder, array $oldUrls = []): array
    {
        // Delete all old images
        foreach ($oldUrls as $url) {
            if (!$url) continue;

            $oldPath = ltrim(parse_url($url, PHP_URL_PATH) ?? '', '/');
            if ($oldPath !== '' && Storage::disk('r2')->exists($oldPath)) {
                Storage::disk('r2')->delete($oldPath);
            }
        }

        // Upload new images
        $result = [];
        foreach ($newFiles as $file) {
            $path = Storage::disk('r2')->putFile($folder, $file, 'public');
            $result[] = config('app.r2_public_dev_domain') . '/' . $path;
        }

        return $result;
    }


    /**
     * Multiple Upload (Append Mode)
     * - Keeps old images
     * - Uploads NEW files and merges
     */
    public static function uploadMultipleAppend(array $newFiles, string $folder, array $oldUrls = []): array
    {
        $result = $oldUrls ?? [];

        // Upload new images
        foreach ($newFiles as $file) {
            $path = Storage::disk('r2')->putFile($folder, $file, 'public');
            $result[] = config('app.r2_public_dev_domain') . '/' . $path;
        }

        return $result;
    }
}
