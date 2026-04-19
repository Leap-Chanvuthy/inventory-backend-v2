<?php

namespace App\Service;

use App\Enums\AddressTypeEnum;
use App\Models\CustomerAddress;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerAddressService
{
    public function setDefaultAddress(int $customerId, int $addressId): CustomerAddress
    {
        return DB::transaction(function () use ($customerId, $addressId) {
            $address = CustomerAddress::query()
                ->where('customer_id', $customerId)
                ->lockForUpdate()
                ->findOrFail($addressId);

            $type = $address->type instanceof AddressTypeEnum
                ? $address->type->value
                : (string) $address->type;

            if ($type === '') {
                throw ValidationException::withMessages([
                    'type' => ['Address type is required to set default address.'],
                ]);
            }

            CustomerAddress::query()
                ->where('customer_id', $customerId)
                ->where('type', $type)
                ->where('id', '!=', $address->id)
                ->update(['is_default' => false]);

            $address->is_default = true;
            $address->save();

            return $address->refresh();
        });
    }
}
