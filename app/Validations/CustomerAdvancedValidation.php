<?php

namespace App\Validations;

use App\Enums\CustomerStatusEnum;
use App\Enums\PaymentTermEnum;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerAdvancedValidation
{
    public function validatePosSearch(Request $request): array
    {
        return $request->validate([
            'keyword' => ['required', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);
    }

    public function validateAddressDefaultRequest(Request $request): array
    {
        return $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'address_id' => ['required', 'integer', 'exists:customer_addresses,id'],
        ]);
    }

    public function validateCustomerSegmentation(Request $request): array
    {
        return $request->validate([
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:customer_tags,id'],
            'category_id' => ['nullable', 'integer', 'exists:customer_categories,id'],
            'status' => ['nullable', Rule::enum(CustomerStatusEnum::class)],
        ]);
    }

    public function validateTagIds(array $tagIds): array
    {
        return validator(
            ['tag_ids' => $tagIds],
            [
                'tag_ids' => ['required', 'array'],
                'tag_ids.*' => ['integer', 'exists:customer_tags,id'],
            ]
        )->validate();
    }

    public function validateCreditAmount(float $amount): void
    {
        validator(
            ['amount' => $amount],
            ['amount' => ['required', 'numeric', 'min:0.01']]
        )->validate();
    }

    public function validatePaymentTerm(Request $request): array
    {
        return $request->validate([
            'payment_terms' => ['required', Rule::enum(PaymentTermEnum::class)],
        ]);
    }
}
