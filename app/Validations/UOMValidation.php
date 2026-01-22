<?php

namespace App\Validations;

use App\Helpers\GenerateUniqeCode;
use App\Models\UOM;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UOMValidation
{
    public function CreateValidationFields(Request $request): array
    {
        if (!$request->filled('uom_code')) {
            $request->merge([
                'uom_code' => GenerateUniqeCode::generate(
                    UOM::class,
                    'uom_code',
                    8,
                    'UOM'
                ),
            ]);
        }

        $validator = Validator::make($request->all(), [
            'uom_code' => [
                'required',
                'string',
                'max:12',
                'starts_with:UOM',
                Rule::unique('unit_of_measurements', 'uom_code'),
            ],
            'name' => [
                'required',
                'string',
                Rule::unique('unit_of_measurements', 'name'),
            ],
            'symbol' => 'nullable|string',
            'uom_type' => 'required|string',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        return $validator->validate();
    }

    public function UpdateValidationFields(Request $request, ?int $id = null): array
    {
        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                Rule::unique('unit_of_measurements', 'name')->ignore($id),
            ],
            'symbol' => 'nullable|string',
            'uom_type' => 'required|string',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        return $validator->validate();
    }
}
