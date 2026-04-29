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
use App\Models\SaleOrderInstallment;
use App\Models\SaleOrderItem;
use App\Models\SaleOrderRefund;
use App\Models\SaleOrderRefundItem;
use App\Models\SaleOrderStatusHistory;
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
            $dateFrom = $request->query('date_from');
            $dateTo = $request->query('date_to');
            $customerId = $request->query('customer_id');
            $statusInput = trim((string) $request->query('status', ''));
            $groupBy = strtolower((string) $request->query('group_by', 'month'));

            if (!in_array($groupBy, ['day', 'week', 'month', 'year'], true)) {
                $groupBy = 'month';
            }

            $statusFilters = collect(explode(',', $statusInput))
                ->map(fn ($status) => strtoupper(trim((string) $status)))
                ->filter(fn ($status) => in_array($status, [
                    SaleOrderStatusEnum::DRAFT->value,
                    SaleOrderStatusEnum::PROCESSING->value,
                    SaleOrderStatusEnum::ON_HOLD->value,
                    SaleOrderStatusEnum::COMPLETED->value,
                    SaleOrderStatusEnum::CANCELLED->value,
                    SaleOrderStatusEnum::REFUNDED->value,
                ], true))
                ->values()
                ->all();

            $applyOrderFilters = function ($query) use ($dateFrom, $dateTo, $customerId, $statusFilters) {
                if (!empty($dateFrom)) {
                    $query->whereDate('sale_orders.order_date', '>=', $dateFrom);
                }

                if (!empty($dateTo)) {
                    $query->whereDate('sale_orders.order_date', '<=', $dateTo);
                }

                if (!empty($customerId)) {
                    $query->where('sale_orders.customer_id', (int) $customerId);
                }

                if (!empty($statusFilters)) {
                    $query->whereIn('sale_orders.order_status', $statusFilters);
                }

                return $query;
            };

            $query = $applyOrderFilters(SaleOrder::query());

            $stats = (clone $query)
                ->selectRaw('COUNT(*) as total_orders')
                ->selectRaw('SUM(CASE WHEN order_status = ? THEN 1 ELSE 0 END) as total_draft', [SaleOrderStatusEnum::DRAFT->value])
                ->selectRaw('SUM(CASE WHEN order_status = ? THEN 1 ELSE 0 END) as total_processing', [SaleOrderStatusEnum::PROCESSING->value])
                ->selectRaw('SUM(CASE WHEN order_status = ? THEN 1 ELSE 0 END) as total_on_hold', [SaleOrderStatusEnum::ON_HOLD->value])
                ->selectRaw('SUM(CASE WHEN order_status = ? THEN 1 ELSE 0 END) as total_completed', [SaleOrderStatusEnum::COMPLETED->value])
                ->selectRaw('SUM(CASE WHEN order_status = ? THEN 1 ELSE 0 END) as total_cancelled', [SaleOrderStatusEnum::CANCELLED->value])
                ->selectRaw('COALESCE(SUM(CASE WHEN order_status = ? THEN total_refunded_amount_in_usd ELSE 0 END), 0) as total_refunded_usd', [SaleOrderStatusEnum::COMPLETED->value])
                ->selectRaw('COALESCE(SUM(CASE WHEN order_status = ? THEN total_refunded_amount_in_riel ELSE 0 END), 0) as total_refunded_riel', [SaleOrderStatusEnum::COMPLETED->value])
                ->selectRaw('COALESCE(SUM(CASE WHEN order_status = ? THEN discount_amount ELSE 0 END), 0) as total_discount_amount', [SaleOrderStatusEnum::COMPLETED->value])
                ->selectRaw('COALESCE(SUM(CASE WHEN order_status = ? THEN grand_total_amount_in_usd ELSE 0 END), 0) as gross_sales_usd', [SaleOrderStatusEnum::COMPLETED->value])
                ->selectRaw('COALESCE(SUM(CASE WHEN order_status = ? THEN grand_total_amount_in_riel ELSE 0 END), 0) as gross_sales_riel', [SaleOrderStatusEnum::COMPLETED->value])
                ->first();

            $totalRefundedOrders = (clone $query)
                ->whereExists(function ($subQuery) {
                    $subQuery->selectRaw('1')
                        ->from('sale_order_refunds')
                        ->whereColumn('sale_order_refunds.sale_order_id', 'sale_orders.id');
                })
                ->count();

            $salesRows = $applyOrderFilters(SaleOrder::query())
                ->where('order_status', SaleOrderStatusEnum::COMPLETED->value)
                ->orderBy('order_date')
                ->get([
                    'order_date',
                    'grand_total_amount_in_usd',
                    'grand_total_amount_in_riel',
                ]);

            $trendBucket = [];
            foreach ($salesRows as $row) {
                $parsedDate = Carbon::parse((string) $row->order_date);
                $bucketKey = $this->formatTrendBucket($parsedDate, $groupBy);

                if (!isset($trendBucket[$bucketKey])) {
                    $trendBucket[$bucketKey] = [
                        'period' => $bucketKey,
                        'total_sales_usd' => 0.0,
                        'total_sales_riel' => 0.0,
                    ];
                }

                $trendBucket[$bucketKey]['total_sales_usd'] += (float) $row->grand_total_amount_in_usd;
                $trendBucket[$bucketKey]['total_sales_riel'] += (float) $row->grand_total_amount_in_riel;
            }

            $salesTrend = array_values(array_map(function (array $bucket) {
                return [
                    'period' => $bucket['period'],
                    'total_sales_usd' => round((float) $bucket['total_sales_usd'], 2),
                    'total_sales_riel' => round((float) $bucket['total_sales_riel'], 2),
                ];
            }, $trendBucket));

            $topCustomers = $applyOrderFilters(
                DB::table('sale_orders')
                    ->leftJoin('customers', 'sale_orders.customer_id', '=', 'customers.id')
            )
                ->where('sale_orders.order_status', SaleOrderStatusEnum::COMPLETED->value)
                ->select('sale_orders.customer_id')
                ->selectRaw("COALESCE(customers.fullname, 'Walk-in Customer') as customer_name")
                ->selectRaw('COUNT(sale_orders.id) as orders_count')
                ->selectRaw('COALESCE(SUM(sale_orders.grand_total_amount_in_usd), 0) as total_sales_usd')
                ->selectRaw('COALESCE(SUM(sale_orders.grand_total_amount_in_riel), 0) as total_sales_riel')
                ->groupBy('sale_orders.customer_id', 'customers.fullname')
                ->orderByDesc('total_sales_usd')
                ->limit(10)
                ->get();

            $topProducts = $applyOrderFilters(
                DB::table('sale_order_items')
                    ->join('sale_orders', 'sale_order_items.sale_order_id', '=', 'sale_orders.id')
                    ->leftJoin('products', 'sale_order_items.product_id', '=', 'products.id')
            )
                ->where('sale_orders.order_status', SaleOrderStatusEnum::COMPLETED->value)
                ->select('sale_order_items.product_id')
                ->selectRaw("COALESCE(products.product_name, 'Unknown Product') as product_name")
                ->selectRaw('COALESCE(SUM(sale_order_items.quantity), 0) as quantity_sold')
                ->selectRaw('COALESCE(SUM(sale_order_items.total_price_in_usd), 0) as total_sales_usd')
                ->selectRaw('COALESCE(SUM(sale_order_items.total_price_in_riel), 0) as total_sales_riel')
                ->groupBy('sale_order_items.product_id', 'products.product_name')
                ->orderByDesc('quantity_sold')
                ->limit(10)
                ->get();

            $netRevenueUsd = round(
                max(0, (float) ($stats?->gross_sales_usd ?? 0) - (float) ($stats?->total_refunded_usd ?? 0)),
                2
            );
            $netRevenueRiel = round(
                max(0, (float) ($stats?->gross_sales_riel ?? 0) - (float) ($stats?->total_refunded_riel ?? 0)),
                2
            );
            $totalCompleted = (int) ($stats?->total_completed ?? 0);
            $averageOrderValueUsd = $totalCompleted > 0
                ? round(((float) ($stats?->gross_sales_usd ?? 0)) / $totalCompleted, 2)
                : 0.0;
            $averageOrderValueRiel = $totalCompleted > 0
                ? round(((float) ($stats?->gross_sales_riel ?? 0)) / $totalCompleted, 2)
                : 0.0;

            return ResponseHelper::success([
                'total_orders' => (int) ($stats?->total_orders ?? 0),
                'total_draft' => (int) ($stats?->total_draft ?? 0),
                'total_processing' => (int) ($stats?->total_processing ?? 0),
                'total_on_hold' => (int) ($stats?->total_on_hold ?? 0),
                'total_completed' => (int) ($stats?->total_completed ?? 0),
                'total_cancelled' => (int) ($stats?->total_cancelled ?? 0),
                'total_refunded_records' => (int) $totalRefundedOrders,
                'total_refunded' => (int) $totalRefundedOrders,
                'total_refunded_usd' => round((float) ($stats?->total_refunded_usd ?? 0), 2),
                'total_refunded_riel' => round((float) ($stats?->total_refunded_riel ?? 0), 2),
                'total_discount_amount' => round((float) ($stats?->total_discount_amount ?? 0), 2),
                'gross_sales_usd' => round((float) ($stats?->gross_sales_usd ?? 0), 2),
                'gross_sales_riel' => round((float) ($stats?->gross_sales_riel ?? 0), 2),
                'total_sales_usd' => round((float) ($stats?->gross_sales_usd ?? 0), 2),
                'total_sales_riel' => round((float) ($stats?->gross_sales_riel ?? 0), 2),
                'net_revenue_usd' => $netRevenueUsd,
                'net_revenue_riel' => $netRevenueRiel,
                'average_order_value_usd' => $averageOrderValueUsd,
                'average_order_value_riel' => $averageOrderValueRiel,
                // Backward-compatible aliases for existing clients.
                'total_earning_usd' => $netRevenueUsd,
                'total_earning_riel' => $netRevenueRiel,
                'group_by' => $groupBy,
                'top_customers' => $topCustomers,
                'top_products' => $topProducts,
                'filters' => [
                    'date_from' => $dateFrom ?: null,
                    'date_to' => $dateTo ?: null,
                    'customer_id' => !empty($customerId) ? (int) $customerId : null,
                    'status' => $statusFilters,
                ],
                'sales_trend' => $salesTrend,
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
                'installments' => fn ($q) => $q->orderBy('paid_at'),
                'installments.creator',
                'statusHistories' => fn ($q) => $q->orderByDesc('changed_at'),
                'statusHistories.changedBy',
            ])->findOrFail($id);

            return ResponseHelper::success(['sale_order' => $saleOrder]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function getRefunds(int $id)
    {
        try {
            $saleOrder = SaleOrder::with([
                'customer' => fn ($q) => $q->withTrashed()->with('customerCategory'),
            ])->findOrFail($id);

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
                'sale_order' => [
                    'id' => (int) $saleOrder->id,
                    'order_no' => (string) $saleOrder->order_no,
                    'order_status' => (string) $this->normalizeStatus($saleOrder->order_status),
                    'customer' => $saleOrder->customer,
                    'navigation' => [
                        'completed_sale_order_query' => [
                            'sale_order_tab' => 'history',
                            'sale_order_subtab' => 'completed',
                            'sale_order_id' => (int) $saleOrder->id,
                        ],
                    ],
                ],
                'refunds' => $refunds,
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function listRefundRecords(Request $request)
    {
        try {
            $perPage = max(1, min((int) $request->query('per_page', 10), 100));
            $dateFrom = $request->query('date_from');
            $dateTo = $request->query('date_to');
            $search = trim((string) $request->query('search', ''));
            $refundType = strtoupper((string) $request->query('refund_type', ''));

            $query = SaleOrderRefund::with([
                'saleOrder' => fn ($q) => $q->withTrashed()->with('customer'),
                'processedBy',
            ])->orderByDesc('processed_at')->orderByDesc('id');

            if (!empty($dateFrom)) {
                $query->whereDate('processed_at', '>=', $dateFrom);
            }

            if (!empty($dateTo)) {
                $query->whereDate('processed_at', '<=', $dateTo);
            }

            if (!empty($refundType)) {
                $query->where('refund_type', $refundType);
            }

            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('refund_no', 'LIKE', "%{$search}%")
                        ->orWhere('reason', 'LIKE', "%{$search}%")
                        ->orWhereHas('saleOrder', function ($saleOrderQuery) use ($search) {
                            $saleOrderQuery->where('order_no', 'LIKE', "%{$search}%")
                                ->orWhereHas('customer', function ($customerQuery) use ($search) {
                                    $customerQuery->where('fullname', 'LIKE', "%{$search}%")
                                        ->orWhere('phone_number', 'LIKE', "%{$search}%");
                                });
                        });
                });
            }

            $records = $query->paginate($perPage)->appends($request->query());

            return ResponseHelper::success($records, 'Refund records retrieved successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function addInstallment(Request $request, int $id)
    {
        if (!$request->has('payment_status')) {
            $current = SaleOrder::query()->select('payment_status')->find($id);
            $currentPaymentStatus = $this->normalizePaymentStatusInput(
                $current
                    ? (is_object($current->payment_status) ? $current->payment_status->value : (string) $current->payment_status)
                    : null
            ) ?? PaymentStatusEnum::INSTALLMENT->value;
            $request->merge(['payment_status' => $currentPaymentStatus]);
        }

        return $this->addPayment($request, $id);
    }

    public function addPayment(Request $request, int $id)
    {
        try {
            $validator = Validator::make(
                $request->all(),
                $this->saleOrderValidation->paymentRules($request),
                $this->saleOrderValidation->messages()
            );

            if ($validator->fails()) {
                return ResponseHelper::validation($validator->errors()->toArray(), 'Validation Error');
            }

            $validated = $validator->validated();
            $userId = $this->getCurrentUserHelper->getUserId();
            $requestedInstallmentPercentage = round((float) ($validated['payment_percentage'] ?? 0), 2);

            $result = DB::transaction(function () use ($id, $validated, $requestedInstallmentPercentage, $userId) {
                /** @var SaleOrder $saleOrder */
                $saleOrder = SaleOrder::query()
                    ->where('id', $id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $currentStatus = $this->normalizeStatus($saleOrder->order_status);
                if (in_array($currentStatus, [SaleOrderStatusEnum::CANCELLED->value, SaleOrderStatusEnum::REFUNDED->value], true)) {
                    throw ValidationException::withMessages([
                        'order_status' => ['Cancelled or refunded orders cannot accept payment updates.'],
                    ]);
                }

                $currentPaymentStatus = $this->normalizePaymentStatusInput(
                    is_object($saleOrder->payment_status) ? $saleOrder->payment_status->value : (string) $saleOrder->payment_status
                ) ?? PaymentStatusEnum::INSTALLMENT->value;

                $requestedPaymentStatus = $this->normalizePaymentStatusInput($validated['payment_status'] ?? null)
                    ?? $currentPaymentStatus;

                if ($currentStatus !== SaleOrderStatusEnum::DRAFT->value && $requestedPaymentStatus !== $currentPaymentStatus) {
                    throw ValidationException::withMessages([
                        'payment_status' => ['Payment status type cannot be changed after order leaves DRAFT.'],
                    ]);
                }

                $currentPaidPercentage = round((float) ($saleOrder->paid_percentage ?? 0), 2);
                $remainingPercentage = round(max(0, 100 - $currentPaidPercentage), 2);

                if ($remainingPercentage <= self::FLOAT_EPSILON) {
                    throw ValidationException::withMessages([
                        'payment_percentage' => ['Order is already fully paid.'],
                    ]);
                }

                if ($requestedInstallmentPercentage > $remainingPercentage + self::FLOAT_EPSILON) {
                    throw ValidationException::withMessages([
                        'payment_percentage' => ["Installment percentage cannot exceed remaining {$remainingPercentage}%."],
                    ]);
                }

                $newCumulativePercentage = round(min(100, $currentPaidPercentage + $requestedInstallmentPercentage), 2);
                if (
                    $requestedPaymentStatus === PaymentStatusEnum::PAID->value
                    && $newCumulativePercentage < 100 - self::FLOAT_EPSILON
                ) {
                    throw ValidationException::withMessages([
                        'payment_percentage' => ['PAID status requires total paid percentage to reach 100%.'],
                    ]);
                }

                $grandTotalUsd = (float) $saleOrder->grand_total_amount_in_usd;
                $grandTotalRiel = (float) $saleOrder->grand_total_amount_in_riel;
                $installmentAmountUsd = round($grandTotalUsd * ($requestedInstallmentPercentage / 100), 2);
                $installmentAmountRiel = round($grandTotalRiel * ($requestedInstallmentPercentage / 100), 2);

                $installment = SaleOrderInstallment::create([
                    'sale_order_id' => (int) $saleOrder->id,
                    'percentage' => $requestedInstallmentPercentage,
                    'cumulative_percentage' => $newCumulativePercentage,
                    'amount_usd' => $installmentAmountUsd,
                    'amount_riel' => $installmentAmountRiel,
                    'paid_at' => (string) ($validated['paid_at'] ?? now()->toDateTimeString()),
                    'note' => $validated['note'] ?? null,
                    'created_by' => $userId,
                ]);

                $resolvedPaymentStatus = $newCumulativePercentage >= 100 - self::FLOAT_EPSILON
                    ? PaymentStatusEnum::PAID->value
                    : ($currentStatus === SaleOrderStatusEnum::DRAFT->value
                        ? $requestedPaymentStatus
                        : $currentPaymentStatus);

                $snapshot = $this->buildPaymentSnapshot(
                    grandTotalInUsd: $grandTotalUsd,
                    grandTotalInRiel: $grandTotalRiel,
                    paidAmountInUsd: (float) $saleOrder->paid_amount_in_usd,
                    paidAmountInRiel: (float) $saleOrder->paid_amount_in_riel,
                    refundedAmountInUsd: (float) $saleOrder->total_refunded_amount_in_usd,
                    refundedAmountInRiel: (float) $saleOrder->total_refunded_amount_in_riel,
                    paymentStatusInput: $resolvedPaymentStatus,
                    paidPercentageInput: $newCumulativePercentage
                );

                $saleOrder->update([
                    'payment_status' => $snapshot['payment_status'],
                    'paid_amount_in_usd' => $snapshot['paid_amount_in_usd'],
                    'paid_amount_in_riel' => $snapshot['paid_amount_in_riel'],
                    'paid_percentage' => $snapshot['paid_percentage'],
                    'remaining_balance_in_usd' => $snapshot['remaining_balance_in_usd'],
                    'remaining_balance_in_riel' => $snapshot['remaining_balance_in_riel'],
                    'last_updated_by' => $userId,
                ]);

                return [
                    'installment' => $installment,
                    'sale_order' => $saleOrder->fresh([
                        'customer.customerCategory',
                        'orderItems.product',
                        'refunds.items.saleOrderItem.product',
                        'refunds.processedBy',
                        'installments' => fn ($q) => $q->orderBy('paid_at'),
                        'installments.creator',
                        'statusHistories' => fn ($q) => $q->orderByDesc('changed_at'),
                    ]),
                ];
            });

            /** @var SaleOrder $updatedOrder */
            $updatedOrder = $result['sale_order'];
            /** @var SaleOrderInstallment $installment */
            $installment = $result['installment'];

            return ResponseHelper::success([
                'sale_order_id' => (int) $updatedOrder->id,
                'payment_status' => is_object($updatedOrder->payment_status)
                    ? $updatedOrder->payment_status->value
                    : (string) $updatedOrder->payment_status,
                'paid_percentage_total' => round((float) ($updatedOrder->paid_percentage ?? 0), 2),
                'remaining_percentage' => round(max(0, 100 - (float) ($updatedOrder->paid_percentage ?? 0)), 2),
                'paid_amount_usd' => round((float) ($updatedOrder->paid_amount_in_usd ?? 0), 2),
                'paid_amount_riel' => round((float) ($updatedOrder->paid_amount_in_riel ?? 0), 2),
                'installment' => $installment,
                'sale_order' => $updatedOrder,
            ], 'Sale order payment recorded successfully');
        } catch (ValidationException $e) {
            return ResponseHelper::validation($e->errors(), 'Validation Error');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function exportStatisticsReport(Request $request)
    {
        $statsResponse = $this->statistics($request);
        $payload = $statsResponse->getData(true);

        if (!(bool) ($payload['status'] ?? false)) {
            return ResponseHelper::error('Failed generating statistics report', 500);
        }

        $stats = $payload['data'] ?? [];
        $lines = [
            'Sale Order Statistics Report',
            'Generated At: ' . now()->toDateTimeString(),
            'Date From: ' . ($request->query('date_from') ?: 'ALL'),
            'Date To: ' . ($request->query('date_to') ?: 'ALL'),
            'Customer: ' . ($request->query('customer_id') ?: 'ALL'),
            'Status: ' . ($request->query('status') ?: 'ALL'),
            'Group By: ' . strtoupper((string) ($stats['group_by'] ?? 'MONTH')),
            '',
            'Summary',
            'Total Orders: ' . (int) ($stats['total_orders'] ?? 0),
            'Total Draft: ' . (int) ($stats['total_draft'] ?? 0),
            'Total Processing: ' . (int) ($stats['total_processing'] ?? 0),
            'Total On Hold: ' . (int) ($stats['total_on_hold'] ?? 0),
            'Total Completed: ' . (int) ($stats['total_completed'] ?? 0),
            'Total Cancelled: ' . (int) ($stats['total_cancelled'] ?? 0),
            'Orders With Refunds: ' . (int) ($stats['total_refunded'] ?? 0),
            'Gross Sales USD: ' . number_format((float) ($stats['gross_sales_usd'] ?? 0), 2),
            'Total Refunded USD: ' . number_format((float) ($stats['total_refunded_usd'] ?? 0), 2),
            'Total Discount USD: ' . number_format((float) ($stats['total_discount_amount'] ?? 0), 2),
            'Net Revenue USD: ' . number_format((float) ($stats['net_revenue_usd'] ?? 0), 2),
            'Average Order Value USD: ' . number_format((float) ($stats['average_order_value_usd'] ?? 0), 2),
            '',
            'Sales Trend (USD)',
        ];

        foreach (($stats['sales_trend'] ?? []) as $point) {
            $lines[] = ($point['period'] ?? '-') . ': ' . number_format((float) ($point['total_sales_usd'] ?? 0), 2);
        }

        $filename = 'sale-order-report-' . now()->format('Ymd-His') . '.pdf';
        $pdfContent = $this->buildSimplePdfDocument($lines);

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
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
                $initialPaidPercentage = array_key_exists('payment_percentage', $validated)
                    ? (float) $validated['payment_percentage']
                    : (
                        strtoupper((string) ($validated['payment_status'] ?? '')) === PaymentStatusEnum::PAID->value
                            ? 100.0
                            : null
                    );

                $paymentSnapshot = $this->buildPaymentSnapshot(
                    grandTotalInUsd: (float) $totals['grand_total_amount_in_usd'],
                    grandTotalInRiel: (float) $totals['grand_total_amount_in_riel'],
                    paidAmountInUsd: (float) ($validated['paid_amount_in_usd'] ?? 0),
                    paidAmountInRiel: (float) ($validated['paid_amount_in_riel'] ?? 0),
                    refundedAmountInUsd: 0,
                    refundedAmountInRiel: 0,
                    paymentStatusInput: $validated['payment_status'] ?? null,
                    paidPercentageInput: $initialPaidPercentage
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
                    'paid_percentage' => $paymentSnapshot['paid_percentage'],
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

                if ((float) ($paymentSnapshot['paid_percentage'] ?? 0) > 0) {
                    SaleOrderInstallment::create([
                        'sale_order_id' => (int) $saleOrder->id,
                        'percentage' => (float) $paymentSnapshot['paid_percentage'],
                        'cumulative_percentage' => (float) $paymentSnapshot['paid_percentage'],
                        'amount_usd' => (float) $paymentSnapshot['paid_amount_in_usd'],
                        'amount_riel' => (float) $paymentSnapshot['paid_amount_in_riel'],
                        'paid_at' => (string) ($validated['paid_at'] ?? now()->toDateTimeString()),
                        'note' => $validated['installment_note'] ?? null,
                        'created_by' => $userId,
                    ]);
                }

                SaleOrderStatusHistory::create([
                    'sale_order_id' => (int) $saleOrder->id,
                    'from_status' => null,
                    'to_status' => SaleOrderStatusEnum::DRAFT->value,
                    'note' => 'Initial status set on sale order creation.',
                    'changed_at' => now()->toDateTimeString(),
                    'changed_by' => $userId,
                    'metadata' => [
                        'payment_status' => $paymentSnapshot['payment_status'],
                        'paid_percentage' => $paymentSnapshot['paid_percentage'],
                    ],
                ]);

                return $saleOrder;
            });

            return ResponseHelper::success([
                'sale_order' => $saleOrder->load([
                    'customer.customerCategory',
                    'orderItems.product',
                    'refunds.items.saleOrderItem.product',
                    'refunds.processedBy',
                    'installments' => fn ($q) => $q->orderBy('paid_at'),
                    'installments.creator',
                    'statusHistories' => fn ($q) => $q->orderByDesc('changed_at'),
                    'statusHistories.changedBy',
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
                if (!array_key_exists('payment_percentage', $validated)) {
                    return ResponseHelper::error(
                        'Only new payment entries are allowed after order leaves DRAFT. Please use payment_percentage.',
                        422,
                        ['payment_percentage' => ['A new installment payment percentage is required.']]
                    );
                }

                $allowedInstallmentFields = ['payment_percentage', 'paid_at', 'installment_note', 'note', 'payment_status'];
                $invalidInstallmentFields = array_values(array_diff(array_keys($validated), $allowedInstallmentFields));
                if (!empty($invalidInstallmentFields)) {
                    return ResponseHelper::error(
                        'Installment updates cannot modify non-payment fields.',
                        422,
                        ['fields' => $invalidInstallmentFields]
                    );
                }

                $currentPaymentStatus = $this->normalizePaymentStatusInput(
                    is_object($saleOrder->payment_status) ? $saleOrder->payment_status->value : (string) $saleOrder->payment_status
                ) ?? PaymentStatusEnum::INSTALLMENT->value;

                $request->merge([
                    'payment_status' => $validated['payment_status'] ?? $currentPaymentStatus,
                    'note' => $validated['installment_note'] ?? $validated['note'] ?? $request->input('note'),
                ]);

                return $this->addPayment($request, $id);
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
                $nextPaidPercentage = array_key_exists('payment_percentage', $validated)
                    ? (float) $validated['payment_percentage']
                    : (
                        array_key_exists('payment_status', $validated)
                        && strtoupper((string) ($validated['payment_status'] ?? '')) === PaymentStatusEnum::PAID->value
                            ? 100.0
                            : (float) ($saleOrder->paid_percentage ?? 0)
                    );

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
                    paymentStatusInput: $validated['payment_status'] ?? null,
                    paidPercentageInput: $nextPaidPercentage
                );

                $saleOrder->update([
                    'customer_id' => $customerId,
                    'order_date' => $nextOrderDate,
                    'return_window_days' => $returnWindowDays,
                    'return_valid_until' => $returnValidUntil,
                    'payment_status' => $snapshot['payment_status'],
                    'paid_amount_in_usd' => $snapshot['paid_amount_in_usd'],
                    'paid_amount_in_riel' => $snapshot['paid_amount_in_riel'],
                    'paid_percentage' => $snapshot['paid_percentage'],
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
                    'refunds.items.saleOrderItem.product',
                    'refunds.processedBy',
                    'installments' => fn ($q) => $q->orderBy('paid_at'),
                    'installments.creator',
                    'statusHistories' => fn ($q) => $q->orderByDesc('changed_at'),
                    'statusHistories.changedBy',
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

            if ($currentStatus !== SaleOrderStatusEnum::DRAFT->value && array_key_exists('payment_status', $validated)) {
                return ResponseHelper::error(
                    'Payment status type cannot be changed after order leaves DRAFT.',
                    422,
                    ['payment_status' => ['Payment status updates are only allowed while order status is DRAFT.']]
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
                    paidAmountInUsd: (float) $saleOrder->paid_amount_in_usd,
                    paidAmountInRiel: (float) $saleOrder->paid_amount_in_riel,
                    refundedAmountInUsd: (float) $saleOrder->total_refunded_amount_in_usd,
                    refundedAmountInRiel: (float) $saleOrder->total_refunded_amount_in_riel,
                    paymentStatusInput: $currentStatus === SaleOrderStatusEnum::DRAFT->value
                        ? ($validated['payment_status'] ?? null)
                        : null,
                    paidPercentageInput: (float) ($saleOrder->paid_percentage ?? 0)
                );

                $fromStatus = $currentStatus;
                $saleOrder->update([
                    'order_status' => $targetStatus,
                    'payment_status' => $snapshot['payment_status'],
                    'paid_amount_in_usd' => $snapshot['paid_amount_in_usd'],
                    'paid_amount_in_riel' => $snapshot['paid_amount_in_riel'],
                    'paid_percentage' => $snapshot['paid_percentage'],
                    'remaining_balance_in_usd' => $snapshot['remaining_balance_in_usd'],
                    'remaining_balance_in_riel' => $snapshot['remaining_balance_in_riel'],
                    'last_updated_by' => $userId,
                ]);

                if ($fromStatus !== $targetStatus) {
                    SaleOrderStatusHistory::create([
                        'sale_order_id' => (int) $saleOrder->id,
                        'from_status' => $fromStatus,
                        'to_status' => $targetStatus,
                        'note' => 'Status updated from sale order workflow.',
                        'changed_at' => now()->toDateTimeString(),
                        'changed_by' => $userId,
                        'metadata' => [
                            'payment_status' => $snapshot['payment_status'],
                            'paid_percentage' => $snapshot['paid_percentage'],
                        ],
                    ]);
                }

                return $saleOrder->fresh([
                    'customer.customerCategory',
                    'orderItems.product',
                    'refunds.items.saleOrderItem.product',
                    'refunds.processedBy',
                    'installments' => fn ($q) => $q->orderBy('paid_at'),
                    'installments.creator',
                    'statusHistories' => fn ($q) => $q->orderByDesc('changed_at'),
                    'statusHistories.changedBy',
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
                    paymentStatusInput: null,
                    paidPercentageInput: (float) ($saleOrder->paid_percentage ?? 0)
                );

                $saleOrder->update([
                    'order_status' => SaleOrderStatusEnum::COMPLETED->value,
                    'payment_status' => $snapshot['payment_status'],
                    'paid_percentage' => $snapshot['paid_percentage'],
                    'total_refunded_amount_in_usd' => $newRefundUsd,
                    'total_refunded_amount_in_riel' => $newRefundRiel,
                    'remaining_balance_in_usd' => $snapshot['remaining_balance_in_usd'],
                    'remaining_balance_in_riel' => $snapshot['remaining_balance_in_riel'],
                    'last_updated_by' => $userId,
                ]);

                SaleOrderStatusHistory::create([
                    'sale_order_id' => (int) $saleOrder->id,
                    'from_status' => SaleOrderStatusEnum::COMPLETED->value,
                    'to_status' => SaleOrderStatusEnum::COMPLETED->value,
                    'note' => 'Refund processed while keeping completed status.',
                    'changed_at' => now()->toDateTimeString(),
                    'changed_by' => $userId,
                    'metadata' => [
                        'refund_id' => (int) $refund->id,
                        'refund_no' => (string) $refund->refund_no,
                        'total_refund_amount_in_usd' => round($totalRefundUsd, 2),
                        'total_refund_amount_in_riel' => round($totalRefundRiel, 2),
                    ],
                ]);

                return $saleOrder->fresh([
                    'customer.customerCategory',
                    'orderItems.product',
                    'refunds.items.saleOrderItem.product',
                    'refunds.processedBy',
                    'installments' => fn ($q) => $q->orderBy('paid_at'),
                    'installments.creator',
                    'statusHistories' => fn ($q) => $q->orderByDesc('changed_at'),
                    'statusHistories.changedBy',
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
        string|null $paymentStatusInput,
        float|null $paidPercentageInput = null
    ): array {
        $paidAmountInUsd = max(0, round($paidAmountInUsd, 2));
        $paidAmountInRiel = max(0, round($paidAmountInRiel, 2));
        $refundedAmountInUsd = max(0, round($refundedAmountInUsd, 2));
        $refundedAmountInRiel = max(0, round($refundedAmountInRiel, 2));

        $normalizedInput = $this->normalizePaymentStatusInput($paymentStatusInput);

        if (!is_null($paidPercentageInput)) {
            $paidPercentageInput = round(max(0, min($paidPercentageInput, 100)), 2);
            $paidAmountInUsd = round($grandTotalInUsd * ($paidPercentageInput / 100), 2);
            $paidAmountInRiel = round($grandTotalInRiel * ($paidPercentageInput / 100), 2);
        } else {
            if ($paidAmountInRiel <= 0 && $paidAmountInUsd > 0 && $grandTotalInUsd > 0 && $grandTotalInRiel > 0) {
                $rate = $grandTotalInRiel / $grandTotalInUsd;
                $paidAmountInRiel = round($paidAmountInUsd * $rate, 2);
            }

            if ($paidAmountInUsd <= 0 && $paidAmountInRiel > 0 && $grandTotalInUsd > 0 && $grandTotalInRiel > 0) {
                $rate = $grandTotalInUsd / $grandTotalInRiel;
                $paidAmountInUsd = round($paidAmountInRiel * $rate, 2);
            }
        }

        $paidAmountInUsd = min($grandTotalInUsd, $paidAmountInUsd);
        $paidAmountInRiel = min($grandTotalInRiel, $paidAmountInRiel);
        $paidPercentage = $grandTotalInUsd > 0
            ? round(min(100, max(0, ($paidAmountInUsd / $grandTotalInUsd) * 100)), 2)
            : 0.0;

        $remainingUsd = max(0, round($grandTotalInUsd - $paidAmountInUsd - $refundedAmountInUsd, 2));
        $remainingRiel = max(0, round($grandTotalInRiel - $paidAmountInRiel - $refundedAmountInRiel, 2));

        if (
            $normalizedInput === PaymentStatusEnum::PAID->value
            && $paidPercentage < 100 - self::FLOAT_EPSILON
        ) {
            throw ValidationException::withMessages([
                'payment_percentage' => ['PAID status requires total paid percentage to reach 100%.'],
            ]);
        }

        $computedStatus = $paidPercentage >= 100 - self::FLOAT_EPSILON
            ? PaymentStatusEnum::PAID->value
            : PaymentStatusEnum::INSTALLMENT->value;

        if (
            $normalizedInput === PaymentStatusEnum::DEBT->value
            && $paidPercentage < 100 - self::FLOAT_EPSILON
        ) {
            $computedStatus = PaymentStatusEnum::DEBT->value;
        }

        return [
            'paid_amount_in_usd' => $paidAmountInUsd,
            'paid_amount_in_riel' => $paidAmountInRiel,
            'paid_percentage' => $paidPercentage,
            'remaining_balance_in_usd' => $remainingUsd,
            'remaining_balance_in_riel' => $remainingRiel,
            'payment_status' => $computedStatus,
        ];
    }

    private function normalizePaymentStatusInput(string|null $paymentStatusInput): string|null
    {
        if (is_null($paymentStatusInput) || trim($paymentStatusInput) === '') {
            return null;
        }

        $normalized = strtoupper((string) $paymentStatusInput);
        if ($normalized === 'UNPAID') {
            return PaymentStatusEnum::INSTALLMENT->value;
        }

        return $normalized;
    }

    private function formatTrendBucket(Carbon $date, string $groupBy): string
    {
        return match ($groupBy) {
            'day' => $date->format('Y-m-d'),
            'week' => $date->copy()->startOfWeek()->format('Y-m-d'),
            'year' => $date->format('Y'),
            default => $date->format('Y-m'),
        };
    }

    private function buildSimplePdfDocument(array $lines): string
    {
        $safeLines = array_slice(array_map(function ($line) {
            $normalized = preg_replace('/[^\\x20-\\x7E]/', '?', (string) $line) ?? '';
            return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $normalized);
        }, $lines), 0, 180);

        $y = 795;
        $lineHeight = 14;
        $content = "BT\n/F1 11 Tf\n72 {$y} Td\n";
        $first = true;

        foreach ($safeLines as $line) {
            if (!$first) {
                $content .= "0 -{$lineHeight} Td\n";
            }
            $content .= "({$line}) Tj\n";
            $first = false;
        }

        $content .= "ET";

        $objects = [];
        $objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
        $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>";
        $objects[] = "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream";
        $objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $objectNumber = $index + 1;
            $pdf .= "{$objectNumber} 0 obj\n{$object}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= str_pad((string) $offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
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
