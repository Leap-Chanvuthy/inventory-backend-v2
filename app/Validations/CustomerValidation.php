<?php

namespace App\Validations;

use App\Enums\CustomerStatusEnum;
use App\Enums\PaymentTermEnum;
use App\Helpers\GenerateUniqeCode;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerValidation {

    private function normalizeCustomerCategoryId(Request $request): void
    {
        if ($request->filled('customer_category_id')) {
            $request->merge([
                'customer_category_id' => (int) $request->input('customer_category_id'),
            ]);
        }
    }

    private function normalizeCustomerStatus(Request $request): void
    {
        if ($request->filled('customer_status')) {
            $request->merge([
                'customer_status' => strtolower((string) $request->input('customer_status')),
            ]);
        }
    }

    public function CreateValidation(Request $request): array
    {
        if (!$request->filled('customer_code')) {
            $request->merge([
                'customer_code' => GenerateUniqeCode::generate(
                    Customer::class,
                    'customer_code',
                    8,
                    'CUS'
                ),
            ]);
        }

        $this->normalizeCustomerCategoryId($request);
        $this->normalizeCustomerStatus($request);

        return $request->validate([
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'customer_code' => [
                'required',
                'string',
                'max:12',
                'starts_with:CUS',
                Rule::unique('customers', 'customer_code'),
            ],
            'fullname' => 'required|string|max:50',
            'email_address' => 'nullable|email|max:50',
            'phone_number' => 'required|string|max:50',
            'social_media' => 'nullable|string|max:100',
            'customer_address' => 'nullable|string|max:255',
            'google_map_link' => 'nullable|string|max:100',
            'customer_status' => [
                'required',
                'string',
                Rule::enum(CustomerStatusEnum::class),
            ],
            'customer_category_id' => [
                'required',
                'integer',
                'exists:customer_categories,id',
            ],
            'customer_note' => 'nullable|string',
            'payment_terms' => ['nullable', Rule::enum(PaymentTermEnum::class)],
        ]);

    }


    public function UpdateValidation(Request $request, $id): array
    {
        $this->normalizeCustomerCategoryId($request);
        $this->normalizeCustomerStatus($request);

        return $request->validate([
            'image' => 'sometimes|nullable|image|mimes:jpeg,png,jpg|max:2048',
            'fullname' => 'sometimes|required|string|max:50',
            'email_address' => 'sometimes|nullable|email|max:50',
            'phone_number' => 'sometimes|required|string|max:50',
            'social_media' => 'sometimes|nullable|string|max:100',
            'customer_address' => 'sometimes|nullable|string|max:255',
            'google_map_link' => 'sometimes|nullable|string|max:100',
            'customer_status' => [
                'sometimes',
                'required',
                'string',
                Rule::enum(CustomerStatusEnum::class),
            ],
            'customer_category_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:customer_categories,id',
            ],
            'customer_note' => 'sometimes|nullable|string',
            'payment_terms' => ['sometimes', 'required', Rule::enum(PaymentTermEnum::class)],
        ]);
    }


}
