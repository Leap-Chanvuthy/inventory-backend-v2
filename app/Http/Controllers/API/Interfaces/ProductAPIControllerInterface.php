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
     *     @OA\Parameter(name="filter[product_type]",     in="query", @OA\Schema(type="string", example="EXTERNAL_PURCHASED")),
     *     @OA\Parameter(name="sort",                in="query", @OA\Schema(type="string", example="-created_at")),
     *
     *     @OA\Response(response=200, description="Products retrieved successfully", @OA\JsonContent(type="object")),
     *     @OA\Response(response=500, description="Internal server error",           @OA\JsonContent(type="object"))
     * )
     */
    public function index();

   /**
    * @OA\Get(
    *     path="/api/products/{id}",
    *     tags={"Products"},
    *     security={{"Bearer":{}}},
    *     summary="Get product detail",
    *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
    *     @OA\Response(
    *         response=200,
    *         description="Product retrieved successfully",
    *         @OA\JsonContent(
    *             type="object",
    *             @OA\Property(property="product", type="object", description="Product resource with related UOMs, movements, images and BOM"),
    *             @OA\Property(property="current_qty_in_stock", type="number", example=150),
    *             @OA\Property(property="product_stock_status", type="string", example="IN_STOCK", enum={"IN_STOCK","OUT_OF_STOCK"}),
    *             @OA\Property(
    *                 property="total_count_by_movement_type",
    *                 type="object",
    *                 description="Map of movement_type => count",
    *                 @OA\Property(property="EXTERNAL_PURCHASED", type="integer", example=3),
    *                 @OA\Property(property="INTERNAL_PRODUCED", type="integer", example=1)
    *             )
    *         )
    *     ),
    *     @OA\Response(response=404, description="Product not found"),
    *     @OA\Response(response=500, description="Internal server error")
    * )
    */
   public function show($id);

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

   /**
    * @OA\Post(
    *     path="/api/products/{id}/reorder/external-purchase",
    *     tags={"Products"},
    *     security={{"Bearer":{}}},
    *     summary="Create a reorder movement for an externally purchased product",
    *     description="Creates a RE_ORDER / IN product movement. Supply purchase and selling unit prices in USD and the USD->Riel exchange rates. Totals and Riel equivalents are derived by the server.",
    *
    *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
    *
    *     @OA\RequestBody(
    *         required=true,
    *         @OA\MediaType(
    *             mediaType="application/json",
    *             @OA\Schema(
    *                 required={"quantity","purchase_unit_price_in_usd","exchange_rate_from_usd_to_riel","selling_unit_price_in_usd","selling_exchange_rate_from_usd_to_riel"},
    *                 @OA\Property(property="quantity", type="number", example=100),
    *                 @OA\Property(property="purchase_unit_price_in_usd", type="number", example=12.5),
    *                 @OA\Property(property="exchange_rate_from_usd_to_riel", type="number", example=4100),
    *                 @OA\Property(property="selling_unit_price_in_usd", type="number", example=15.0),
    *                 @OA\Property(property="selling_exchange_rate_from_usd_to_riel", type="number", example=4100),
    *                 @OA\Property(property="movement_date", type="string", format="date-time", example="2026-04-04T10:00:00Z"),
    *                 @OA\Property(property="note", type="string", example="Reorder due to low stock")
    *             )
    *         )
    *     ),
    *
    *     @OA\Response(response=201, description="Reorder created successfully", @OA\JsonContent(type="object")),
    *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(type="object")),
    *     @OA\Response(response=500, description="Internal server error", @OA\JsonContent(type="object"))
    * )
    */
   public function reorderExternalPurchase($id);

   /**
    * @OA\Patch(
    *     path="/api/products/{productId}/reorder/external-purchase/{movementId}",
    *     tags={"Products"},
    *     security={{"Bearer":{}}},
    *     summary="Update an existing reorder movement for an externally purchased product",
    *     description="Update a previously created RE_ORDER / IN movement. Updates are blocked if the movement has been used/sold.",
    *
    *     @OA\Parameter(name="productId", in="path", required=true, @OA\Schema(type="integer", example=1)),
    *     @OA\Parameter(name="movementId", in="path", required=true, @OA\Schema(type="integer", example=10)),
    *
    *     @OA\RequestBody(
    *         required=true,
    *         @OA\MediaType(
    *             mediaType="application/json",
    *             @OA\Schema(
    *                 required={"quantity","purchase_unit_price_in_usd","exchange_rate_from_usd_to_riel","selling_unit_price_in_usd","selling_exchange_rate_from_usd_to_riel"},
    *                 @OA\Property(property="quantity", type="number", example=100),
    *                 @OA\Property(property="purchase_unit_price_in_usd", type="number", example=12.5),
    *                 @OA\Property(property="exchange_rate_from_usd_to_riel", type="number", example=4100),
    *                 @OA\Property(property="selling_unit_price_in_usd", type="number", example=15.0),
    *                 @OA\Property(property="selling_exchange_rate_from_usd_to_riel", type="number", example=4100),
    *                 @OA\Property(property="movement_date", type="string", format="date-time", example="2026-04-04T10:00:00Z"),
    *                 @OA\Property(property="note", type="string", example="Updated reorder note")
    *             )
    *         )
    *     ),
    *
    *     @OA\Response(response=201, description="Reorder updated successfully", @OA\JsonContent(type="object")),
    *     @OA\Response(response=401, description="Cannot update used stock movement", @OA\JsonContent(type="object")),
    *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(type="object")),
    *     @OA\Response(response=500, description="Internal server error", @OA\JsonContent(type="object"))
    * )
    */
   public function updateReorderExternalPurchase($productId, $movementId);

   /**
    * @OA\Post(
    *     path="/api/products/{id}/reorder/internal-manufacturing",
    *     tags={"Products"},
    *     security={{"Bearer":{}}},
    *     summary="Create a reorder movement for an internally manufactured product",
    *     description="Creates a RE_ORDER / IN product movement for an internally manufactured product. Purchase costs are zero and raw materials (BOM) must be supplied; raw material stock will be validated and deducted.",
    *
    *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
    *
    *     @OA\RequestBody(
    *         required=true,
    *         @OA\MediaType(
    *             mediaType="application/json",
    *             @OA\Schema(
    *                 required={"quantity","selling_unit_price_in_usd","selling_exchange_rate_from_usd_to_riel","raw_materials"},
    *                 @OA\Property(property="quantity", type="number", example=50),
    *                 @OA\Property(property="selling_unit_price_in_usd", type="number", example=12.0),
    *                 @OA\Property(property="selling_exchange_rate_from_usd_to_riel", type="number", example=4100),
    *                 @OA\Property(property="movement_date", type="string", format="date-time", example="2026-04-04T10:00:00Z"),
    *                 @OA\Property(property="note", type="string", example="Production run"),
    *                 @OA\Property(
    *                     property="raw_materials",
    *                     type="array",
    *                     @OA\Items(
    *                         @OA\Property(property="raw_material_id", type="integer", example=3),
    *                         @OA\Property(property="quantity", type="number", example=2)
    *                     )
    *                 )
    *             )
    *         )
    *     ),
    *
    *     @OA\Response(response=201, description="Reorder created successfully", @OA\JsonContent(type="object")),
    *     @OA\Response(response=422, description="Validation error or insufficient raw material stock", @OA\JsonContent(type="object")),
    *     @OA\Response(response=500, description="Internal server error", @OA\JsonContent(type="object"))
    * )
    */
   public function reorderInternalManufacturing($id);

   /**
    * @OA\Patch(
    *     path="/api/products/{productId}/reorder/internal-manufacturing/{movementId}",
    *     tags={"Products"},
    *     security={{"Bearer":{}}},
    *     summary="Update an existing internal-manufacturing reorder movement",
    *     description="Update a previously created RE_ORDER / IN movement for an internally manufactured product. Updates are blocked if the movement has been used/sold. Raw material adjustments are not performed on update.",
    *
    *     @OA\Parameter(name="productId", in="path", required=true, @OA\Schema(type="integer", example=1)),
    *     @OA\Parameter(name="movementId", in="path", required=true, @OA\Schema(type="integer", example=10)),
    *
    *     @OA\RequestBody(
    *         required=true,
    *         @OA\MediaType(
    *             mediaType="application/json",
    *             @OA\Schema(
    *                 required={"quantity","selling_unit_price_in_usd","selling_exchange_rate_from_usd_to_riel"},
    *                 @OA\Property(property="quantity", type="number", example=50),
    *                 @OA\Property(property="selling_unit_price_in_usd", type="number", example=12.0),
    *                 @OA\Property(property="selling_exchange_rate_from_usd_to_riel", type="number", example=4100),
    *                 @OA\Property(property="movement_date", type="string", format="date-time", example="2026-04-04T10:00:00Z"),
    *                 @OA\Property(property="note", type="string", example="Adjusted production quantity")
    *             )
    *         )
    *     ),
    *
    *     @OA\Response(response=201, description="Reorder updated successfully", @OA\JsonContent(type="object")),
    *     @OA\Response(response=401, description="Cannot update used stock movement", @OA\JsonContent(type="object")),
    *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(type="object")),
    *     @OA\Response(response=500, description="Internal server error", @OA\JsonContent(type="object"))
    * )
    */
   public function updateReorderInternalManufacturing($productId, $movementId);

   /**
    * @OA\Post(
    *     path="/api/products/{id}/scrap",
    *     tags={"Products"},
    *     security={{"Bearer":{}}},
    *     summary="Create a scrap movement for a product",
    *     description="Creates a SCRAP / OUT movement for a product. Financial fields are set to zero for scrapped stock.",
    *
    *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
    *
    *     @OA\RequestBody(
    *         required=true,
    *         @OA\MediaType(
    *             mediaType="application/json",
    *             @OA\Schema(
    *                 required={"quantity"},
    *                 @OA\Property(property="quantity", type="number", example=5),
    *                 @OA\Property(property="movement_date", type="string", format="date-time", example="2026-04-04T10:00:00Z"),
    *                 @OA\Property(property="note", type="string", example="Damaged items scrapped")
    *             )
    *         )
    *     ),
    *
    *     @OA\Response(response=201, description="Product scrapped successfully", @OA\JsonContent(type="object")),
    *     @OA\Response(response=422, description="Validation error or insufficient stock", @OA\JsonContent(type="object")),
    *     @OA\Response(response=500, description="Internal server error", @OA\JsonContent(type="object"))
    * )
    */
   public function createScrap($id);

   /**
    * @OA\Patch(
    *     path="/api/products/{productId}/scrap/{movementId}",
    *     tags={"Products"},
    *     security={{"Bearer":{}}},
    *     summary="Update an existing scrap movement for a product",
    *     description="Update a previously created SCRAP / OUT movement. Financial fields remain zero. Updates are blocked if the movement has been used/sold.",
    *
    *     @OA\Parameter(name="productId", in="path", required=true, @OA\Schema(type="integer", example=1)),
    *     @OA\Parameter(name="movementId", in="path", required=true, @OA\Schema(type="integer", example=10)),
    *
    *     @OA\RequestBody(
    *         required=true,
    *         @OA\MediaType(
    *             mediaType="application/json",
    *             @OA\Schema(
    *                 required={"quantity"},
    *                 @OA\Property(property="quantity", type="number", example=3),
    *                 @OA\Property(property="movement_date", type="string", format="date-time", example="2026-04-04T10:00:00Z"),
    *                 @OA\Property(property="note", type="string", example="Adjusted scrapped qty")
    *             )
    *         )
    *     ),
    *
    *     @OA\Response(response=201, description="Scrap updated successfully", @OA\JsonContent(type="object")),
    *     @OA\Response(response=401, description="Cannot update used stock movement", @OA\JsonContent(type="object")),
    *     @OA\Response(response=422, description="Validation error or insufficient stock", @OA\JsonContent(type="object")),
    *     @OA\Response(response=500, description="Internal server error", @OA\JsonContent(type="object"))
    * )
    */
   public function updateScrap($productId, $movementId);
}
