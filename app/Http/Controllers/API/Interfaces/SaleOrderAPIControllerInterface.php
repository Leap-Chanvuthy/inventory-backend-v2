<?php

namespace App\Http\Controllers\API\Interfaces;

use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Sale Orders",
 *     description="API Endpoints for managing sale orders. (Only Accessible for: ADMIN and CUSTOMER Users)"
 * )
 *
 * @OA\Schema(
 *   schema="ProductMovementAllocation",
 *   type="object",
 *   @OA\Property(property="source_movement_id", type="integer", example=5),
 *   @OA\Property(property="movement_type", type="string", example="RE_ORDER"),
 *   @OA\Property(property="movement_date", type="string", format="date-time", nullable=true),
 *   @OA\Property(property="allocated_quantity", type="number", format="float", example=10),
 *   @OA\Property(property="selling_unit_price_in_usd", type="number", format="float", example=6),
 *   @OA\Property(property="selling_unit_price_in_riel", type="number", format="float", example=24600),
 *   @OA\Property(property="line_total_usd", type="number", format="float", example=60),
 *   @OA\Property(property="line_total_riel", type="number", format="float", example=246000)
 * )
 */
interface SaleOrderAPIControllerInterface
{
    /**
     * @OA\Get(
     *     path="/api/sale-orders",
     *     tags={"Sale Orders"},
     *     security={{"Bearer":{}}},
     *     summary="Get all sale orders",
     *     description="Retrieve a paginated list of sale orders.",
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", example=10)),
     *     @OA\Parameter(name="filter[search]", in="query", required=false, @OA\Schema(type="string", example="SO-20260422")),
     *     @OA\Parameter(name="sort", in="query", required=false, @OA\Schema(type="string", example="-created_at")),
     *     @OA\Response(
     *         response=200,
     *         description="Sale orders retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Success"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=500, description="Failed getting sale orders")
     * )
     */
    public function index(Request $request);

    /**
     * @OA\Get(
     *     path="/api/sale-orders/statistics",
     *     tags={"Sale Orders"},
     *     security={{"Bearer":{}}},
     *     summary="Get sale order statistics",
     *     @OA\Parameter(name="date_from", in="query", required=false, @OA\Schema(type="string", format="date", example="2026-04-01")),
     *     @OA\Parameter(name="date_to", in="query", required=false, @OA\Schema(type="string", format="date", example="2026-04-30")),
     *     @OA\Parameter(name="group_by", in="query", required=false, @OA\Schema(type="string", enum={"day","week","month","year"}, example="month")),
     *     @OA\Parameter(name="customer_id", in="query", required=false, @OA\Schema(type="integer", example=12)),
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string", example="COMPLETED,PROCESSING")),
     *     @OA\Response(response=200, description="Sale order statistics retrieved successfully"),
     *     @OA\Response(response=500, description="Failed getting sale order statistics")
     * )
     */
    public function statistics(Request $request);

    /**
     * @OA\Get(
     *     path="/api/sale-orders/statistics/report",
     *     tags={"Sale Orders"},
     *     security={{"Bearer":{}}},
     *     summary="Download sale order statistics report (PDF)",
     *     @OA\Parameter(name="date_from", in="query", required=false, @OA\Schema(type="string", format="date", example="2026-04-01")),
     *     @OA\Parameter(name="date_to", in="query", required=false, @OA\Schema(type="string", format="date", example="2026-04-30")),
     *     @OA\Parameter(name="group_by", in="query", required=false, @OA\Schema(type="string", enum={"day","week","month","year"}, example="month")),
     *     @OA\Response(response=200, description="PDF report generated successfully"),
     *     @OA\Response(response=500, description="Failed generating sale order statistics report")
     * )
     */
    public function statisticsReport(Request $request);
    public function saleOrderReport(int $id);

    /**
     * @OA\Get(
     *     path="/api/sale-orders/{id}",
     *     tags={"Sale Orders"},
     *     security={{"Bearer":{}}},
     *     summary="Get sale order by ID",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(
     *         response=200,
     *         description="Sale order retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Success"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Sale order not found"),
     *     @OA\Response(response=500, description="Failed getting sale order")
     * )
     */
    public function show(int $id);

