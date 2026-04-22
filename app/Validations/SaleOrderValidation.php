<?php

namespace App\Validations;

use Illuminate\Http\Request;

class SaleOrderValidation
{
    public function createRules(Request $request): array
    {
        return [
            'customer_id' => 'nullable|exists:customers,id',
            'order_date' => 'required|date',
            'payment_status' => 'sometimes|required|string|in:PAID,UNPAID,DEBT',
            'note' => 'nullable|string',
            'tax_percentage' => 'sometimes|numeric|min:0|max:100',

            // If true, discount is read from customer category; otherwise user input is used.
            'use_customer_category_discount' => 'sometimes|boolean',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',

            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.note' => 'nullable|string',
        ];
    }

    public function updateRules(Request $request): array
    {
        return [
            'customer_id' => 'sometimes|nullable|exists:customers,id',
            'order_date' => 'sometimes|required|date',
            'payment_status' => 'sometimes|required|string|in:PAID,UNPAID,DEBT',
            'note' => 'sometimes|nullable|string',
            'tax_percentage' => 'sometimes|numeric|min:0|max:100',
            'use_customer_category_discount' => 'sometimes|boolean',
            'discount_percentage' => 'sometimes|nullable|numeric|min:0|max:100',

            'items' => 'sometimes|required|array|min:1',
            'items.*.product_id' => 'required_with:items|exists:products,id',
            'items.*.quantity' => 'required_with:items|numeric|min:0.0001',
            'items.*.note' => 'nullable|string',
        ];
    }

    public function updateStatusRules(Request $request): array
    {
        return [
            'order_status' => 'required|string|in:DRAFT,PROCESSING,ON_HOLD,CANCELLED,COMPLETED',
            'payment_status' => 'sometimes|string|in:PAID,UNPAID,DEBT',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'At least one sale order item is required.',
            'items.min' => 'At least one sale order item is required.',
            'items.*.product_id.exists' => 'One or more selected products do not exist.',
            'items.*.quantity.min' => 'Each item quantity must be greater than zero.',
            'order_status.required' => 'The order status is required.',
            'order_status.in' => 'The selected order must be one of the following: DRAFT, PROCESSING, ON_HOLD, CANCELLED, COMPLETED.',
            'payment_status.in' => 'The selected payment status must be one of the following: PAID, UNPAID, DEBT.',

        ];
    }
}