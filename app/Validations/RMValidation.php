<?php 


namespace App\Validations;


use App\Helpers\GenerateUniqueSKU;
use App\Helpers\CurrencyPricingHelper;
use App\Models\RawMaterial;
use App\Models\RawMaterialCategory;
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
                $relations = ['cat' => 'rm_category.category_name'];
                $format = '{prefix}-{cat}-{random}';
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
            'minimum_quantity_stock_level' => 'required|numeric|min:0',
            'expiry_date' => 'required|date',
            'status' => 'required|in:IN_STOCK,OUT_OF_STOCK,LOW_STOCK,EXPIRED',
            'description' => 'nullable|string',
            'raw_material_category_id' => 'required|exists:raw_material_categories,id',
            'uom_id' => 'required|exists:unit_of_measurements,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'warehouse_id' => 'required|exists:warehouses,id',
        ];
    }


    public function CreateRMPurchasingTransactionValidation (Request $request): array
    {
        CurrencyPricingHelper::fillRMPurchasingCurrencyFields($request);

        return [
            'raw_material_id' => 'required|exists:raw_materials,id',
            'supplier_id' => 'required|exists:suppliers,id',
            'quantity' => 'required|numeric|min:0',
            'transaction_date' => 'required|date',
            'unit_price_in_usd' => 'required|numeric|min:0',
            'total_value_in_usd' => 'nullable|numeric|min:0',
            'exchange_rate_from_usd_to_riel' => 'required|numeric|min:0',
            'unit_price_in_riel' => 'nullable|numeric|min:0',
            'total_value_in_riel' => 'nullable|numeric|min:0',
            'exchange_rate_from_riel_to_usd' => 'nullable|numeric|min:0',
        ];
    }


    public function CreateRMStockMovementValidation (Request $request): array
    {
        return [
            'raw_material_id' => 'required|exists:raw_materials,id',
            'quantity' => 'required|numeric|min:0',
            'direction' => 'required|in:IN,OUT',
            'movement_type' => (function () use ($request) {
                if (!$request->filled('movement_type')) {
                    $request->merge(['movement_type' => 'PURCHASE']);
                }
                return 'required|in:PURCHASE,RE_ORDER,SALE,PRODUCTION_SCRAP,PRODUCTION_RECEIPT,ADJUSTMENT_IN,ADJUSTMENT_OUT';
            })(),
            'movement_date' => 'required|date',
        ];
    }

    public function CreateRMImageValidation (Request $request): array
    {
        return [
            'raw_material_id' => 'nullable|exists:raw_materials,id',
            'image' => 'nullable_without:images|image|max:2048',
            'images' => 'nullable_without:image|array|max:4',
            'images.*' => 'image|max:2048',
        ];
    }



    


}