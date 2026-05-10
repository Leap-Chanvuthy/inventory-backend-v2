<?php

namespace App\Validations;

use App\Enums\UomQuantityTypeEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UomCategoryValidation
{
    public function createRules(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('uom_categories', 'name'),
            ],
            'quantity_type' => [
                'required',
                Rule::in([
                    UomQuantityTypeEnum::INTEGER->value,
                    UomQuantityTypeEnum::DECIMAL->value,
                ]),
            ],
            'description' => 'nullable|string|max:500',
        ]);

        return $validator->validate();
    }

    public function updateRules(Request $request, int $id): array
    {
        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('uom_categories', 'name')->ignore($id),
            ],
            'quantity_type' => [
                'sometimes',
                'required',
                Rule::in([
                    UomQuantityTypeEnum::INTEGER->value,
                    UomQuantityTypeEnum::DECIMAL->value,
                ]),
            ],
            'description' => 'nullable|string|max:500',
        ]);

        return $validator->validate();
    }
}
