<?php

namespace App\Service;

use App\Enums\SaleOrderStatusEnum;
use App\Enums\StockDirectionEnum;
use App\Helpers\GetCurrentUserHelper;
use App\Helpers\ResponseHelper;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductMovement;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\QueryBuilders\SaleOrderQueryBuilder;
use App\Validations\SaleOrderValidation;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SaleOrderService
{
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

    public function show(int $id)
    {
        try {
            $saleOrder = SaleOrder::with([
                'customer' => fn ($q) => $q->withTrashed(),
                'orderItems.product' => fn ($q) => $q->withTrashed(),
            ])->findOrFail($id);

            return ResponseHelper::success(['sale_order' => $saleOrder]);
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
            $stockItems = $this->aggregateItemsForStock($pricedItemsResult['items']);
            $shortfalls = $this->stockDeductionService->validateSufficientStock($stockItems);
            if (!empty($shortfalls)) {
                return ResponseHelper::error('Insufficient product stock', 422, $shortfalls);
            }

            $discountPercentage = $this->resolveDiscountPercentage(
                (int) ($validated['customer_id'] ?? 0),
                (bool) ($validated['use_customer_category_discount'] ?? true),
                isset($validated['discount_percentage']) ? (float) $validated['discount_percentage'] : null
            );
            $taxPercentage = round((float) ($validated['tax_percentage'] ?? 0), 2);

            $totals = $this->buildOrderTotals(
                $pricedItemsResult['sub_total_in_usd'],
                $pricedItemsResult['sub_total_in_riel'],
                $discountPercentage,
                $taxPercentage
            );

            $saleOrder = DB::transaction(function () use ($validated, $pricedItemsResult, $totals, $userId) {
                $saleOrder = SaleOrder::create([
                    'order_no' => $this->generateOrderNo(),
                    'customer_id' => $validated['customer_id'] ?? null,
                    'order_date' => $validated['order_date'],
                    'order_status' => SaleOrderStatusEnum::DRAFT->value,
                    'payment_status' => $validated['payment_status'] ?? 'UNPAID',
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
                        'refund_quantity' => null,
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
                'sale_order' => $saleOrder->load(['customer', 'orderItems.product'])
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

            if ($this->isCompletedStatus($saleOrder->order_status)) {
                return ResponseHelper::error('Sale order is completed and cannot be updated.', 422);
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

            $updatedSaleOrder = DB::transaction(function () use ($saleOrder, $validated, $userId) {
                $items = [];
                $subTotalInUsd = (float) $saleOrder->sub_total_in_usd;
                $subTotalInRiel = (float) $saleOrder->sub_total_in_riel;

                if (array_key_exists('items', $validated)) {
                    $pricedItemsResult = $this->buildPricedItems($validated['items']);
                    $items = $pricedItemsResult['items'];
                    $subTotalInUsd = $pricedItemsResult['sub_total_in_usd'];
                    $subTotalInRiel = $pricedItemsResult['sub_total_in_riel'];

                    $stockItems = $this->aggregateItemsForStock($items);
                    $shortfalls = $this->stockDeductionService->validateSufficientStock($stockItems);
                    if (!empty($shortfalls)) {
                        throw ValidationException::withMessages([
                            'insufficient_product_stock' => $shortfalls,
                        ]);
                    }

                    SaleOrderItem::where('sale_order_id', $saleOrder->id)->delete();
                    foreach ($items as $item) {
                        SaleOrderItem::create([
                            'sale_order_id' => $saleOrder->id,
                            'product_id' => $item['product_id'],
                            'quantity' => $item['quantity'],
                            'refund_quantity' => null,
                            'unit_price_in_usd' => $item['unit_price_in_usd'],
                            'unit_price_in_riel' => $item['unit_price_in_riel'],
                            'total_price_in_usd' => $item['total_price_in_usd'],
                            'total_price_in_riel' => $item['total_price_in_riel'],
                            'exchange_rate_from_usd_to_riel' => $item['exchange_rate_from_usd_to_riel'],
                            'exchange_rate_from_riel_to_usd' => $item['exchange_rate_from_riel_to_usd'],
                            'note' => $item['note'] ?? null,
                        ]);
                    }
                } else {
                    $items = $saleOrder->orderItems->map(function (SaleOrderItem $item) {
                        return [
                            'product_id' => (int) $item->product_id,
                            'quantity' => (float) $item->quantity,
                            'unit_price_in_usd' => (float) $item->unit_price_in_usd,
                            'unit_price_in_riel' => (float) $item->unit_price_in_riel,
                            'total_price_in_usd' => (float) $item->total_price_in_usd,
                            'total_price_in_riel' => (float) $item->total_price_in_riel,
                            'exchange_rate_from_usd_to_riel' => (float) $item->exchange_rate_from_usd_to_riel,
                            'exchange_rate_from_riel_to_usd' => (float) $item->exchange_rate_from_riel_to_usd,
                            'note' => $item->note,
                        ];
                    })->values()->all();
                }

                if (!array_key_exists('use_customer_category_discount', $validated) && !array_key_exists('discount_percentage', $validated)) {
                    $discountPercentage = (float) $saleOrder->discount_percentage;
                } else {
                    $discountPercentage = $this->resolveDiscountPercentage(
                        (int) ($validated['customer_id'] ?? $saleOrder->customer_id ?? 0),
                        (bool) ($validated['use_customer_category_discount'] ?? !array_key_exists('discount_percentage', $validated)),
                        array_key_exists('discount_percentage', $validated)
                            ? (float) $validated['discount_percentage']
                            : (float) $saleOrder->discount_percentage
                    );
                }

                $taxPercentage = array_key_exists('tax_percentage', $validated)
                    ? round((float) $validated['tax_percentage'], 2)
                    : (float) $saleOrder->tax_percentage;

                $totals = $this->buildOrderTotals($subTotalInUsd, $subTotalInRiel, $discountPercentage, $taxPercentage);

                $saleOrder->update([
                    'customer_id' => $validated['customer_id'] ?? $saleOrder->customer_id,
                    'order_date' => $validated['order_date'] ?? $saleOrder->order_date,
                    'payment_status' => $validated['payment_status'] ?? $saleOrder->payment_status,
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

                return $saleOrder->fresh(['customer', 'orderItems.product']);
            });

            return ResponseHelper::success(['sale_order' => $updatedSaleOrder], 'Sale order updated successfully');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            if (array_key_exists('insufficient_product_stock', $errors)) {
                return ResponseHelper::error('Insufficient product stock', 422, $errors['insufficient_product_stock']);
            }

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
            $paymentStatus = array_key_exists('payment_status', $validated) ? strtoupper((string) $validated['payment_status']) : null;

            $this->assertValidStatusTransition($currentStatus, $targetStatus);

            if ($targetStatus === SaleOrderStatusEnum::COMPLETED->value && $currentStatus !== SaleOrderStatusEnum::COMPLETED->value) {
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

            $updatedSaleOrder = DB::transaction(function () use ($saleOrder, $targetStatus, $userId, $paymentStatus) {
                if ($targetStatus === SaleOrderStatusEnum::COMPLETED->value) {
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

                $updatePayload = [
                    'order_status' => $targetStatus,
                    'last_updated_by' => $userId,
                ];

                if (!is_null($paymentStatus)) {
                    $updatePayload['payment_status'] = $paymentStatus;
                }

                $saleOrder->update($updatePayload);

                return $saleOrder->fresh(['customer', 'orderItems.product']);
            });

            return ResponseHelper::success(['sale_order' => $updatedSaleOrder], 'Sale order status updated successfully');
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
                if ($this->isCompletedStatus($saleOrder->order_status)) {
                    $token = $this->stockDeductionService->buildSaleOrderToken((int) $saleOrder->id);
                    $productIds = $this->stockDeductionService->deleteSaleOrderMovementsByToken($token);
                    $this->stockDeductionService->rebuildIsSoldFlags($productIds);
                }

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
            $productId = (int) $item['product_id'];
            $qty = (float) $item['quantity'];

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

    private function resolveDiscountPercentage(int $customerId, bool $useCategoryDiscount, ?float $manualDiscount): float
    {
        if ($useCategoryDiscount) {
            if ($customerId <= 0) {
                return 0;
            }

            $customer = Customer::with('customerCategory')->find($customerId);
            return round((float) ($customer?->customerCategory?->discount_percentage ?? 0), 2);
        }

        return round(max(0, min((float) ($manualDiscount ?? 0), 100)), 2);
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
            $productId = (int) $item['product_id'];
            $qty = (float) $item['quantity'];

            Product::findOrFail($productId);

            $pricingMovement = ProductMovement::where('product_id', $productId)
                ->where('direction', StockDirectionEnum::IN->value)
                ->orderBy('movement_date', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            if (!$pricingMovement) {
                throw ValidationException::withMessages([
                    'items' => ["Product {$productId} has no inbound movement to provide selling prices."],
                ]);
            }

            $unitPriceInUsd = (float) ($pricingMovement->selling_unit_price_in_usd ?? 0);
            $unitPriceInRiel = (float) ($pricingMovement->selling_unit_price_in_riel ?? 0);
            $exchangeRateUsdToRiel = (float) ($pricingMovement->selling_exchange_rate_from_usd_to_riel ?? 0);
            $exchangeRateRielToUsd = (float) ($pricingMovement->selling_exchange_rate_from_riel_to_usd ?? 0);

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

    private function isCompletedStatus(mixed $status): bool
    {
        $statusValue = is_object($status) ? $status->value : (string) $status;
        return strtoupper($statusValue) === SaleOrderStatusEnum::COMPLETED->value;
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
                SaleOrderStatusEnum::CANCELLED->value,
                SaleOrderStatusEnum::COMPLETED->value,
            ],
            SaleOrderStatusEnum::CANCELLED->value => [],
            SaleOrderStatusEnum::COMPLETED->value => [],
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
}