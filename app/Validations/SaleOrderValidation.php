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
            'return_window_days' => 'sometimes|integer|min:1|max:3650',
            'payment_status' => 'sometimes|required|string|in:PAID,UNPAID,DEBT',
            'paid_amount_in_usd' => 'sometimes|numeric|min:0',
            'paid_amount_in_riel' => 'sometimes|numeric|min:0',
            'note' => 'nullable|string',
            'tax_percentage' => 'sometimes|numeric|min:0|max:100',

            // New API format.
            'discount_type' => 'sometimes|string|in:AUTO,MANUAL',
            'discount_value' => 'sometimes|nullable|numeric|min:0|max:100',

            // Legacy compatibility:
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
            'return_window_days' => 'sometimes|integer|min:1|max:3650',
            'payment_status' => 'sometimes|required|string|in:PAID,UNPAID,DEBT',
            'paid_amount_in_usd' => 'sometimes|numeric|min:0',
            'paid_amount_in_riel' => 'sometimes|numeric|min:0',
            'note' => 'sometimes|nullable|string',
            'tax_percentage' => 'sometimes|numeric|min:0|max:100',
            'discount_type' => 'sometimes|string|in:AUTO,MANUAL',
            'discount_value' => 'sometimes|nullable|numeric|min:0|max:100',
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
            'paid_amount_in_usd' => 'sometimes|numeric|min:0',
            'paid_amount_in_riel' => 'sometimes|numeric|min:0',
        ];
    }

    public function refundRules(Request $request): array
    {
        return [
            'refund_type' => 'sometimes|string|in:CASH_REFUND,PARTIAL_REFUND,DISCOUNT_COMPENSATION',
            'refund_method' => 'sometimes|string|in:CASH,BANK_TRANSFER,STORE_CREDIT,DISCOUNT_COMPENSATION',
            'reason_type' => 'required|string|in:PRODUCT_ISSUE,CUSTOMER_SATISFACTION,COMPENSATION,OTHER',
            'reason' => 'required|string',
            'processed_at' => 'sometimes|date',
            'movement_date' => 'sometimes|date',
            'note' => 'sometimes|nullable|string',
            'items' => 'required|array|min:1',
            'items.*.sale_order_item_id' => 'sometimes|integer|exists:sale_order_items,id',
            'items.*.product_id' => 'sometimes|integer|exists:products,id',
            'items.*.quantity' => 'sometimes|numeric|min:0.0001',
            'items.*.refund_quantity' => 'sometimes|numeric|min:0.0001',
            'items.*.process_return' => 'sometimes|boolean',
            'items.*.process_refund' => 'sometimes|boolean',
            'items.*.is_resellable' => 'sometimes|boolean',
            'items.*.return_action' => 'sometimes|string|in:RETURN_TO_STOCK,SCRAP,NO_RETURN',
            'items.*.refund_action' => 'sometimes|string|in:RETURN_TO_STOCK,SCRAP',
            'items.*.refund_percentage' => 'sometimes|numeric|min:0|max:100',
            'items.*.refund_amount_override' => 'sometimes|numeric|min:0',
            'items.*.refund_amount_override_in_usd' => 'sometimes|numeric|min:0',
            'items.*.refund_amount_override_in_riel' => 'sometimes|numeric|min:0',
            'items.*.reason' => 'sometimes|nullable|string',
            'items.*.note' => 'sometimes|nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'At least one sale order item is required.',
            'items.min' => 'At least one sale order item is required.',
            'items.*.product_id.exists' => 'One or more selected products do not exist.',
            'items.*.quantity.min' => 'Each item quantity must be greater than zero.',
            'discount_type.in' => 'Discount type must be either AUTO or MANUAL.',
            'discount_value.max' => 'Discount value must be between 0 and 100.',
            'items.*.refund_quantity.min' => 'Refund quantity must be greater than zero.',
            'items.*.refund_action.in' => 'Refund action must be RETURN_TO_STOCK or SCRAP.',
            'refund_type.in' => 'Refund type must be CASH_REFUND, PARTIAL_REFUND, or DISCOUNT_COMPENSATION.',
            'refund_method.in' => 'Refund method must be CASH, BANK_TRANSFER, STORE_CREDIT, or DISCOUNT_COMPENSATION.',
            'reason_type.in' => 'Reason type must be PRODUCT_ISSUE, CUSTOMER_SATISFACTION, COMPENSATION, or OTHER.',
            'order_status.required' => 'The order status is required.',
            'order_status.in' => 'The selected order must be one of the following: DRAFT, PROCESSING, ON_HOLD, CANCELLED, COMPLETED.',
            'payment_status.in' => 'The selected payment status must be one of the following: PAID, UNPAID, DEBT.',

        ];
    }
}
