<?php

namespace App\Validations;

use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;
use App\Enums\PaymentMethodEnum;

class SupplierBankValidation
{
    public function validate(Request $request): array
    {
        return $request->validate([
            'banks' => ['nullable', 'array', 'max:4'],

            'banks.*.bank_name' => [
                'required',
                'distinct', // prevents duplicates in the same request
                new Enum(PaymentMethodEnum::class),
            ],
            'banks.*.account_number' => ['required', 'string', 'max:100'],
            'banks.*.account_holder_name' => ['required', 'string', 'max:255'],
            'banks.*.payment_link' => ['nullable', 'string', 'max:255'],
            'banks.*.qr_code_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],

            // do not allow client to send these
            'banks.*.supplier_id' => ['prohibited'],
            'banks.*.bank_label' => ['prohibited'],
        ]);
    }
}