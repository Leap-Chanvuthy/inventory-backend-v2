<?php

namespace App\Http\Controllers\API\Interfaces;

use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Sale Orders",
 *     description="API Endpoints for managing sale orders. (Only Accessible for: ADMIN and CUSTOMER Users)"
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
     *             @OA\Property(property="payment_status", type="string", enum={"PAID","UNPAID","DEBT"}, example="UNPAID"),
     *             @OA\Property(property="note", type="string", nullable=true, example="POS sale"),
     *             @OA\Property(property="tax_percentage", type="number", format="float", example=0),
     *             @OA\Property(property="use_customer_category_discount", type="boolean", example=true),
     *             @OA\Property(property="discount_percentage", type="number", format="float", nullable=true, example=5),
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
     *             @OA\Property(property="data", type="object")
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
        *     description="Updates sale order details only. Order status is managed by a dedicated status endpoint. Completed sale orders cannot be updated.",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="customer_id", type="integer", nullable=true, example=5),
     *             @OA\Property(property="order_date", type="string", format="date-time", example="2026-04-23 10:30:00"),
     *             @OA\Property(property="payment_status", type="string", enum={"PAID","UNPAID","DEBT"}, example="UNPAID"),
     *             @OA\Property(property="note", type="string", nullable=true, example="Update sale order"),
     *             @OA\Property(property="tax_percentage", type="number", format="float", example=0),
     *             @OA\Property(property="use_customer_category_discount", type="boolean", example=true),
     *             @OA\Property(property="discount_percentage", type="number", format="float", nullable=true, example=5),
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
    *     description="Handles sale order status transition from DRAFT to COMPLETED path (excluding REFUNDED). Stock is deducted when status becomes COMPLETED, FIFO/LIFO is respected, consumed IN movements are flagged with is_sold=true, and payment_status can be updated in the same request.",
    *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
    *     @OA\RequestBody(
    *         required=true,
    *         @OA\JsonContent(
    *             required={"order_status"},
    *             @OA\Property(property="order_status", type="string", enum={"DRAFT","PROCESSING","ON_HOLD","CANCELLED","COMPLETED"}, example="COMPLETED"),
    *             @OA\Property(property="payment_status", type="string", enum={"PAID","UNPAID","DEBT"}, example="UNPAID")
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