    /**
     * @OA\Get(
     *     path="/api/sale-orders/refund-records",
     *     tags={"Sale Orders"},
     *     security={{"Bearer":{}}},
     *     summary="Get paginated refund records",
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", example=10)),
     *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string", example="RF-20260429")),
     *     @OA\Parameter(name="date_from", in="query", required=false, @OA\Schema(type="string", format="date", example="2026-04-01")),
     *     @OA\Parameter(name="date_to", in="query", required=false, @OA\Schema(type="string", format="date", example="2026-04-30")),
     *     @OA\Response(response=200, description="Refund records retrieved successfully"),
     *     @OA\Response(response=500, description="Failed getting refund records")
     * )
     */
    public function refundRecords(Request $request);

    /**
     * @OA\Get(
     *     path="/api/sale-orders/{id}/refunds",
     *     tags={"Sale Orders"},
     *     security={{"Bearer":{}}},
     *     summary="Get refund history for a sale order",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(response=200, description="Refund history retrieved successfully"),
     *     @OA\Response(response=404, description="Sale order not found"),
     *     @OA\Response(response=500, description="Failed getting refund history")
     * )
     */
    public function refunds(int $id);

    /**
     * @OA\Post(
     *     path="/api/sale-orders",
     *     tags={"Sale Orders"},
     *     security={{"Bearer":{}}},
     *     summary="Create a new sale order",
     *     description="Creates a new sale order in DRAFT status by default and validates product stock shortfalls.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"order_date","items"},
     *             @OA\Property(property="customer_id", type="integer", nullable=true, example=5),
     *             @OA\Property(property="order_date", type="string", format="date-time", example="2026-04-22 10:30:00"),
     *             @OA\Property(property="payment_status", type="string", enum={"PAID","INSTALLMENT","DEBT"}, example="INSTALLMENT"),
     *             @OA\Property(property="note", type="string", nullable=true, example="POS sale"),
     *             @OA\Property(property="tax_percentage", type="number", format="float", example=0),
     *             @OA\Property(property="discount_type", type="string", enum={"AUTO","MANUAL"}, example="AUTO"),
     *             @OA\Property(property="discount_value", type="number", format="float", nullable=true, example=5, description="Used when discount_type=MANUAL. Percentage value 0-100."),
     *             @OA\Property(property="use_customer_category_discount", type="boolean", example=true, description="Legacy compatibility field."),
     *             @OA\Property(property="discount_percentage", type="number", format="float", nullable=true, example=5, description="Legacy compatibility field."),
     *             @OA\Property(
     *                 property="items",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"product_id","quantity"},
     *                     @OA\Property(property="product_id", type="integer", example=10),
     *                     @OA\Property(property="quantity", type="number", format="float", example=2),
     *                     @OA\Property(property="note", type="string", nullable=true, example="Urgent")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Sale order created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Sale order created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="sale_order",
     *                     type="object",
     *                     @OA\Property(
     *                         property="order_items",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="sale_movement_id", type="integer", nullable=true, example=300),
     *                             @OA\Property(
     *                                 property="allocation_summary",
     *                                 type="object",
     *                                 @OA\Property(property="sale_method", type="string", enum={"FIFO","LIFO"}, example="FIFO"),
     *                                 @OA\Property(property="total_quantity", type="number", format="float", example=10),
     *                                 @OA\Property(property="total_amount_usd", type="number", format="float", example=55),
     *                                 @OA\Property(property="average_unit_price_usd", type="number", format="float", example=5.5),
     *                                 @OA\Property(property="lots", type="array", @OA\Items(ref="#/components/schemas/ProductMovementAllocation"))
     *                             )
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or insufficient stock",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Insufficient product stock"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="product_id", type="integer", example=10),
     *                     @OA\Property(property="product_name", type="string", example="Finished Product A"),
     *                     @OA\Property(property="product_sku_code", type="string", example="PRD-ABC-001"),
     *                     @OA\Property(property="required_qty", type="number", format="float", example=200),
     *                     @OA\Property(property="available_qty", type="number", format="float", example=50),
     *                     @OA\Property(property="shortfall_qty", type="number", format="float", example=150)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=500, description="Failed creating sale order")
     * )
     */
    public function store(Request $request);

