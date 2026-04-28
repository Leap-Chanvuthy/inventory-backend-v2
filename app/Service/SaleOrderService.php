<?php

namespace App\Service;

use App\Enums\PaymentStatusEnum;
use App\Enums\ProductStatusEnum;
use App\Enums\ProductStockMovementTypeEnum;
use App\Enums\SaleOrderStatusEnum;
use App\Enums\StockDirectionEnum;
use App\Helpers\GetCurrentUserHelper;
use App\Helpers\ResponseHelper;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductMovement;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\SaleOrderRefund;
use App\Models\SaleOrderRefundItem;
use App\QueryBuilders\SaleOrderQueryBuilder;
use App\Validations\SaleOrderValidation;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SaleOrderService
{
    private const FLOAT_EPSILON = 0.000001;
    private const DEFAULT_RETURN_WINDOW_DAYS = 30;

    protected SaleOrderValidation $saleOrderValidation;
    protected SaleOrderQueryBuilder $saleOrderQueryBuilder;
    protected GetCurrentUserHelper $getCurrentUserHelper;
    protected ProductStockDeductionService $stockDeductionService;

    public function __construct(
        SaleOrderValidation $saleOrderValidation,
        SaleOrderQueryBuilder $saleOrderQueryBuilder,
        GetCurrentUserHelper $getCurrentUserHelper,
        ProductStockDeductionService $stockDeductionService
    ) {
        $this->saleOrderValidation = $saleOrderValidation;
        $this->saleOrderQueryBuilder = $saleOrderQueryBuilder;
        $this->getCurrentUserHelper = $getCurrentUserHelper;
        $this->stockDeductionService = $stockDeductionService;
    }

    public function index(Request $request)
    {
        try {
            $data = $this->saleOrderQueryBuilder->saleOrderBuilder($request);
            return ResponseHelper::success($data);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function statistics(Request $request)
    {
        try {
            $query = SaleOrder::query();
            $dateFrom = $request->query('date_from');
            $dateTo = $request->query('date_to');

            if (!empty($dateFrom)) {
                $query->whereDate('created_at', '>=', $dateFrom);
            }

            if (!empty($dateTo)) {
                $query->whereDate('created_at', '<=', $dateTo);
            }

            $stats = (clone $query)
                ->selectRaw('COUNT(*) as total_orders')
                ->selectRaw('SUM(CASE WHEN order_status = ? THEN 1 ELSE 0 END) as total_draft', [SaleOrderStatusEnum::DRAFT->value])
                ->selectRaw('SUM(CASE WHEN order_status = ? THEN 1 ELSE 0 END) as total_processing', [SaleOrderStatusEnum::PROCESSING->value])
                ->selectRaw('SUM(CASE WHEN order_status = ? THEN 1 ELSE 0 END) as total_on_hold', [SaleOrderStatusEnum::ON_HOLD->value])
                ->selectRaw('SUM(CASE WHEN order_status = ? THEN 1 ELSE 0 END) as total_completed', [SaleOrderStatusEnum::COMPLETED->value])
                ->selectRaw('SUM(CASE WHEN order_status = ? THEN 1 ELSE 0 END) as total_refunded', [SaleOrderStatusEnum::REFUNDED->value])
                ->selectRaw('COALESCE(SUM(CASE WHEN order_status IN (?, ?) THEN (grand_total_amount_in_usd - total_refunded_amount_in_usd) ELSE 0 END), 0) as total_earning_usd', [
                    SaleOrderStatusEnum::COMPLETED->value,
                    SaleOrderStatusEnum::REFUNDED->value,
                ])
                ->selectRaw('COALESCE(SUM(CASE WHEN order_status IN (?, ?) THEN (grand_total_amount_in_riel - total_refunded_amount_in_riel) ELSE 0 END), 0) as total_earning_riel', [
                    SaleOrderStatusEnum::COMPLETED->value,
                    SaleOrderStatusEnum::REFUNDED->value,
                ])
                ->first();

            return ResponseHelper::success([
                'total_draft' => (int) ($stats?->total_draft ?? 0),
                'total_processing' => (int) ($stats?->total_processing ?? 0),
                'total_on_hold' => (int) ($stats?->total_on_hold ?? 0),
                'total_completed' => (int) ($stats?->total_completed ?? 0),
                'total_refunded' => (int) ($stats?->total_refunded ?? 0),
                'total_earning_usd' => round((float) ($stats?->total_earning_usd ?? 0), 2),
                'total_earning_riel' => round((float) ($stats?->total_earning_riel ?? 0), 2),
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function show(int $id)
    {
        try {
            $saleOrder = SaleOrder::with([
                'customer' => fn ($q) => $q->withTrashed()->with('customerCategory'),
                'orderItems.product' => fn ($q) => $q->withTrashed(),
                'refunds.items.saleOrderItem.product' => fn ($q) => $q->withTrashed(),
                'refunds.processedBy',
            ])->findOrFail($id);

            return ResponseHelper::success(['sale_order' => $saleOrder]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function getRefunds(int $id)
    {
        try {
            $saleOrder = SaleOrder::findOrFail($id);

            $refunds = SaleOrderRefund::with([
                'items.saleOrderItem.product' => fn ($q) => $q->withTrashed(),
                'processedBy',
            ])
                ->where('sale_order_id', $saleOrder->id)
                ->orderByDesc('processed_at')
                ->orderByDesc('id')
                ->get();

            return ResponseHelper::success([
                'sale_order_id' => (int) $saleOrder->id,
                'refunds' => $refunds,
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function getStockAvailability(int $productId)
    {
        try {
            Product::findOrFail($productId);
            $availableStock = $this->stockDeductionService->getAvailableStock($productId);
            return ResponseHelper::success(['available_stock' => $availableStock]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make(
                $request->all(),
                $this->saleOrderValidation->createRules($request),
                $this->saleOrderValidation->messages()
            );

            if ($validator->fails()) {
                return ResponseHelper::validation($validator->errors()->toArray(), 'Validation Error');
            }

            $validated = $validator->validated();
            $userId = $this->getCurrentUserHelper->getUserId();

            $pricedItemsResult = $this->buildPricedItems($validated['items']);
            $discountPercentage = $this->resolveDiscountPercentageFromPayload(
                $validated,
                $validated['customer_id'] ?? null,
                null
            );
            $taxPercentage = round((float) ($validated['tax_percentage'] ?? 0), 2);

            $totals = $this->buildOrderTotals(
                $pricedItemsResult['sub_total_in_usd'],
                $pricedItemsResult['sub_total_in_riel'],
                $discountPercentage,
                $taxPercentage
            );

            $saleOrder = DB::transaction(function () use ($validated, $pricedItemsResult, $totals, $userId) {
                $orderDate = Carbon::parse((string) $validated['order_date']);
                $returnWindowDays = max(1, (int) ($validated['return_window_days'] ?? self::DEFAULT_RETURN_WINDOW_DAYS));
                $returnValidUntil = $orderDate->copy()->addDays($returnWindowDays)->endOfDay();

                $paymentSnapshot = $this->buildPaymentSnapshot(
                    grandTotalInUsd: (float) $totals['grand_total_amount_in_usd'],
                    grandTotalInRiel: (float) $totals['grand_total_amount_in_riel'],
                    paidAmountInUsd: (float) ($validated['paid_amount_in_usd'] ?? 0),
                    paidAmountInRiel: (float) ($validated['paid_amount_in_riel'] ?? 0),
                    refundedAmountInUsd: 0,
                    refundedAmountInRiel: 0,
                    paymentStatusInput: $validated['payment_status'] ?? null
                );

                $saleOrder = SaleOrder::create([
                    'order_no' => $this->generateOrderNo(),
                    'customer_id' => $validated['customer_id'] ?? null,
                    'order_date' => $validated['order_date'],
                    'return_window_days' => $returnWindowDays,
                    'return_valid_until' => $returnValidUntil,
                    'order_status' => SaleOrderStatusEnum::DRAFT->value,
                    'payment_status' => $paymentSnapshot['payment_status'],
                    'paid_amount_in_usd' => $paymentSnapshot['paid_amount_in_usd'],
                    'paid_amount_in_riel' => $paymentSnapshot['paid_amount_in_riel'],
                    'remaining_balance_in_usd' => $paymentSnapshot['remaining_balance_in_usd'],
                    'remaining_balance_in_riel' => $paymentSnapshot['remaining_balance_in_riel'],
                    'total_refunded_amount_in_usd' => 0,
                    'total_refunded_amount_in_riel' => 0,
                    'note' => $validated['note'] ?? null,
                    'created_by' => $userId,
                    'last_updated_by' => $userId,
                    'tax_percentage' => $totals['tax_percentage'],
                    'tax_amount_in_usd' => $totals['tax_amount_in_usd'],
                    'tax_amount_in_riel' => $totals['tax_amount_in_riel'],
                    'sub_total_in_usd' => $totals['sub_total_in_usd'],
                    'sub_total_in_riel' => $totals['sub_total_in_riel'],
                    'grand_total_amount_in_usd' => $totals['grand_total_amount_in_usd'],
                    'grand_total_amount_in_riel' => $totals['grand_total_amount_in_riel'],
                    'discount_percentage' => $totals['discount_percentage'],
                    'discount_amount' => $totals['discount_amount'],
                ]);

                foreach ($pricedItemsResult['items'] as $item) {
                    SaleOrderItem::create([
                        'sale_order_id' => $saleOrder->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'returned_quantity' => 0,
                        'refund_quantity' => 0,
                        'unit_price_in_usd' => $item['unit_price_in_usd'],
                        'unit_price_in_riel' => $item['unit_price_in_riel'],
                        'total_price_in_usd' => $item['total_price_in_usd'],
                        'total_price_in_riel' => $item['total_price_in_riel'],
                        'exchange_rate_from_usd_to_riel' => $item['exchange_rate_from_usd_to_riel'],
                        'exchange_rate_from_riel_to_usd' => $item['exchange_rate_from_riel_to_usd'],
                        'note' => $item['note'] ?? null,
                    ]);
                }

                return $saleOrder;
            });

            return ResponseHelper::success([
                'sale_order' => $saleOrder->load([
                    'customer.customerCategory',
                    'orderItems.product',
                ]),
            ], 'Sale order created successfully', 201);
        } catch (ValidationException $e) {
            return ResponseHelper::validation($e->errors(), 'Validation Error');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function update(Request $request, int $id)
    {
        try {
            $saleOrder = SaleOrder::with('orderItems')->findOrFail($id);
            $currentStatus = $this->normalizeStatus($saleOrder->order_status);

            if (in_array($currentStatus, [SaleOrderStatusEnum::CANCELLED->value, SaleOrderStatusEnum::REFUNDED->value], true)) {
                return ResponseHelper::error(
                    'Sale order is locked for editing.',
                    422,
                    ['order_status' => ['Cancelled and refunded sale orders are not editable.']]
                );
            }

            $validator = Validator::make(
                $request->all(),
                $this->saleOrderValidation->updateRules($request),
                $this->saleOrderValidation->messages()
            );

            if ($validator->fails()) {
                return ResponseHelper::validation($validator->errors()->toArray(), 'Validation Error');
            }

            $validated = $validator->validated();
            $userId = $this->getCurrentUserHelper->getUserId();

            if ($currentStatus !== SaleOrderStatusEnum::DRAFT->value) {
                $allowedPaymentOnly = ['payment_status', 'paid_amount_in_usd', 'paid_amount_in_riel'];
                $invalidFields = array_values(array_diff(array_keys($validated), $allowedPaymentOnly));

                if (!empty($invalidFields)) {
                    return ResponseHelper::error(
                        'Only payment fields are editable for this sale order status.',
                        422,
                        ['fields' => $invalidFields]
                    );
                }

                $updated = DB::transaction(function () use ($saleOrder, $validated, $userId) {
                    $snapshot = $this->buildPaymentSnapshot(
                        grandTotalInUsd: (float) $saleOrder->grand_total_amount_in_usd,
                        grandTotalInRiel: (float) $saleOrder->grand_total_amount_in_riel,
                        paidAmountInUsd: array_key_exists('paid_amount_in_usd', $validated)
                            ? (float) $validated['paid_amount_in_usd']
                            : (float) $saleOrder->paid_amount_in_usd,
                        paidAmountInRiel: array_key_exists('paid_amount_in_riel', $validated)
                            ? (float) $validated['paid_amount_in_riel']
                            : (float) $saleOrder->paid_amount_in_riel,
                        refundedAmountInUsd: (float) $saleOrder->total_refunded_amount_in_usd,
                        refundedAmountInRiel: (float) $saleOrder->total_refunded_amount_in_riel,
                        paymentStatusInput: $validated['payment_status'] ?? null
                    );

                    $saleOrder->update([
                        'payment_status' => $snapshot['payment_status'],
                        'paid_amount_in_usd' => $snapshot['paid_amount_in_usd'],
                        'paid_amount_in_riel' => $snapshot['paid_amount_in_riel'],
                        'remaining_balance_in_usd' => $snapshot['remaining_balance_in_usd'],
                        'remaining_balance_in_riel' => $snapshot['remaining_balance_in_riel'],
                        'last_updated_by' => $userId,
                    ]);

                    return $saleOrder->fresh([
                        'customer.customerCategory',
                        'orderItems.product',
                        'refunds.items',
                    ]);
                });

                return ResponseHelper::success(['sale_order' => $updated], 'Sale order payment updated successfully');
            }

            $updatedSaleOrder = DB::transaction(function () use ($saleOrder, $validated, $userId) {
                $subTotalInUsd = (float) $saleOrder->sub_total_in_usd;
                $subTotalInRiel = (float) $saleOrder->sub_total_in_riel;

                if (array_key_exists('items', $validated)) {
                    $pricedItemsResult = $this->buildPricedItems($validated['items']);
                    $subTotalInUsd = $pricedItemsResult['sub_total_in_usd'];
                    $subTotalInRiel = $pricedItemsResult['sub_total_in_riel'];

                    SaleOrderItem::where('sale_order_id', $saleOrder->id)->delete();
                    foreach ($pricedItemsResult['items'] as $item) {
                        SaleOrderItem::create([
                            'sale_order_id' => $saleOrder->id,
                            'product_id' => $item['product_id'],
                            'quantity' => $item['quantity'],
                            'returned_quantity' => 0,
                            'refund_quantity' => 0,
                            'unit_price_in_usd' => $item['unit_price_in_usd'],
                            'unit_price_in_riel' => $item['unit_price_in_riel'],
                            'total_price_in_usd' => $item['total_price_in_usd'],
                            'total_price_in_riel' => $item['total_price_in_riel'],
                            'exchange_rate_from_usd_to_riel' => $item['exchange_rate_from_usd_to_riel'],
                            'exchange_rate_from_riel_to_usd' => $item['exchange_rate_from_riel_to_usd'],
                            'note' => $item['note'] ?? null,
                        ]);
                    }
                }

                $customerId = array_key_exists('customer_id', $validated)
                    ? ($validated['customer_id'] ?? null)
                    : $saleOrder->customer_id;

                $discountPercentage = $this->resolveDiscountPercentageFromPayload(
                    $validated,
                    $customerId,
                    (float) $saleOrder->discount_percentage
                );

                $taxPercentage = array_key_exists('tax_percentage', $validated)
                    ? round((float) $validated['tax_percentage'], 2)
                    : (float) $saleOrder->tax_percentage;

                $totals = $this->buildOrderTotals(
                    $subTotalInUsd,
                    $subTotalInRiel,
                    $discountPercentage,
                    $taxPercentage
                );

                $returnWindowDays = array_key_exists('return_window_days', $validated)
                    ? max(1, (int) $validated['return_window_days'])
                    : (int) ($saleOrder->return_window_days ?: self::DEFAULT_RETURN_WINDOW_DAYS);

                $nextOrderDate = array_key_exists('order_date', $validated)
                    ? (string) $validated['order_date']
                    : (string) $saleOrder->order_date;

                $returnValidUntil = Carbon::parse($nextOrderDate)->addDays($returnWindowDays)->endOfDay();

                $snapshot = $this->buildPaymentSnapshot(
                    grandTotalInUsd: (float) $totals['grand_total_amount_in_usd'],
                    grandTotalInRiel: (float) $totals['grand_total_amount_in_riel'],
                    paidAmountInUsd: array_key_exists('paid_amount_in_usd', $validated)
                        ? (float) $validated['paid_amount_in_usd']
                        : (float) $saleOrder->paid_amount_in_usd,
                    paidAmountInRiel: array_key_exists('paid_amount_in_riel', $validated)
                        ? (float) $validated['paid_amount_in_riel']
                        : (float) $saleOrder->paid_amount_in_riel,
                    refundedAmountInUsd: (float) $saleOrder->total_refunded_amount_in_usd,
                    refundedAmountInRiel: (float) $saleOrder->total_refunded_amount_in_riel,
                    paymentStatusInput: $validated['payment_status'] ?? null
                );

                $saleOrder->update([
                    'customer_id' => $customerId,
                    'order_date' => $nextOrderDate,
                    'return_window_days' => $returnWindowDays,
                    'return_valid_until' => $returnValidUntil,
                    'payment_status' => $snapshot['payment_status'],
                    'paid_amount_in_usd' => $snapshot['paid_amount_in_usd'],
                    'paid_amount_in_riel' => $snapshot['paid_amount_in_riel'],
                    'remaining_balance_in_usd' => $snapshot['remaining_balance_in_usd'],
                    'remaining_balance_in_riel' => $snapshot['remaining_balance_in_riel'],
                    'note' => $validated['note'] ?? $saleOrder->note,
                    'last_updated_by' => $userId,
                    'tax_percentage' => $totals['tax_percentage'],
                    'tax_amount_in_usd' => $totals['tax_amount_in_usd'],
                    'tax_amount_in_riel' => $totals['tax_amount_in_riel'],
                    'sub_total_in_usd' => $totals['sub_total_in_usd'],
                    'sub_total_in_riel' => $totals['sub_total_in_riel'],
                    'grand_total_amount_in_usd' => $totals['grand_total_amount_in_usd'],
                    'grand_total_amount_in_riel' => $totals['grand_total_amount_in_riel'],
                    'discount_percentage' => $totals['discount_percentage'],
                    'discount_amount' => $totals['discount_amount'],
                ]);

                return $saleOrder->fresh([
                    'customer.customerCategory',
                    'orderItems.product',
                    'refunds.items',
                ]);
            });

            return ResponseHelper::success(['sale_order' => $updatedSaleOrder], 'Sale order updated successfully');
        } catch (ValidationException $e) {
            return ResponseHelper::validation($e->errors(), 'Validation Error');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function updateStatus(Request $request, int $id)
    {
        try {
            $saleOrder = SaleOrder::with('orderItems')->findOrFail($id);

            $validator = Validator::make(
                $request->all(),
                $this->saleOrderValidation->updateStatusRules($request),
                $this->saleOrderValidation->messages()
            );

            if ($validator->fails()) {
                return ResponseHelper::validation($validator->errors()->toArray(), 'Validation Error');
            }

            $validated = $validator->validated();
            $userId = $this->getCurrentUserHelper->getUserId();
            $currentStatus = $this->normalizeStatus($saleOrder->order_status);
            $targetStatus = strtoupper((string) $validated['order_status']);

            $lockedStatuses = [
                SaleOrderStatusEnum::COMPLETED->value,
                SaleOrderStatusEnum::CANCELLED->value,
                SaleOrderStatusEnum::REFUNDED->value,
            ];

            if (in_array($currentStatus, $lockedStatuses, true)) {
                return ResponseHelper::error(
                    'Sale order is locked for status updates.',
                    422,
                    ['order_status' => ['Completed, refunded, and cancelled orders are fully locked.']]
                );
            }

            $this->assertValidStatusTransition($currentStatus, $targetStatus);

            if (
                $currentStatus !== SaleOrderStatusEnum::COMPLETED->value &&
                $targetStatus === SaleOrderStatusEnum::COMPLETED->value
            ) {
                $items = $saleOrder->orderItems->map(function (SaleOrderItem $item) {
                    return [
                        'product_id' => (int) $item->product_id,
                        'quantity' => (float) $item->quantity,
                        'unit_price_in_usd' => (float) $item->unit_price_in_usd,
                        'unit_price_in_riel' => (float) $item->unit_price_in_riel,
                        'exchange_rate_from_usd_to_riel' => (float) $item->exchange_rate_from_usd_to_riel,
                        'exchange_rate_from_riel_to_usd' => (float) $item->exchange_rate_from_riel_to_usd,
                    ];
                })->values()->all();

                $stockItems = $this->aggregateItemsForStock($items);
                $shortfalls = $this->stockDeductionService->validateSufficientStock($stockItems);
                if (!empty($shortfalls)) {
                    return ResponseHelper::error('Insufficient product stock', 422, $shortfalls);
                }
            }

            $updatedSaleOrder = DB::transaction(function () use ($saleOrder, $targetStatus, $userId, $validated) {
                $currentStatus = $this->normalizeStatus($saleOrder->order_status);

                if (
                    $currentStatus !== SaleOrderStatusEnum::COMPLETED->value &&
                    $targetStatus === SaleOrderStatusEnum::COMPLETED->value
                ) {
                    $items = $saleOrder->orderItems->map(function (SaleOrderItem $item) {
                        return [
                            'product_id' => (int) $item->product_id,
                            'quantity' => (float) $item->quantity,
                            'unit_price_in_usd' => (float) $item->unit_price_in_usd,
                            'unit_price_in_riel' => (float) $item->unit_price_in_riel,
                            'exchange_rate_from_usd_to_riel' => (float) $item->exchange_rate_from_usd_to_riel,
                            'exchange_rate_from_riel_to_usd' => (float) $item->exchange_rate_from_riel_to_usd,
                        ];
                    })->values()->all();

                    $this->applyCompletionMovements($saleOrder, $items, $userId);
                }

                if ($targetStatus === SaleOrderStatusEnum::CANCELLED->value) {
                    $token = $this->stockDeductionService->buildSaleOrderToken((int) $saleOrder->id);
                    $productIds = $this->stockDeductionService->deleteSaleOrderMovementsByToken($token);
                    $this->stockDeductionService->rebuildIsSoldFlags($productIds);
                }

                $snapshot = $this->buildPaymentSnapshot(
                    grandTotalInUsd: (float) $saleOrder->grand_total_amount_in_usd,
                    grandTotalInRiel: (float) $saleOrder->grand_total_amount_in_riel,
                    paidAmountInUsd: array_key_exists('paid_amount_in_usd', $validated)
                        ? (float) $validated['paid_amount_in_usd']
                        : (float) $saleOrder->paid_amount_in_usd,
                    paidAmountInRiel: array_key_exists('paid_amount_in_riel', $validated)
                        ? (float) $validated['paid_amount_in_riel']
                        : (float) $saleOrder->paid_amount_in_riel,
                    refundedAmountInUsd: (float) $saleOrder->total_refunded_amount_in_usd,
                    refundedAmountInRiel: (float) $saleOrder->total_refunded_amount_in_riel,
                    paymentStatusInput: $validated['payment_status'] ?? null
                );

                $saleOrder->update([
                    'order_status' => $targetStatus,
                    'payment_status' => $snapshot['payment_status'],
                    'paid_amount_in_usd' => $snapshot['paid_amount_in_usd'],
                    'paid_amount_in_riel' => $snapshot['paid_amount_in_riel'],
                    'remaining_balance_in_usd' => $snapshot['remaining_balance_in_usd'],
                    'remaining_balance_in_riel' => $snapshot['remaining_balance_in_riel'],
                    'last_updated_by' => $userId,
                ]);

                return $saleOrder->fresh([
                    'customer.customerCategory',
                    'orderItems.product',
                    'refunds.items',
                ]);
            });

            return ResponseHelper::success(['sale_order' => $updatedSaleOrder], 'Sale order status updated successfully');
        } catch (ValidationException $e) {
            return ResponseHelper::validation($e->errors(), 'Validation Error');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function refund(Request $request, int $id)
    {
        try {
            $saleOrder = SaleOrder::with([
                'orderItems.product' => fn ($q) => $q->withTrashed(),
                'customer' => fn ($q) => $q->withTrashed()->with('customerCategory'),
            ])->findOrFail($id);

            $currentStatus = $this->normalizeStatus($saleOrder->order_status);
            if ($currentStatus !== SaleOrderStatusEnum::COMPLETED->value) {
                return ResponseHelper::error(
                    'Only completed orders can be refunded.',
                    422,
                    ['order_status' => ['Refund is only allowed when order status is COMPLETED.']]
                );
            }

            $validator = Validator::make(
                $request->all(),
                $this->saleOrderValidation->refundRules($request),
                $this->saleOrderValidation->messages()
            );

            if ($validator->fails()) {
                return ResponseHelper::validation($validator->errors()->toArray(), 'Validation Error');
            }

            $validated = $validator->validated();
            $processedAt = (string) ($validated['processed_at'] ?? now()->toDateTimeString());
            $movementDate = (string) ($validated['movement_date'] ?? $processedAt);
            $userId = $this->getCurrentUserHelper->getUserId();
            $token = $this->stockDeductionService->buildSaleOrderToken((int) $saleOrder->id);
            $errors = [];

            $updatedSaleOrder = DB::transaction(function () use (
                $saleOrder,
                $validated,
                $processedAt,
                $movementDate,
                $userId,
                $token,
                &$errors
            ) {
                $refund = SaleOrderRefund::create([
                    'sale_order_id' => (int) $saleOrder->id,
                    'refund_no' => $this->generateRefundNo(),
                    'refund_type' => strtoupper((string) ($validated['refund_type'] ?? 'CASH_REFUND')),
                    'refund_method' => strtoupper((string) ($validated['refund_method'] ?? 'CASH')),
                    'reason_type' => strtoupper((string) ($validated['reason_type'] ?? 'OTHER')),
                    'reason' => (string) ($validated['reason'] ?? ''),
                    'note' => $validated['note'] ?? null,
                    'processed_at' => $processedAt,
                    'processed_by' => $userId,
                    'total_refund_amount_in_usd' => 0,
                    'total_refund_amount_in_riel' => 0,
                ]);

                $totalRefundUsd = 0;
                $totalRefundRiel = 0;

                foreach ($validated['items'] as $index => $refundItem) {
                    $lineItem = $this->resolveRefundLineItem($saleOrder, $refundItem);
                    if (!$lineItem) {
                        $errors["items.{$index}"][] = 'Sale order item was not found in this order.';
                        continue;
                    }

                    $requestedQty = (float) ($refundItem['quantity'] ?? $refundItem['refund_quantity'] ?? 0);
                    if ($requestedQty <= 0) {
                        $errors["items.{$index}.quantity"][] = 'Quantity must be greater than zero.';
                        continue;
                    }

                    $processReturn = (bool) ($refundItem['process_return'] ?? true);
                    $processRefund = (bool) ($refundItem['process_refund'] ?? true);

                    if (!$processReturn && !$processRefund) {
                        $errors["items.{$index}"][] = 'At least one action is required: return and/or refund.';
                        continue;
                    }

                    $rawReturnAction = strtoupper((string) (
                        $refundItem['return_action']
                        ?? $refundItem['refund_action']
                        ?? ($processReturn ? 'RETURN_TO_STOCK' : 'NO_RETURN')
                    ));

                    $returnAction = $processReturn ? $rawReturnAction : 'NO_RETURN';
                    if ($processReturn && !in_array($returnAction, ['RETURN_TO_STOCK', 'SCRAP'], true)) {
                        $errors["items.{$index}.return_action"][] = 'Return action must be RETURN_TO_STOCK or SCRAP when return is enabled.';
                        continue;
                    }

                    if (
                        $processReturn &&
                        $this->isReturnWindowExpired($saleOrder, $processedAt)
                    ) {
                        $errors["items.{$index}.return_window"][] = 'Return window has expired for this sale order.';
                        continue;
                    }

                    if (
                        !$processReturn &&
                        $processRefund &&
                        strtoupper((string) ($validated['reason_type'] ?? 'OTHER')) === 'CUSTOMER_SATISFACTION' &&
                        $this->isReturnWindowExpired($saleOrder, $processedAt)
                    ) {
                        $errors["items.{$index}.refund_window"][] = 'Customer-satisfaction refund is not allowed after return expiry.';
                        continue;
                    }

                    $purchasedQty = (float) ($lineItem->quantity ?? 0);
                    $currentReturnedQty = (float) ($lineItem->returned_quantity ?? 0);
                    $currentRefundQty = (float) ($lineItem->refund_quantity ?? 0);

                    $returnableQty = max(0, $purchasedQty - $currentReturnedQty);
                    $refundableQty = max(0, $purchasedQty - $currentRefundQty);

                    if ($processReturn && $requestedQty > $returnableQty + self::FLOAT_EPSILON) {
                        $errors["items.{$index}.quantity"][] = "Requested return exceeds returnable quantity ({$returnableQty}).";
                        continue;
                    }

                    if ($processRefund && $requestedQty > $refundableQty + self::FLOAT_EPSILON) {
                        $errors["items.{$index}.quantity"][] = "Requested refund exceeds refundable quantity ({$refundableQty}).";
                        continue;
                    }

                    $isResellable = array_key_exists('is_resellable', $refundItem)
                        ? (bool) $refundItem['is_resellable']
                        : ($returnAction !== 'SCRAP');

                    $refundPercentage = $processRefund
                        ? round(max(0, min((float) ($refundItem['refund_percentage'] ?? 100), 100)), 2)
                        : 0;

                    $refundAmounts = $processRefund
                        ? $this->calculateRefundAmounts(
                            lineItem: $lineItem,
                            quantity: $requestedQty,
                            discountPercentage: (float) $saleOrder->discount_percentage,
                            taxPercentage: (float) $saleOrder->tax_percentage,
                            refundPercentage: $refundPercentage,
                            overrideUsd: array_key_exists('refund_amount_override_in_usd', $refundItem)
                                ? (float) $refundItem['refund_amount_override_in_usd']
                                : (array_key_exists('refund_amount_override', $refundItem)
                                    ? (float) $refundItem['refund_amount_override']
                                    : null),
                            overrideRiel: array_key_exists('refund_amount_override_in_riel', $refundItem)
                                ? (float) $refundItem['refund_amount_override_in_riel']
                                : null
                        )
                        : ['amount_in_usd' => 0.0, 'amount_in_riel' => 0.0];

                    $itemReason = $refundItem['reason'] ?? ($validated['reason'] ?? null);
                    $itemNote = $refundItem['note'] ?? ($validated['note'] ?? null);
                    $contextNote = "SALE_ORDER_REFUND | {$token} | REFUND_NO:{$refund->refund_no} | SALE_ORDER_ITEM_ID:{$lineItem->id}";

                    if ($processReturn) {
                        $this->createReturnFromCustomerMovement(
                            lineItem: $lineItem,
                            quantity: $requestedQty,
                            movementDate: $movementDate,
                            userId: $userId,
                            note: $itemNote
                                ? "{$contextNote} | NOTE: {$itemNote}"
                                : "{$contextNote} | ACTION: {$returnAction}"
                        );

                        if ($returnAction === 'SCRAP') {
                            $this->createScrapMovementFromRefund(
                                lineItem: $lineItem,
                                quantity: $requestedQty,
                                movementDate: $movementDate,
                                userId: $userId,
                                note: $itemNote
                                    ?: "{$contextNote} | Returned item damaged / expired / not resellable"
                            );
                        }

                        $lineItem->returned_quantity = round($currentReturnedQty + $requestedQty, 4);
                    }

                    if ($processRefund) {
                        $lineItem->refund_quantity = round($currentRefundQty + $requestedQty, 4);
                        $totalRefundUsd += (float) $refundAmounts['amount_in_usd'];
                        $totalRefundRiel += (float) $refundAmounts['amount_in_riel'];
                    }

                    $lineItem->save();

                    SaleOrderRefundItem::create([
                        'sale_order_refund_id' => (int) $refund->id,
                        'sale_order_item_id' => (int) $lineItem->id,
                        'quantity' => $requestedQty,
                        'process_return' => $processReturn,
                        'process_refund' => $processRefund,
                        'is_resellable' => $processReturn ? $isResellable : null,
                        'return_action' => $returnAction,
                        'refund_percentage' => $refundPercentage,
                        'refund_amount_in_usd' => (float) $refundAmounts['amount_in_usd'],
                        'refund_amount_in_riel' => (float) $refundAmounts['amount_in_riel'],
                        'reason' => $itemReason,
                        'note' => $itemNote,
                    ]);
                }

                if (!empty($errors)) {
                    throw ValidationException::withMessages($errors);
                }

                $refund->update([
                    'total_refund_amount_in_usd' => round($totalRefundUsd, 2),
                    'total_refund_amount_in_riel' => round($totalRefundRiel, 2),
                ]);

                $newRefundUsd = round((float) $saleOrder->total_refunded_amount_in_usd + $totalRefundUsd, 2);
                $newRefundRiel = round((float) $saleOrder->total_refunded_amount_in_riel + $totalRefundRiel, 2);

                $snapshot = $this->buildPaymentSnapshot(
                    grandTotalInUsd: (float) $saleOrder->grand_total_amount_in_usd,
                    grandTotalInRiel: (float) $saleOrder->grand_total_amount_in_riel,
                    paidAmountInUsd: (float) $saleOrder->paid_amount_in_usd,
                    paidAmountInRiel: (float) $saleOrder->paid_amount_in_riel,
                    refundedAmountInUsd: $newRefundUsd,
                    refundedAmountInRiel: $newRefundRiel,
                    paymentStatusInput: null
                );

                $allFullyRefunded = $saleOrder->orderItems()
                    ->get()
                    ->every(function (SaleOrderItem $item) {
                        $purchasedQty = (float) ($item->quantity ?? 0);
                        $refundedQty = (float) ($item->refund_quantity ?? 0);
                        return $refundedQty + self::FLOAT_EPSILON >= $purchasedQty;
                    });

                $saleOrder->update([
                    'order_status' => $allFullyRefunded
                        ? SaleOrderStatusEnum::REFUNDED->value
                        : SaleOrderStatusEnum::COMPLETED->value,
                    'payment_status' => $snapshot['payment_status'],
                    'total_refunded_amount_in_usd' => $newRefundUsd,
                    'total_refunded_amount_in_riel' => $newRefundRiel,
                    'remaining_balance_in_usd' => $snapshot['remaining_balance_in_usd'],
                    'remaining_balance_in_riel' => $snapshot['remaining_balance_in_riel'],
                    'last_updated_by' => $userId,
                ]);

                return $saleOrder->fresh([
                    'customer.customerCategory',
                    'orderItems.product',
                    'refunds.items.saleOrderItem.product',
                    'refunds.processedBy',
                ]);
            });

            return ResponseHelper::success(
                ['sale_order' => $updatedSaleOrder],
                'Sale order refund processed successfully'
            );
        } catch (ValidationException $e) {
            return ResponseHelper::validation($e->errors(), 'Validation Error');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function delete(int $id)
    {
        try {
            $saleOrder = SaleOrder::findOrFail($id);

            DB::transaction(function () use ($saleOrder) {
                $token = $this->stockDeductionService->buildSaleOrderToken((int) $saleOrder->id);
                $productIds = $this->stockDeductionService->deleteSaleOrderMovementsByToken($token);
                $this->stockDeductionService->rebuildIsSoldFlags($productIds);
                $saleOrder->delete();
            });

            return ResponseHelper::success([], 'Sale order deleted successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    private function applyCompletionMovements(SaleOrder $saleOrder, array $items, int $userId): void
    {
        $stockItems = $this->aggregateItemsForStock($items);

        $shortfalls = $this->stockDeductionService->validateSufficientStock($stockItems);
        if (!empty($shortfalls)) {
            throw ValidationException::withMessages([
                'items' => ['Insufficient stock for one or more products.'],
                'shortfalls' => [json_encode($shortfalls, JSON_UNESCAPED_SLASHES)],
            ]);
        }

        $movementDate = (string) $saleOrder->order_date;
        $this->stockDeductionService->deductStockForSaleOrder(
            $stockItems,
            (int) $saleOrder->id,
            $userId,
            $movementDate
        );
    }

    private function aggregateItemsForStock(array $items): array
    {
        $grouped = [];

        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $qty = (float) ($item['quantity'] ?? 0);

            if ($productId <= 0 || $qty <= 0) {
                continue;
            }

            if (!isset($grouped[$productId])) {
                $grouped[$productId] = [
                    'product_id' => $productId,
                    'quantity' => 0,
                    'unit_price_in_usd' => (float) ($item['unit_price_in_usd'] ?? 0),
                    'unit_price_in_riel' => (float) ($item['unit_price_in_riel'] ?? 0),
                    'exchange_rate_from_usd_to_riel' => (float) ($item['exchange_rate_from_usd_to_riel'] ?? 0),
                    'exchange_rate_from_riel_to_usd' => (float) ($item['exchange_rate_from_riel_to_usd'] ?? 0),
                ];
            }

            $grouped[$productId]['quantity'] += $qty;
        }

        return array_values($grouped);
    }

    private function resolveDiscountPercentageFromPayload(
        array $payload,
        int|null $customerId,
        float|null $fallbackDiscountPercentage
    ): float {
        $fallback = round(max(0, min((float) ($fallbackDiscountPercentage ?? 0), 100)), 2);
        $hasDiscountType = array_key_exists('discount_type', $payload);
        $hasLegacyDiscountSwitch = array_key_exists('use_customer_category_discount', $payload);
        $hasLegacyDiscountPercentage = array_key_exists('discount_percentage', $payload);

        if ($hasDiscountType) {
            $discountType = strtoupper((string) ($payload['discount_type'] ?? 'AUTO'));
            if ($discountType === 'AUTO') {
                return $this->getCustomerCategoryDiscountPercentage($customerId);
            }

            $manualValue = $payload['discount_value'] ?? $fallback;
            return round(max(0, min((float) $manualValue, 100)), 2);
        }

        if ($hasLegacyDiscountSwitch || $hasLegacyDiscountPercentage) {
            $useCustomerCategoryDiscount = (bool) ($payload['use_customer_category_discount'] ?? false);
            if ($useCustomerCategoryDiscount) {
                return $this->getCustomerCategoryDiscountPercentage($customerId);
            }

            $manualValue = $payload['discount_percentage'] ?? $fallback;
            return round(max(0, min((float) $manualValue, 100)), 2);
        }

        if (is_null($fallbackDiscountPercentage)) {
            return $this->getCustomerCategoryDiscountPercentage($customerId);
        }

        return $fallback;
    }

    private function getCustomerCategoryDiscountPercentage(int|null $customerId): float
    {
        if (!$customerId || $customerId <= 0) {
            return 0;
        }

        $customer = Customer::with('customerCategory')->find($customerId);
        return round((float) ($customer?->customerCategory?->discount_percentage ?? 0), 2);
    }

    private function buildOrderTotals(
        float $subTotalInUsd,
        float $subTotalInRiel,
        float $discountPercentage,
        float $taxPercentage
    ): array {
        $discountAmountInUsd = round($subTotalInUsd * ($discountPercentage / 100), 2);
        $discountAmountInRiel = round($subTotalInRiel * ($discountPercentage / 100), 2);

        $taxableInUsd = max(0, $subTotalInUsd - $discountAmountInUsd);
        $taxableInRiel = max(0, $subTotalInRiel - $discountAmountInRiel);

        $taxAmountInUsd = round($taxableInUsd * ($taxPercentage / 100), 2);
        $taxAmountInRiel = round($taxableInRiel * ($taxPercentage / 100), 2);

        return [
            'sub_total_in_usd' => round($subTotalInUsd, 2),
            'sub_total_in_riel' => round($subTotalInRiel, 2),
            'discount_percentage' => round($discountPercentage, 2),
            'discount_amount' => $discountAmountInUsd,
            'tax_percentage' => round($taxPercentage, 2),
            'tax_amount_in_usd' => $taxAmountInUsd,
            'tax_amount_in_riel' => $taxAmountInRiel,
            'grand_total_amount_in_usd' => round($taxableInUsd + $taxAmountInUsd, 2),
            'grand_total_amount_in_riel' => round($taxableInRiel + $taxAmountInRiel, 2),
        ];
    }

    private function buildPricedItems(array $items): array
    {
        $preparedItems = [];
        $subTotalInUsd = 0;
        $subTotalInRiel = 0;

        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $qty = (float) ($item['quantity'] ?? 0);

            Product::findOrFail($productId);

            // Price source rule:
            // unit price must always be derived from product movement history (never from client payload).
            $pricingMovement = ProductMovement::where('product_id', $productId)
                ->where(function ($query) {
                    $query->where('selling_unit_price_in_usd', '>', 0)
                        ->orWhere('selling_unit_price_in_riel', '>', 0);
                })
                ->orderBy('movement_date', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            if (!$pricingMovement) {
                throw ValidationException::withMessages([
                    'items' => ["Product {$productId} has no movement history to provide selling prices."],
                ]);
            }

            $unitPriceInUsd = (float) ($pricingMovement->selling_unit_price_in_usd ?? 0);
            $unitPriceInRiel = (float) ($pricingMovement->selling_unit_price_in_riel ?? 0);
            $exchangeRateUsdToRiel = (float) ($pricingMovement->selling_exchange_rate_from_usd_to_riel ?? 0);
            $exchangeRateRielToUsd = (float) ($pricingMovement->selling_exchange_rate_from_riel_to_usd ?? 0);

            if ($unitPriceInRiel <= 0 && $unitPriceInUsd > 0 && $exchangeRateUsdToRiel > 0) {
                $unitPriceInRiel = round($unitPriceInUsd * $exchangeRateUsdToRiel, 2);
            }

            if ($unitPriceInUsd <= 0 && $unitPriceInRiel > 0 && $exchangeRateRielToUsd > 0) {
                $unitPriceInUsd = round($unitPriceInRiel * $exchangeRateRielToUsd, 2);
            }

            if ($exchangeRateUsdToRiel <= 0 && $unitPriceInUsd > 0 && $unitPriceInRiel > 0) {
                $exchangeRateUsdToRiel = round($unitPriceInRiel / $unitPriceInUsd, 4);
            }

            if ($exchangeRateRielToUsd <= 0 && $exchangeRateUsdToRiel > 0) {
                $exchangeRateRielToUsd = round(1 / $exchangeRateUsdToRiel, 6);
            }

            $totalInUsd = round($unitPriceInUsd * $qty, 2);
            $totalInRiel = round($unitPriceInRiel * $qty, 2);

            $subTotalInUsd += $totalInUsd;
            $subTotalInRiel += $totalInRiel;

            $preparedItems[] = [
                'product_id' => $productId,
                'quantity' => $qty,
                'unit_price_in_usd' => $unitPriceInUsd,
                'unit_price_in_riel' => $unitPriceInRiel,
                'total_price_in_usd' => $totalInUsd,
                'total_price_in_riel' => $totalInRiel,
                'exchange_rate_from_usd_to_riel' => $exchangeRateUsdToRiel,
                'exchange_rate_from_riel_to_usd' => $exchangeRateRielToUsd,
                'note' => $item['note'] ?? null,
            ];
        }

        return [
            'items' => $preparedItems,
            'sub_total_in_usd' => round($subTotalInUsd, 2),
            'sub_total_in_riel' => round($subTotalInRiel, 2),
        ];
    }

    private function resolveRefundLineItem(SaleOrder $saleOrder, array $refundItem): SaleOrderItem|null
    {
        if (array_key_exists('sale_order_item_id', $refundItem) && !is_null($refundItem['sale_order_item_id'])) {
            return $saleOrder->orderItems->firstWhere('id', (int) $refundItem['sale_order_item_id']);
        }

        if (array_key_exists('product_id', $refundItem) && !is_null($refundItem['product_id'])) {
            return $saleOrder->orderItems->firstWhere('product_id', (int) $refundItem['product_id']);
        }

        return null;
    }

    private function createReturnFromCustomerMovement(
        SaleOrderItem $lineItem,
        float $quantity,
        string $movementDate,
        int $userId,
        string $note
    ): void {
        ProductMovement::create([
            'product_id' => (int) $lineItem->product_id,
            'quantity' => $quantity,
            'product_status' => ProductStatusEnum::COMPLETED->value,
            'is_sold' => false,
            'direction' => StockDirectionEnum::IN->value,
            'movement_type' => ProductStockMovementTypeEnum::RETURN_FROM_CUSTOMER->value,
            'movement_date' => $movementDate,
            'purchase_unit_price_in_usd' => (float) ($lineItem->unit_price_in_usd ?? 0),
            'purchase_total_price_in_usd' => round((float) ($lineItem->unit_price_in_usd ?? 0) * $quantity, 2),
            'purchase_unit_price_in_riel' => (float) ($lineItem->unit_price_in_riel ?? 0),
            'purchase_total_price_in_riel' => round((float) ($lineItem->unit_price_in_riel ?? 0) * $quantity, 2),
            'exchange_rate_from_usd_to_riel' => (float) ($lineItem->exchange_rate_from_usd_to_riel ?? 0),
            'exchange_rate_from_riel_to_usd' => (float) ($lineItem->exchange_rate_from_riel_to_usd ?? 0),
            'selling_unit_price_in_usd' => (float) ($lineItem->unit_price_in_usd ?? 0),
            'selling_unit_price_in_riel' => (float) ($lineItem->unit_price_in_riel ?? 0),
            'selling_exchange_rate_from_usd_to_riel' => (float) ($lineItem->exchange_rate_from_usd_to_riel ?? 0),
            'selling_exchange_rate_from_riel_to_usd' => (float) ($lineItem->exchange_rate_from_riel_to_usd ?? 0),
            'created_by' => $userId,
            'last_updated_by' => $userId,
            'note' => $note,
        ]);
    }

    private function createScrapMovementFromRefund(
        SaleOrderItem $lineItem,
        float $quantity,
        string $movementDate,
        int $userId,
        string $note
    ): void {
        ProductMovement::create([
            'product_id' => (int) $lineItem->product_id,
            'quantity' => $quantity,
            'product_status' => ProductStatusEnum::COMPLETED->value,
            'is_sold' => false,
            'direction' => StockDirectionEnum::OUT->value,
            'movement_type' => ProductStockMovementTypeEnum::SCRAP->value,
            'movement_date' => $movementDate,
            'purchase_unit_price_in_usd' => (float) ($lineItem->unit_price_in_usd ?? 0),
            'purchase_total_price_in_usd' => round((float) ($lineItem->unit_price_in_usd ?? 0) * $quantity, 2),
            'purchase_unit_price_in_riel' => (float) ($lineItem->unit_price_in_riel ?? 0),
            'purchase_total_price_in_riel' => round((float) ($lineItem->unit_price_in_riel ?? 0) * $quantity, 2),
            'exchange_rate_from_usd_to_riel' => (float) ($lineItem->exchange_rate_from_usd_to_riel ?? 0),
            'exchange_rate_from_riel_to_usd' => (float) ($lineItem->exchange_rate_from_riel_to_usd ?? 0),
            'selling_unit_price_in_usd' => (float) ($lineItem->unit_price_in_usd ?? 0),
            'selling_unit_price_in_riel' => (float) ($lineItem->unit_price_in_riel ?? 0),
            'selling_exchange_rate_from_usd_to_riel' => (float) ($lineItem->exchange_rate_from_usd_to_riel ?? 0),
            'selling_exchange_rate_from_riel_to_usd' => (float) ($lineItem->exchange_rate_from_riel_to_usd ?? 0),
            'created_by' => $userId,
            'last_updated_by' => $userId,
            'note' => $note,
        ]);
    }

    private function isReturnWindowExpired(SaleOrder $saleOrder, string $referenceDate): bool
    {
        if (empty($saleOrder->return_valid_until)) {
            $windowDays = max(1, (int) ($saleOrder->return_window_days ?: self::DEFAULT_RETURN_WINDOW_DAYS));
            $computed = Carbon::parse((string) $saleOrder->order_date)->addDays($windowDays)->endOfDay();
            return Carbon::parse($referenceDate)->greaterThan($computed);
        }

        return Carbon::parse($referenceDate)->greaterThan(Carbon::parse((string) $saleOrder->return_valid_until));
    }

    private function calculateRefundAmounts(
        SaleOrderItem $lineItem,
        float $quantity,
        float $discountPercentage,
        float $taxPercentage,
        float $refundPercentage,
        float|null $overrideUsd,
        float|null $overrideRiel
    ): array {
        $grossUsd = round((float) ($lineItem->unit_price_in_usd ?? 0) * $quantity, 2);
        $grossRiel = round((float) ($lineItem->unit_price_in_riel ?? 0) * $quantity, 2);

        $discountUsd = round($grossUsd * ($discountPercentage / 100), 2);
        $discountRiel = round($grossRiel * ($discountPercentage / 100), 2);

        $taxableUsd = max(0, $grossUsd - $discountUsd);
        $taxableRiel = max(0, $grossRiel - $discountRiel);

        $taxUsd = round($taxableUsd * ($taxPercentage / 100), 2);
        $taxRiel = round($taxableRiel * ($taxPercentage / 100), 2);

        $baseUsd = round($taxableUsd + $taxUsd, 2);
        $baseRiel = round($taxableRiel + $taxRiel, 2);

        $calculatedUsd = round($baseUsd * ($refundPercentage / 100), 2);
        $calculatedRiel = round($baseRiel * ($refundPercentage / 100), 2);

        if (!is_null($overrideUsd) && $overrideUsd >= 0) {
            $calculatedUsd = round(min($baseUsd, $overrideUsd), 2);
        }

        if (!is_null($overrideRiel) && $overrideRiel >= 0) {
            $calculatedRiel = round(min($baseRiel, $overrideRiel), 2);
        }

        return [
            'amount_in_usd' => $calculatedUsd,
            'amount_in_riel' => $calculatedRiel,
        ];
    }

    private function buildPaymentSnapshot(
        float $grandTotalInUsd,
        float $grandTotalInRiel,
        float $paidAmountInUsd,
        float $paidAmountInRiel,
        float $refundedAmountInUsd,
        float $refundedAmountInRiel,
        string|null $paymentStatusInput
    ): array {
        $paidAmountInUsd = max(0, round($paidAmountInUsd, 2));
        $paidAmountInRiel = max(0, round($paidAmountInRiel, 2));
        $refundedAmountInUsd = max(0, round($refundedAmountInUsd, 2));
        $refundedAmountInRiel = max(0, round($refundedAmountInRiel, 2));

        if ($paidAmountInRiel <= 0 && $paidAmountInUsd > 0 && $grandTotalInUsd > 0 && $grandTotalInRiel > 0) {
            $rate = $grandTotalInRiel / $grandTotalInUsd;
            $paidAmountInRiel = round($paidAmountInUsd * $rate, 2);
        }

        if ($paidAmountInUsd <= 0 && $paidAmountInRiel > 0 && $grandTotalInUsd > 0 && $grandTotalInRiel > 0) {
            $rate = $grandTotalInUsd / $grandTotalInRiel;
            $paidAmountInUsd = round($paidAmountInRiel * $rate, 2);
        }

        $remainingUsd = max(0, round($grandTotalInUsd - $paidAmountInUsd - $refundedAmountInUsd, 2));
        $remainingRiel = max(0, round($grandTotalInRiel - $paidAmountInRiel - $refundedAmountInRiel, 2));

        $normalizedInput = is_null($paymentStatusInput)
            ? null
            : strtoupper((string) $paymentStatusInput);

        $netPaidUsd = max(0, round($paidAmountInUsd - $refundedAmountInUsd, 2));
        $computedStatus = PaymentStatusEnum::DEBT->value;
        if ($netPaidUsd <= self::FLOAT_EPSILON) {
            $computedStatus = PaymentStatusEnum::UNPAID->value;
        } elseif ($netPaidUsd + self::FLOAT_EPSILON >= $grandTotalInUsd) {
            $computedStatus = PaymentStatusEnum::PAID->value;
        }

        return [
            'paid_amount_in_usd' => $paidAmountInUsd,
            'paid_amount_in_riel' => $paidAmountInRiel,
            'remaining_balance_in_usd' => $remainingUsd,
            'remaining_balance_in_riel' => $remainingRiel,
            'payment_status' => $normalizedInput ?: $computedStatus,
        ];
    }

    private function normalizeStatus(mixed $status): string
    {
        $statusValue = is_object($status) ? $status->value : (string) $status;
        return strtoupper($statusValue);
    }

    private function assertValidStatusTransition(string $currentStatus, string $targetStatus): void
    {
        if ($currentStatus === $targetStatus) {
            return;
        }

        $allowedTransitions = [
            SaleOrderStatusEnum::DRAFT->value => [
                SaleOrderStatusEnum::PROCESSING->value,
                SaleOrderStatusEnum::ON_HOLD->value,
                SaleOrderStatusEnum::CANCELLED->value,
                SaleOrderStatusEnum::COMPLETED->value,
            ],
            SaleOrderStatusEnum::PROCESSING->value => [
                SaleOrderStatusEnum::ON_HOLD->value,
                SaleOrderStatusEnum::COMPLETED->value,
                SaleOrderStatusEnum::CANCELLED->value,
            ],
            SaleOrderStatusEnum::ON_HOLD->value => [
                SaleOrderStatusEnum::PROCESSING->value,
                SaleOrderStatusEnum::COMPLETED->value,
                SaleOrderStatusEnum::CANCELLED->value,
            ],
            SaleOrderStatusEnum::COMPLETED->value => [],
            SaleOrderStatusEnum::CANCELLED->value => [],
            SaleOrderStatusEnum::REFUNDED->value => [],
        ];

        $allowedTargets = $allowedTransitions[$currentStatus] ?? [];
        if (in_array($targetStatus, $allowedTargets, true)) {
            return;
        }

        throw ValidationException::withMessages([
            'order_status' => ["Invalid status transition from {$currentStatus} to {$targetStatus}."],
        ]);
    }

    private function generateOrderNo(): string
    {
        $prefix = 'SO-' . now()->format('Ymd');
        $lastOrder = SaleOrder::where('order_no', 'like', $prefix . '-%')
            ->orderByDesc('id')
            ->first();

        $next = 1;
        if ($lastOrder && str_contains($lastOrder->order_no, '-')) {
            $parts = explode('-', $lastOrder->order_no);
            $tail = end($parts);
            if (is_numeric($tail)) {
                $next = (int) $tail + 1;
            }
        }

        return sprintf('%s-%04d', $prefix, $next);
    }

    private function generateRefundNo(): string
    {
        $prefix = 'RF-' . now()->format('Ymd');
        $lastRefund = SaleOrderRefund::where('refund_no', 'like', $prefix . '-%')
            ->orderByDesc('id')
            ->first();

        $next = 1;
        if ($lastRefund && str_contains($lastRefund->refund_no, '-')) {
            $parts = explode('-', $lastRefund->refund_no);
            $tail = end($parts);
            if (is_numeric($tail)) {
                $next = (int) $tail + 1;
            }
        }

        return sprintf('%s-%04d', $prefix, $next);
    }
}
