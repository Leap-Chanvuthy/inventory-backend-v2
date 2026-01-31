<?php 


namespace App\Validations;


use App\Helpers\GenerateUniqueSKU;
use App\Models\RawMaterial;
use App\Models\RawMaterialCategory;
use Illuminate\Http\Request;

class RMValidation {



    public function CreateValidation (Request $request): array
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
            'name' => 'required|string|max:255',
            'material_sku_code' => 'required|string|max:100|unique:raw_materials,material_sku_code',
            'description' => 'nullable|string',
            'uom_id' => 'required|exists:uoms,id',
            'raw_material_category_id' => 'required|exists:raw_material_categories,id',
            'supplier_id' => 'required|exists:suppliers,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'minimum_stock_level' => 'required|numeric|min:0',
            'maximum_stock_level' => 'required|numeric|min:0|gte:minimum_stock_level',
            'reorder_level' => 'required|numeric|min:0|lte:maximum_stock_level',
            'current_stock_level' => 'required|numeric|min:0',
            'status' => 'required|string|in:IN_STOCK,LOW_STOCK,OUT_OF_STOCK,EXPIRED',
        ];
    }


}