    /**
     * @OA\Patch(
     *     path="/api/sale-orders/{id}",
     *     tags={"Sale Orders"},
     *     security={{"Bearer":{}}},
     *     summary="Update sale order",
        *     description="DRAFT orders support full edits. PROCESSING/ON_HOLD/COMPLETED only allow payment updates. CANCELLED/REFUNDED are locked.",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="customer_id", type="integer", nullable=true, example=5),
     *             @OA\Property(property="order_date", type="string", format="date-time", example="2026-04-23 10:30:00"),
     *             @OA\Property(property="payment_status", type="string", enum={"PAID","INSTALLMENT","DEBT"}, example="INSTALLMENT"),
     *             @OA\Property(property="note", type="string", nullable=true, example="Update sale order"),
     *             @OA\Property(property="tax_percentage", type="number", format="float", example=0),
     *             @OA\Property(property="discount_type", type="string", enum={"AUTO","MANUAL"}, example="AUTO"),
     *             @OA\Property(property="discount_value", type="number", format="float", nullable=true, example=5, description="Used when discount_type=MANUAL. Percentage value 0-100."),
     *             @OA\Property(property="use_customer_category_discount", type="boolean", example=true, description="Legacy compatibility field."),
     *             @OA\Property(property="discount_percentage", type="number", format="float", nullable=true, example=5, description="Legacy compatibility field."),
     *             @OA\Property(
     *                 property="items",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"product_id","quantity"},
     *                     @OA\Property(property="product_id", type="integer", example=10),
     *                     @OA\Property(property="quantity", type="number", format="float", example=3),
     *                     @OA\Property(property="note", type="string", nullable=true, example="Update item")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Sale order updated successfully"),
    *     @OA\Response(response=422, description="Validation error, insufficient stock, or completed order cannot be updated"),
     *     @OA\Response(response=404, description="Sale order not found"),
     *     @OA\Response(response=500, description="Failed updating sale order")
     * )
     */
    public function update(Request $request, int $id);

    /**
    * @OA\Patch(
    *     path="/api/sale-orders/{id}/status",
    *     tags={"Sale Orders"},
    *     security={{"Bearer":{}}},
    *     summary="Update sale order status",
    *     description="Handles sale order status transition from DRAFT to COMPLETED path (excluding REFUNDED). Stock is deducted when status becomes COMPLETED using FIFO/LIFO stock-lot allocation. Sale order items are linked to sale_movement_id and allocation_summary.",
    *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
    *     @OA\RequestBody(
    *         required=true,
    *         @OA\JsonContent(
    *             required={"order_status"},
    *             @OA\Property(property="order_status", type="string", enum={"DRAFT","PROCESSING","ON_HOLD","CANCELLED","COMPLETED"}, example="COMPLETED"),
    *             @OA\Property(property="payment_status", type="string", enum={"PAID","INSTALLMENT","DEBT"}, example="INSTALLMENT")
    *         )
    *     ),
    *     @OA\Response(response=200, description="Sale order status updated successfully"),
    *     @OA\Response(response=422, description="Validation error, invalid status transition, or insufficient stock"),
    *     @OA\Response(response=404, description="Sale order not found"),
    *     @OA\Response(response=500, description="Failed updating sale order status")
    * )
    */
    public function updateStatus(Request $request, int $id);

    /**
     * @OA\Post(
     *     path="/api/sale-orders/{id}/installments",
     *     tags={"Sale Orders"},
     *     security={{"Bearer":{}}},
     *     summary="Record a new installment payment (append-only)",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"payment_percentage"},
     *             @OA\Property(property="payment_percentage", type="number", format="float", example=15, description="New installment percentage to add (not cumulative)."),
     *             @OA\Property(property="paid_at", type="string", format="date-time", nullable=true, example="2026-04-29 15:00:00"),
     *             @OA\Property(property="note", type="string", nullable=true, example="Second installment collected")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Installment recorded successfully"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Failed recording installment")
     * )
     */
    public function addInstallment(Request $request, int $id);

    /**
     * @OA\Post(
     *     path="/api/sale-orders/{id}/payments",
     *     tags={"Sale Orders"},
     *     security={{"Bearer":{}}},
     *     summary="Record sale order payment by installment percentage",
     *     description="Payment entries are append-only and amount fields are calculated from percentage. Payment status type can only be changed while order status is DRAFT.",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"payment_status","payment_percentage"},
     *             @OA\Property(property="payment_status", type="string", enum={"PAID","INSTALLMENT","DEBT"}, example="INSTALLMENT"),
     *             @OA\Property(property="payment_percentage", type="number", format="float", example=30, description="New installment payment percentage to add, not cumulative percentage."),
     *             @OA\Property(property="paid_at", type="string", format="date-time", nullable=true, example="2026-04-29 15:00:00"),
     *             @OA\Property(property="note", type="string", nullable=true, example="First installment")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Sale order payment recorded successfully"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Failed recording sale order payment")
     * )
     */
    public function addPayment(Request $request, int $id);
    public function updateLatestInstallment(Request $request, int $id);

