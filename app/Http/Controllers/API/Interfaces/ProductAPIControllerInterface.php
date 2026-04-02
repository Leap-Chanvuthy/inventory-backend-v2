<?php

namespace App\Http\Controllers\API\Interfaces;

/**
 * @OA\Tag(
 *     name="Products",
 *     description="API Endpoints for managing products (external purchase & internal manufacturing flows). Note: `product_type` is stored on the product record and is enforced by the server; movement records do not accept `product_type`."
 * )
 */
interface ProductAPIControllerInterface
{
    /**
     * @OA\Get(
     *     path="/api/products",
     *     tags={"Products"},
     *     security={{"Bearer":{}}},
     *     summary="Get all products (paginated, filterable, sortable)",
     *
     *     @OA\Parameter(name="per_page",            in="query", @OA\Schema(type="integer", example=10)),
     *     @OA\Parameter(name="filter[search]",      in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="filter[product_category_id]", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="filter[supplier_id]", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="filter[warehouse_id]",in="query", @OA\Schema(type="integer")),
    *     @OA\Parameter(name="filter[base_uom_id]",      in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="sort",                in="query", @OA\Schema(type="string", example="-created_at")),
     *
     *     @OA\Response(response=200, description="Products retrieved successfully", @OA\JsonContent(type="object")),
     *     @OA\Response(response=500, description="Internal server error",           @OA\JsonContent(type="object"))
     * )
     */
    public function index();

    /**
     * @OA\Post(
     *     path="/api/products/create/external-purchase",
     *     tags={"Products"},
     *     security={{"Bearer":{}}},
     *     summary="Create an externally purchased product with initial stock movement",
        *     description="Creates a product and an initial EXTERNAL_PURCHASED / IN / COMPLETED movement. The server enforces `product_type` = EXTERNAL_PURCHASED; do NOT supply `product_type` in the request. `product_status` is automatically set to COMPLETED and cannot be changed. Supply `purchase_unit_price_in_usd` + `exchange_rate_from_usd_to_riel` (totals are derived); supply `selling_unit_price_in_usd` + `selling_exchange_rate_from_usd_to_riel` for selling price. Submitting `raw_materials` is NOT allowed and will be rejected with a validation error.",
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"product_name","product_category_id","uom_id","supplier_id","warehouse_id",
    *                           "quantity","purchase_unit_price_in_usd","exchange_rate_from_usd_to_riel",
    *                           "selling_unit_price_in_usd","selling_exchange_rate_from_usd_to_riel","base_uom_id"},
     *
     *                 @OA\Property(property="product_name",           type="string",  example="Widget A"),
     *                 @OA\Property(property="barcode",                type="string",  example="1234567890"),
     *                 @OA\Property(property="product_description",    type="string"),
     *                 @OA\Property(property="product_category_id",    type="integer", example=1),
    *                 @OA\Property(property="base_uom_id",                 type="integer", example=1, description="Base unit id used for storing stock (base_uom_id)"),
     *                 @OA\Property(property="supplier_id",            type="integer", example=1),
     *                 @OA\Property(property="warehouse_id",           type="integer", example=1),
     *
     *                 @OA\Property(property="quantity",                        type="number", example=100),
     *                 @OA\Property(property="purchase_unit_price_in_usd",      type="number", example=5.50),
     *                 @OA\Property(property="exchange_rate_from_usd_to_riel",  type="number", example=4100),
     *                 @OA\Property(property="selling_unit_price_in_usd",       type="number", example=8.00),
     *                 @OA\Property(property="selling_exchange_rate_from_usd_to_riel", type="number", example=4100),
     *
     *                 @OA\Property(property="movement_date", type="string", format="date-time"),
     *                 @OA\Property(property="note",          type="string"),
     *
     *                 @OA\Property(property="images",   type="array", @OA\Items(type="string", format="binary"))
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="Product created successfully",  @OA\JsonContent(type="object")),
     *     @OA\Response(response=422, description="Validation error",              @OA\JsonContent(type="object")),
     *     @OA\Response(response=500, description="Internal server error",         @OA\JsonContent(type="object"))
     * )
     */
    public function storeExternalPurchase();

    /**
     * @OA\Post(
     *     path="/api/products/create/internal-manufacturing",
     *     tags={"Products"},
     *     security={{"Bearer":{}}},
     *     summary="Create an internally manufactured product with initial stock movement",
        *     description="Creates a product and an initial INTERNAL_PRODUCED / IN movement. The server enforces `product_type` = INTERNAL_PRODUCED and will ignore any supplied `supplier_id`. Purchase price is automatically set to 0 (produced internally). Supply `raw_materials[]` BOM to record which raw materials are consumed. Raw material stock is validated (FIFO/LIFO) and deducted automatically via PRODUCTION_RECEIPT OUT movements. Supply `selling_unit_price_in_usd` + `selling_exchange_rate_from_usd_to_riel` for selling price.",
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
   *             @OA\Schema(
   *                 required={"product_name","product_category_id","uom_id","warehouse_id",
   *                           "quantity","product_status","selling_unit_price_in_usd","selling_exchange_rate_from_usd_to_riel",
   *                           "raw_materials"},
     *
     *                 @OA\Property(property="product_name",           type="string",  example="Assembled Part B"),
     *                 @OA\Property(property="barcode",                type="string"),
     *                 @OA\Property(property="product_description",    type="string"),
     *                 @OA\Property(property="product_category_id",    type="integer", example=2),
    *                 @OA\Property(property="base_uom_id",                 type="integer", example=1, description="Base unit id used for storing stock (base_uom_id)"),
     *                 @OA\Property(property="supplier_id",            type="integer", example=1),
     *                 @OA\Property(property="warehouse_id",           type="integer", example=1),
     *
     *                 @OA\Property(
     *                     property="product_status",
     *                     type="string",
     *                     example="COMPLETED",
     *                     description="Current product status. Accepted values: DRAFT, WORK_IN_PROGRESS, PARTIALLY_COMPLETED, COMPLETED, BLOCKED",
     *                     enum={"DRAFT","WORK_IN_PROGRESS","PARTIALLY_COMPLETED","COMPLETED","BLOCKED"}
     *                 ),
     *
     *                 @OA\Property(property="quantity",                              type="number", example=50),
     *                 @OA\Property(property="selling_unit_price_in_usd",             type="number", example=12.00),
     *                 @OA\Property(property="selling_exchange_rate_from_usd_to_riel",type="number", example=4100),
     *
     *                 @OA\Property(property="movement_date", type="string", format="date-time"),
     *                 @OA\Property(property="note",          type="string"),
     *
     *                 @OA\Property(
     *                     property="raw_materials",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="raw_material_id", type="integer", example=3),
     *                         @OA\Property(property="quantity",        type="number",  example=2)
     *                     )
     *                 ),
     *
     *                 @OA\Property(property="images", type="array", @OA\Items(type="string", format="binary"))
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="Product created successfully",           @OA\JsonContent(type="object")),
     *     @OA\Response(response=422, description="Validation error or insufficient stock", @OA\JsonContent(type="object")),
     *     @OA\Response(response=500, description="Internal server error",                  @OA\JsonContent(type="object"))
     * )
     */
    public function storeInternalManufacturing();
}
