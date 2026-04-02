<?php

namespace App\Helpers;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductHelper
{
    public static function applyCommonDefaults(Request $request, GetCurrentUserHelper $getCurrentUserHelper): void
    {
        if (!$request->filled('movement_date')) {
            $request->merge(['movement_date' => now()->toDateTimeString()]);
        }

        $userId = $getCurrentUserHelper->getUserId();
        $request->merge([
            'created_by'      => $userId,
            'last_updated_by' => $userId,
        ]);
    }

    public static function forceZeroPurchasePrices(Request $request): void
    {
        $request->merge([
            'purchase_unit_price_in_usd'     => 0,
            'purchase_total_price_in_usd'    => 0,
            'exchange_rate_from_usd_to_riel' => 0,
            'purchase_unit_price_in_riel'    => 0,
            'purchase_total_price_in_riel'   => 0,
            'exchange_rate_from_riel_to_usd' => 0,
        ]);
    }

    public static function collectValidationErrors(Request $request, array $ruleSets, array $messages = []): array
    {
        $errors = [];

        $mergeErrors = static function (array $base, array $incoming): array {
            foreach ($incoming as $field => $msgs) {
                $base[$field] = array_values(array_unique(array_merge($base[$field] ?? [], $msgs)));
            }
            return $base;
        };

        foreach ($ruleSets as $rules) {
            $validator = Validator::make($request->all(), $rules, $messages);
            if ($validator->fails()) {
                $errors = $mergeErrors($errors, $validator->errors()->toArray());
            }
        }

        return $errors;
    }

    public static function handleImageUpload(Request $request, Product $product): void
    {
        $files = [];

        if ($request->hasFile('images')) {
            $files = $request->file('images');
        } elseif ($request->hasFile('image')) {
            $files = [$request->file('image')];
        }

        if (empty($files)) {
            return;
        }

        $files = array_slice($files, 0, 4);

        foreach ($files as $file) {
            $imageUrl = FileUploadHelper::uploadSingle($file, 'products', null);
            ProductImage::create([
                'product_id' => $product->id,
                'image'      => $imageUrl,
            ]);
        }
    }

    public static function freshProduct(Product $product): Product
    {
        return $product->fresh([
            'category'                        => fn ($q) => $q->withTrashed(),
            'supplier'                        => fn ($q) => $q->withTrashed(),
            'warehouse'                       => fn ($q) => $q->withTrashed(),
            'uom'                             => fn ($q) => $q->withTrashed(),
            'productMovements.createdBy',
            'productMovements.lastUpdatedBy',
            'productRawMaterials.rawMaterial',
            'productImages',
        ]);
    }
}