    /**
     * @OA\Patch(
     *     path="/api/sale-orders/{id}/refund",
     *     tags={"Sale Orders"},
     *     security={{"Bearer":{}}},
     *     summary="Process sale order refund",
     *     description="Refund is allowed only when sale order status is COMPLETED. Each line can either return to stock or be scrapped. Refund quantity is tracked per sale order item.",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"reason_type","reason","items"},
     *             @OA\Property(property="refund_type", type="string", enum={"CASH_REFUND","PARTIAL_REFUND","DISCOUNT_COMPENSATION"}, example="CASH_REFUND"),
     *             @OA\Property(property="refund_method", type="string", enum={"CASH","BANK_TRANSFER","STORE_CREDIT","DISCOUNT_COMPENSATION"}, example="CASH"),
     *             @OA\Property(property="reason_type", type="string", enum={"PRODUCT_ISSUE","CUSTOMER_SATISFACTION","COMPENSATION","OTHER"}, example="PRODUCT_ISSUE"),
     *             @OA\Property(property="reason", type="string", example="Product damaged"),
     *             @OA\Property(property="processed_at", type="string", format="date-time", nullable=true, example="2026-04-28 15:00:00"),
     *             @OA\Property(property="movement_date", type="string", format="date-time", nullable=true, example="2026-04-28 15:00:00"),
     *             @OA\Property(property="note", type="string", nullable=true, example="Customer returned damaged products"),
     *             @OA\Property(
     *                 property="items",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"sale_order_item_id","quantity"},
     *                     @OA\Property(property="sale_order_item_id", type="integer", example=12),
     *                     @OA\Property(property="product_id", type="integer", example=10, description="Optional fallback matcher when sale_order_item_id is not provided."),
     *                     @OA\Property(property="quantity", type="number", format="float", example=1),
     *                     @OA\Property(property="process_return", type="boolean", example=true),
     *                     @OA\Property(property="process_refund", type="boolean", example=true),
     *                     @OA\Property(property="is_resellable", type="boolean", nullable=true, example=true),
     *                     @OA\Property(property="return_action", type="string", enum={"RETURN_TO_STOCK","SCRAP","NO_RETURN"}, example="RETURN_TO_STOCK"),
     *                     @OA\Property(property="refund_percentage", type="number", format="float", nullable=true, example=100),
     *                     @OA\Property(property="refund_amount_override_in_usd", type="number", format="float", nullable=true, example=20),
     *                     @OA\Property(property="reason", type="string", nullable=true, example="Customer changed mind"),
     *                     @OA\Property(property="note", type="string", nullable=true, example="Returned item damaged / expired / not resellable")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Sale order refund processed successfully"),
     *     @OA\Response(response=422, description="Validation error or order not eligible for refund"),
     *     @OA\Response(response=404, description="Sale order not found"),
     *     @OA\Response(response=500, description="Failed processing refund")
     * )
     */
    public function refund(Request $request, int $id);

    /**
     * @OA\Delete(
     *     path="/api/sale-orders/{id}",
     *     tags={"Sale Orders"},
     *     security={{"Bearer":{}}},
     *     summary="Delete sale order",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(response=200, description="Sale order deleted successfully"),
     *     @OA\Response(response=404, description="Sale order not found"),
     *     @OA\Response(response=500, description="Failed deleting sale order")
     * )
     */
    public function delete(int $id);

    /**
     * @OA\Get(
     *     path="/api/sale-orders/stock-availability/{productId}",
     *     tags={"Sale Orders"},
     *     security={{"Bearer":{}}},
     *     summary="Get product stock availability",
     *     @OA\Parameter(name="productId", in="path", required=true, @OA\Schema(type="integer", example=10)),
     *     @OA\Response(response=200, description="Stock availability retrieved successfully"),
     *     @OA\Response(response=404, description="Product not found"),
     *     @OA\Response(response=500, description="Failed getting stock availability")
     * )
     */
    public function getStockAvailability(int $productId);
}
