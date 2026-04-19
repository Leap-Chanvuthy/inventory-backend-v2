<?php

namespace App\Service;

use App\Models\Customer;
use App\Validations\CustomerAdvancedValidation;
use Illuminate\Support\Facades\DB;

class CustomerTagService
{
    public function __construct(private readonly CustomerAdvancedValidation $validation)
    {
    }

    public function attachTags(int $customerId, array $tagIds): void
    {
        $validated = $this->validation->validateTagIds($tagIds);

        DB::transaction(function () use ($customerId, $validated) {
            $customer = Customer::query()->findOrFail($customerId);
            $customer->tags()->syncWithoutDetaching($validated['tag_ids']);
        });
    }

    public function syncTags(int $customerId, array $tagIds): void
    {
        $validated = $this->validation->validateTagIds($tagIds);

        DB::transaction(function () use ($customerId, $validated) {
            $customer = Customer::query()->findOrFail($customerId);
            $customer->tags()->sync($validated['tag_ids']);
        });
    }

    public function detachTag(int $customerId, int $tagId): void
    {
        DB::transaction(function () use ($customerId, $tagId) {
            $customer = Customer::query()->findOrFail($customerId);
            $customer->tags()->detach($tagId);
        });
    }
}
