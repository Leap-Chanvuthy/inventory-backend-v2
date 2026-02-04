<?php 


namespace App\Validations;


use App\Helpers\GenerateUniqueSKU;
use App\Models\RawMaterial;
use App\Models\RawMaterialCategory;
use App\Models\UOM;
use Illuminate\Http\Request;

class RMValidation {


    // Handle the validation for creating a Raw Material
    public function CreateRMValidation (Request $request): array
    {
        $rm = new RawMaterial();

        $relations = [];
        $format = '{prefix}-{random}';

        // Use the category chosen by the user (raw_material_category_id)
        if ($request->filled('raw_material_category_id')) {
            $cat = RawMaterialCategory::find($request->input('raw_material_category_id'));

            if ($cat) {
                $rm->rm_category()->associate($cat);
                $relations['cat'] = 'rm_category.category_name';
                $format = '{prefix}-{cat}-{random}';
            }
        }

        // Include UOM in SKU (required by validation)
        if ($request->filled('uom_id')) {
            $uom = UOM::find($request->input('uom_id'));
            if ($uom) {
                $rm->uom()->associate($uom);
                // Use name because it's required (symbol can be nullable)
                $relations['uom'] = 'uom.name';

                // If we already have category, format becomes RM-{CAT}-{UOM}-{RANDOM}
                // Otherwise fallback to RM-{UOM}-{RANDOM}
                $format = isset($relations['cat'])
                    ? '{prefix}-{cat}-{uom}-{random}'
                    : '{prefix}-{uom}-{random}';
            }
        }

        if (!$request->filled('material_sku_code')) {
            $request->merge([
                'material_sku_code' => GenerateUniqueSKU::generate(
                    model: $rm,
                    field: 'material_sku_code',
                    randomLength: 6,
                    prefix: 'RM',
                    relations: $relations,
                    format: $format
                )
            ]);
        }

        return [
            'material_name' => 'required|string|max:255',
            'material_sku_code' => 'required|string|unique:raw_materials,material_sku_code|max:255',
            'barcode' => 'nullable|string|max:255',
            'minimum_stock_level' => 'required|numeric|min:0',
            'expiry_date' => 'required|date',
            'description' => 'nullable|string',
            'raw_material_category_id' => 'required|exists:raw_material_categories,id',
            'uom_id' => 'required|exists:unit_of_measurements,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'warehouse_id' => 'required|exists:warehouses,id',
        ];
    }



    public function CreateRMStockMovementValidation (Request $request): array
    {
        return [
            'raw_material_id' => 'required|exists:raw_materials,id',
            'supplier_id' => 'required|exists:suppliers,id',
            'quantity' => 'required|numeric|min:0',
            'direction' => 'required|in:IN,OUT',
            'movement_type' => (function () use ($request) {
                if (!$request->filled('movement_type')) {
                    $request->merge(['movement_type' => 'PURCHASE']);
                }
                return 'required|in:PURCHASE,RE_ORDER,SALE,PRODUCTION_SCRAP,PRODUCTION_RECEIPT,ADJUSTMENT_IN,ADJUSTMENT_OUT';
            })(),
            'unit_price_in_usd' => 'required|numeric|min:0',
            'total_value_in_usd' => 'nullable|numeric|min:0',
            'exchange_rate_from_usd_to_riel' => 'required|numeric|min:0',
            'unit_price_in_riel' => 'nullable|numeric|min:0',
            'total_value_in_riel' => 'nullable|numeric|min:0',
            'exchange_rate_from_riel_to_usd' => 'nullable|numeric|min:0',
            'note' => 'nullable|string',
            'movement_date' => 'required|date',
        ];
    }

    public function CreateRMImageValidation (Request $request): array
    {
        return [
            'raw_material_id' => 'nullable|exists:raw_materials,id',
            // Images are optional on create. If provided, validate type/size.
            'image' => 'nullable|image|max:2048',
            'images' => 'nullable|array|max:4',
            'images.*' => 'image|max:2048',
        ];
    }

}