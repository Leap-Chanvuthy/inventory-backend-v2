<?php

namespace App\Service;

use App\Helpers\ImageDeleteHelper;
use App\Helpers\FileUploadHelper;
use App\Helpers\ResponseHelper;
use App\Models\RMImage;
use App\Models\RawMaterial;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RMImageService
{
    public function __construct(
        protected AuditLoggerService $auditLoggerService
    ) {
    }

    /**
     * Upload raw material images from detail page.
     * Expected payload: multipart/form-data with images[].
     */
    public function uploadRawMaterialImages(int $rawMaterialId, Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'images' => 'required|array|min:1|max:4',
                'images.*' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
            ]);

            if ($validator->fails()) {
                return ResponseHelper::validation($validator->errors()->toArray(), 'Validation Error');
            }

            $rawMaterial = RawMaterial::find($rawMaterialId);
            if (!$rawMaterial) {
                return ResponseHelper::error('Raw Material not found', 404);
            }

            $existingCount = RMImage::query()
                ->where('raw_material_id', $rawMaterialId)
                ->count();

            $files = $request->file('images', []);
            if (($existingCount + count($files)) > 8) {
                return ResponseHelper::validation([
                    'images' => ['A raw material can have at most 8 images.'],
                ], 'Validation Error');
            }

            $createdRows = DB::transaction(function () use ($rawMaterialId, $files) {
                $rows = [];
                foreach ($files as $file) {
                    $url = FileUploadHelper::uploadSingle($file, 'raw-materials', null);
                    $rows[] = RMImage::query()->create([
                        'raw_material_id' => $rawMaterialId,
                        'image' => $url,
                    ]);
                }
                return $rows;
            });

            return ResponseHelper::success([
                'uploaded_images' => collect($createdRows)->map(fn ($row) => [
                    'id' => (int) $row->id,
                    'image' => $row->image,
                ])->values()->all(),
                'images' => RMImage::query()
                    ->where('raw_material_id', $rawMaterialId)
                    ->orderBy('id')
                    ->get(['id', 'image']),
            ], 'Raw material images uploaded successfully', 201);
        } catch (Exception $e) {
            return ResponseHelper::error('Failed uploading raw material image(s)', 500, $e->getMessage());
        }
    }

    /**
     * Delete raw material images (user can send one id or many ids).
     * Expected payload: {"image_ids": [1,2]}.
     */
    public function deleteRawMaterialImages(int $rawMaterialId, Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'image_ids' => 'required|array|min:1|max:4',
                'image_ids.*' => 'required|integer|distinct|exists:rm_images,id',
            ]);

            if ($validator->fails()) {
                return ResponseHelper::validation($validator->errors()->toArray(), 'Validation Error');
            }

            $rawMaterial = RawMaterial::find($rawMaterialId);
            if (!$rawMaterial) {
                return ResponseHelper::error('Raw Material not found', 404);
            }

            $imageIds = array_values($request->input('image_ids'));

            $images = RMImage::query()
                ->where('raw_material_id', $rawMaterialId)
                ->whereIn('id', $imageIds)
                ->get();

            if ($images->count() !== count($imageIds)) {
                $foundIds = $images->pluck('id')->map(fn ($id) => (int) $id)->all();
                $missingIds = array_values(array_diff($imageIds, $foundIds));

                return ResponseHelper::error('Some images were not found for this raw material', 404, [
                    'missing_image_ids' => $missingIds,
                ]);
            }

            $deletedIds = $images->pluck('id')->map(fn ($id) => (int) $id)->all();
            $oldImages = $images->map(fn ($img) => [
                'id' => (int) $img->id,
                'image' => $img->image,
            ])->values()->all();

            DB::transaction(function () use ($images) {
                ImageDeleteHelper::deleteMultiple($images);
            });

            $remainingImages = RMImage::query()
                ->where('raw_material_id', $rawMaterialId)
                ->orderBy('id')
                ->get(['id', 'image'])
                ->map(fn ($img) => [
                    'id' => (int) $img->id,
                    'image' => $img->image,
                ])->values()->all();

            $this->auditLoggerService->logDiff(
                'raw_material.images.delete',
                RawMaterial::class,
                (int) $rawMaterialId,
                ['deleted_images' => $oldImages],
                ['remaining_images' => $remainingImages],
                null,
                ['context' => 'rm_image_service']
            );

            return ResponseHelper::success([
                'deleted_image_ids' => $deletedIds,
                'deleted_count' => count($deletedIds),
            ], 'Raw material image(s) deleted successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error('Failed deleting raw material image(s)', 500, $e->getMessage());
        }
    }
}
