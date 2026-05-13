<?php

namespace App\Http\Controllers\API\Interfaces;

/**
 * @OA\Tag(name="Products - Core", description="Product listing and detail APIs")
 * @OA\Tag(name="Products - External Purchase", description="Create/update initial external purchased stock")
 * @OA\Tag(name="Products - Internal Manufacturing", description="Create/update initial internally manufactured stock")
 * @OA\Tag(name="Products - Reorder External", description="External purchase reorder create/update/detail")
 * @OA\Tag(name="Products - Reorder Internal", description="Internal manufacturing reorder create/update/detail")
 * @OA\Tag(name="Products - Scrap", description="Scrap movement create/update")
 *
 * @OA\Schema(
 *   schema="StockLot",
 *   type="object",
 *   @OA\Property(property="id", type="integer", example=12),
 *   @OA\Property(property="movement_type", type="string", example="RE_ORDER"),
 *   @OA\Property(property="product_status", type="string", nullable=true, example="COMPLETED"),
 *   @OA\Property(property="quantity", type="number", format="float", example=100),
 *   @OA\Property(property="remaining_quantity", type="number", format="float", example=85),
 *   @OA\Property(property="allocated_quantity", type="number", format="float", example=15),
 *   @OA\Property(property="lot_status", type="string", enum={"AVAILABLE","PARTIALLY_CONSUMED","CONSUMED"}, example="PARTIALLY_CONSUMED"),
 *   @OA\Property(property="selling_unit_price_in_usd", type="number", format="float", example=6.5),
 *   @OA\Property(property="selling_unit_price_in_riel", type="number", format="float", example=26650),
 *   @OA\Property(property="purchase_unit_price_in_usd", type="number", format="float", example=4.8),
 *   @OA\Property(property="purchase_unit_price_in_riel", type="number", format="float", example=19680),
 *   @OA\Property(property="movement_date", type="string", format="date-time", nullable=true)
 * )
 *
 * @OA\Schema(
 *   schema="SaleAllocationPreviewLot",
 *   type="object",
 *   @OA\Property(property="source_movement_id", type="integer", example=5),
 *   @OA\Property(property="movement_type", type="string", example="INTERNAL_PRODUCED"),
 *   @OA\Property(property="movement_date", type="string", format="date-time", nullable=true),
 *   @OA\Property(property="allocated_quantity", type="number", format="float", example=10),
 *   @OA\Property(property="remaining_quantity_before_sale", type="number", format="float", example=15),
 *   @OA\Property(property="remaining_quantity_after_sale", type="number", format="float", example=5),
 *   @OA\Property(property="selling_unit_price_in_usd", type="number", format="float", example=6),
 *   @OA\Property(property="selling_unit_price_in_riel", type="number", format="float", example=24600),
 *   @OA\Property(property="line_total_usd", type="number", format="float", example=60),
 *   @OA\Property(property="line_total_riel", type="number", format="float", example=246000)
 * )
 */
interface ProductAPIControllerInterface
{
      /**
       * @OA\Get(
       *     path="/api/products",
       *     tags={"Products - Core"},
       *     security={{"Bearer":{}}},
       *     summary="Get all products",
       *     description="Supports filtering, sorting and pagination. Use `filter[...]` query parameters as shown.",
       *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer"), description="Page number"),
       *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer"), description="Items per page (1-100)"),
       *     @OA\Parameter(name="sort", in="query", @OA\Schema(type="string"), description="Sort fields. Prefix with - for desc. Allowed: created_at,updated_at,deleted_at,product_name,product_sku_code,product_category_name,official_name,warehouse_name,uom_name,product_category_id,supplier_id,warehouse_id,base_uom_id"),
       *
       *     @OA\Parameter(name="filter[id]", in="query", @OA\Schema(type="integer"), description="Exact product id"),
       *     @OA\Parameter(name="filter[product_category_id]", in="query", @OA\Schema(type="integer"), description="Exact product category id"),
       *     @OA\Parameter(name="filter[supplier_id]", in="query", @OA\Schema(type="integer"), description="Exact supplier id"),
       *     @OA\Parameter(name="filter[warehouse_id]", in="query", @OA\Schema(type="integer"), description="Exact warehouse id"),
       *     @OA\Parameter(name="filter[base_uom_id]", in="query", @OA\Schema(type="integer"), description="Exact base UOM id"),
       *     @OA\Parameter(name="filter[product_type]", in="query", @OA\Schema(type="string"), description="Partial match on product_type"),
       *     @OA\Parameter(name="filter[search]", in="query", @OA\Schema(type="string"), description="Search across product_name, product_sku_code, category name, supplier, warehouse, uom"),
       *     @OA\Parameter(name="filter[category_name]", in="query", @OA\Schema(type="string"), description="Search by category name"),
       *
       *     @OA\Response(response=200, description="Products retrieved successfully"),
       *     @OA\Response(response=500, description="Internal server error")
       * )
       */
      public function index();

    /**
     * @OA\Get(
     *     path="/api/products/{id}",
     *     tags={"Products - Core"},
     *     security={{"Bearer":{}}},
     *     summary="Get product detail",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Product retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Success"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_qty_in_stock", type="number", format="float", example=1090),
     *                 @OA\Property(property="available_qty_in_stock", type="number", format="float", example=1090),
     *                 @OA\Property(property="ledger_qty_in_stock", type="number", format="float", example=1090),
     *                 @OA\Property(property="pricing_reference_lot", ref="#/components/schemas/StockLot", nullable=true)
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
     *     path="/api/products/{id}/sale-allocation-preview",
     *     tags={"Products - Core"},
     *     security={{"Bearer":{}}},
     *     summary="Preview FIFO/LIFO stock-batch allocation for sale quantity",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"quantity"},
     *             @OA\Property(property="quantity", type="number", format="float", example=10)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Allocation preview generated",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Sale allocation preview generated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="product_id", type="integer", example=1),
     *                 @OA\Property(property="product_name", type="string", example="Product A"),
     *                 @OA\Property(property="sale_method", type="string", enum={"FIFO","LIFO"}, example="FIFO"),
     *                 @OA\Property(property="requested_quantity", type="number", format="float", example=10),
     *                 @OA\Property(property="available_quantity", type="number", format="float", example=1090),
     *                 @OA\Property(property="can_fulfill", type="boolean", example=true),
     *                 @OA\Property(property="estimated_total_usd", type="number", format="float", example=55),
     *                 @OA\Property(property="estimated_average_unit_price_usd", type="number", format="float", example=5.5),
     *                 @OA\Property(property="lots", type="array", @OA\Items(ref="#/components/schemas/SaleAllocationPreviewLot")),
     *                 @OA\Property(property="message", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError")),
     *     @OA\Response(response=500, description="Internal server error", @OA\JsonContent(ref="#/components/schemas/ApiError"))
     * )
     */
    public function saleAllocationPreview($request, $id);

   /**
    * @OA\Get(
    *     path="/api/products/{id}/movements",
    *     tags={"Products - Core"},
    *     security={{"Bearer":{}}},
    *     summary="Get product movements",
    *     description="Returns paginated product movement records for a product. Includes UOM and creator/updater info.",
    *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
    *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer"), description="Page number"),
    *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer"), description="Items per page (1-100)"),
    *     @OA\Parameter(name="sort", in="query", @OA\Schema(type="string"), description="Sort fields: movement_date,created_at,updated_at,quantity"),
    *     @OA\Parameter(name="filter[movement_type]", in="query", @OA\Schema(type="string"), description="Filter by movement_type"),
    *     @OA\Parameter(name="filter[direction]", in="query", @OA\Schema(type="string"), description="Filter by direction"),
    *     @OA\Response(response=200, description="Movements retrieved successfully"),
    *     @OA\Response(response=404, description="Product not found"),
    *     @OA\Response(response=500, description="Internal server error")
    * )
   */
   public function movements($request, $productId);

   /**
    * @OA\Get(
    *     path="/api/products/{id}/stock-lots",
    *     tags={"Products - Core"},
    *     security={{"Bearer":{}}},
    *     summary="Get product stock lots (dedicated endpoint)",
    *     description="Returns paginated stock-IN lots for the product, with advanced search, filtering and sorting.",
    *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
    *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer"), description="Page number"),
    *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer"), description="Items per page (1-100)"),
    *     @OA\Parameter(name="sort", in="query", @OA\Schema(type="string"), description="Sort fields: movement_date,created_at,updated_at,quantity,remaining_quantity,selling_unit_price_in_usd,selling_unit_price_in_riel,purchase_unit_price_in_usd,purchase_unit_price_in_riel"),
    *     @OA\Parameter(name="filter[search]", in="query", @OA\Schema(type="string"), description="Search by movement type, product status, note, created/updated user name, or lot id"),
    *     @OA\Parameter(name="filter[movement_type]", in="query", @OA\Schema(type="string"), description="Exact movement type"),
    *     @OA\Parameter(name="filter[product_status]", in="query", @OA\Schema(type="string"), description="Exact product status"),
    *     @OA\Parameter(name="filter[lot_status]", in="query", @OA\Schema(type="string", enum={"AVAILABLE","PARTIALLY_CONSUMED","CONSUMED"}), description="Derived stock lot status"),
    *     @OA\Parameter(name="filter[has_remaining_stock]", in="query", @OA\Schema(type="boolean"), description="true => remaining_quantity > 0, false => remaining_quantity <= 0"),
    *     @OA\Response(response=200, description="Stock lots retrieved successfully"),
    *     @OA\Response(response=404, description="Product not found"),
    *     @OA\Response(response=500, description="Internal server error")
    * )
    */
   public function stockLots($request, $productId);

   /**
    * @OA\Get(
    *     path="/api/products/{id}/pnl-detail",
    *     tags={"Products - Core"},
    *     security={{"Bearer":{}}},
    *     summary="Get detailed product P&L",
    *     description="Returns detailed revenue, COGS, gross/net profit, inventory valuation, sales lines, and (for internal products) raw-material spending split by initial production vs reorder.",
    *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
    *     @OA\Response(response=200, description="Detailed product P&L retrieved successfully"),
    *     @OA\Response(response=404, description="Product not found"),
    *     @OA\Response(response=500, description="Internal server error")
    * )
    */
   public function pnlDetail($id);

    /**
     * @OA\Get(
     *     path="/api/products/trashed",
     *     tags={"Products - Core"},
     *     security={{"Bearer":{}}},
     *     summary="Get soft-deleted (trashed) products",
     *     description="Returns soft-deleted products. Supports the same filter/sort/pagination parameters as `/api/products`.",
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer"), description="Page number"),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer"), description="Items per page (1-100)"),
     *     @OA\Parameter(name="sort", in="query", @OA\Schema(type="string"), description="Sort fields. Prefix with - for desc. Allowed: created_at,updated_at,deleted_at,product_name,product_sku_code,product_category_name,official_name,warehouse_name,uom_name,product_category_id,supplier_id,warehouse_id,base_uom_id"),
     *
     *     @OA\Parameter(name="filter[id]", in="query", @OA\Schema(type="integer"), description="Exact product id"),
     *     @OA\Parameter(name="filter[product_category_id]", in="query", @OA\Schema(type="integer"), description="Exact product category id"),
     *     @OA\Parameter(name="filter[supplier_id]", in="query", @OA\Schema(type="integer"), description="Exact supplier id"),
     *     @OA\Parameter(name="filter[warehouse_id]", in="query", @OA\Schema(type="integer"), description="Exact warehouse id"),
     *     @OA\Parameter(name="filter[base_uom_id]", in="query", @OA\Schema(type="integer"), description="Exact base UOM id"),
     *     @OA\Parameter(name="filter[product_type]", in="query", @OA\Schema(type="string"), description="Partial match on product_type"),
     *     @OA\Parameter(name="filter[search]", in="query", @OA\Schema(type="string"), description="Search across product_name, product_sku_code, category name, supplier, warehouse, uom"),
     *     @OA\Parameter(name="filter[category_name]", in="query", @OA\Schema(type="string"), description="Search by category name"),
     *
     *     @OA\Response(response=200, description="Trashed products retrieved successfully"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function trashed($request);

    /**
     * @OA\Delete(
     *     path="/api/products/{id}",
     *     tags={"Products - Core"},
     *     security={{"Bearer":{}}},
     *     summary="Soft delete a product",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Product deleted successfully"),
     *     @OA\Response(response=404, description="Product not found"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function delete($id);

    /**
     * @OA\Patch(
     *     path="/api/products/{id}/restore",
     *     tags={"Products - Core"},
     *     security={{"Bearer":{}}},
     *     summary="Restore a soft-deleted product",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Product restored successfully"),
     *     @OA\Response(response=404, description="Product not found"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function restore($id);

    /**
     * @OA\Post(
     *     path="/api/products/create/external-purchase",
     *     tags={"Products - External Purchase"},
     *     security={{"Bearer":{}}},
     *     summary="Create externally purchased product",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object",
     *             @OA\Property(property="product_name", type="string", example="Test External Product"),
     *             @OA\Property(property="product_description", type="string", example="Sample external purchased product"),
    *             @OA\Property(property="sale_method", type="string", enum={"FIFO","LIFO"}, example="LIFO", description="Stock consumption method for this product."),
     *             @OA\Property(property="product_category_id", type="integer", example=1),
     *             @OA\Property(property="base_uom_id", type="integer", example=10),
     *             @OA\Property(property="supplier_id", type="integer", example=2),
     *             @OA\Property(property="warehouse_id", type="integer", example=1),
     *             @OA\Property(property="movement_date", type="string", format="date-time", example="2026-03-20T10:00:00Z"),
     *             @OA\Property(property="quantity", type="number", format="float", example=100),
     *             @OA\Property(property="purchase_unit_price_in_usd", type="number", format="float", example=5.25),
     *             @OA\Property(property="exchange_rate_from_usd_to_riel", type="number", format="float", example=4100),
     *             @OA\Property(property="selling_unit_price_in_usd", type="number", format="float", example=7.0),
     *             @OA\Property(property="selling_exchange_rate_from_usd_to_riel", type="number", format="float", example=4100),
     *             @OA\Property(property="note", type="string", example="Initial purchase for testing")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Created successfully",
     *         @OA\JsonContent(type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product created successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError")),
     *     @OA\Response(response=500, description="Internal server error", @OA\JsonContent(ref="#/components/schemas/ApiError"))
     * )
     */
    public function storeExternalPurchase();

    /**
     * @OA\Patch(
     *     path="/api/products/{id}/update/external-purchase",
     *     tags={"Products - External Purchase"},
     *     security={{"Bearer":{}}},
     *     summary="Update initial external purchased movement",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object",
     *             @OA\Property(property="product_name", type="string", example="Test Update 3"),
     *             @OA\Property(property="product_status", type="string", enum={"DRAFT","WORK_IN_PROGRESS","PARTIALLY_COMPLETED","COMPLETED","BLOCKED"}, example="COMPLETED", description="Optional on update. When provided, updates movement product status."),
     *             @OA\Property(property="product_description", type="string", example="Sample external purchased product"),
    *             @OA\Property(property="sale_method", type="string", enum={"FIFO","LIFO"}, example="LIFO", description="Optional on update. When provided, updates the product stock consumption method."),
     *             @OA\Property(property="product_category_id", type="integer", example=2),
     *             @OA\Property(property="base_uom_id", type="integer", example=10),
     *             @OA\Property(property="supplier_id", type="integer", example=2),
     *             @OA\Property(property="warehouse_id", type="integer", example=1),
     *             @OA\Property(property="movement_date", type="string", format="date-time", example="2026-03-20T10:00:00Z"),
     *             @OA\Property(property="quantity", type="number", format="float", example=200, description="If this movement is already sold, quantity is immutable and must remain unchanged."),
     *             @OA\Property(property="purchase_unit_price_in_usd", type="number", format="float", example=5.25),
     *             @OA\Property(property="exchange_rate_from_usd_to_riel", type="number", format="float", example=4100),
     *             @OA\Property(property="selling_unit_price_in_usd", type="number", format="float", example=7.0),
     *             @OA\Property(property="selling_exchange_rate_from_usd_to_riel", type="number", format="float", example=4100),
     *             @OA\Property(property="note", type="string", example="Initial purchase for testing")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Updated successfully",
     *         @OA\JsonContent(type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product updated successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error (includes sold-movement quantity immutable case)", @OA\JsonContent(ref="#/components/schemas/ValidationError")),
     *     @OA\Response(response=500, description="Internal server error", @OA\JsonContent(ref="#/components/schemas/ApiError"))
     * )
     */
    public function updateExternalPurchase($id);

    /**
     * @OA\Post(
     *     path="/api/products/create/internal-manufacturing",
     *     tags={"Products - Internal Manufacturing"},
     *     security={{"Bearer":{}}},
        *     summary="Create internally manufactured product",
        *     @OA\RequestBody(
        *         required=true,
        *         @OA\JsonContent(type="object",
        *             @OA\Property(property="product_name", type="string", example="Manufacturing Product"),
        *             @OA\Property(property="product_status", type="string", example="COMPLETED"),
        *             @OA\Property(property="product_description", type="string", example="Sample internally manufactured product for Postman test"),
      *             @OA\Property(property="sale_method", type="string", enum={"FIFO","LIFO"}, example="LIFO", description="Stock consumption method for this product."),
        *             @OA\Property(property="product_category_id", type="integer", example=1),
        *             @OA\Property(property="base_uom_id", type="integer", example=20),
        *             @OA\Property(property="supplier_id", type="integer", example=1, nullable=true),
        *             @OA\Property(property="warehouse_id", type="integer", example=1),
        *             @OA\Property(property="movement_date", type="string", format="date-time", example="2026-03-20T10:00:00Z"),
        *             @OA\Property(property="quantity", type="number", format="float", example=100),
        *             @OA\Property(property="selling_unit_price_in_usd", type="number", format="float", example=10),
        *             @OA\Property(property="selling_exchange_rate_from_usd_to_riel", type="number", format="float", example=4100),
        *             @OA\Property(property="raw_materials", type="array", description="Per-unit BOM. For each item, required_qty = production_qty × quantity_per_unit and scrap_qty = required_qty × scrap_percentage / 100.",
        *                 @OA\Items(type="object",
        *                     @OA\Property(property="raw_material_id", type="integer", example=10),
        *                     @OA\Property(property="quantity_per_unit", type="number", format="float", example=2),
        *                     @OA\Property(property="scrap_percentage", type="number", format="float", example=5)
        *                 )
        *             ),
        *             @OA\Property(property="note", type="string", example="BOM-based manufactured product")
        *         )
        *     ),
        *     @OA\Response(
        *         response=201,
        *         description="Created successfully",
        *         @OA\JsonContent(type="object",
        *             @OA\Property(property="status", type="boolean", example=true),
        *             @OA\Property(property="message", type="string", example="Product created successfully"),
        *             @OA\Property(property="data", type="object",
        *                 @OA\Property(property="product", type="object"),
        *                 @OA\Property(property="materials", type="array",
        *                     @OA\Items(type="object",
        *                         @OA\Property(property="raw_material_id", type="integer", example=10),
        *                         @OA\Property(property="required_qty", type="number", format="float", example=200),
        *                         @OA\Property(property="scrap_qty", type="number", format="float", example=10),
        *                         @OA\Property(property="total_consumption", type="number", format="float", example=210)
        *                     )
        *                 )
        *             )
        *         )
        *     ),
        *     @OA\Response(
        *         response=422,
        *         description="Validation error or insufficient stock",
        *         @OA\JsonContent(type="object",
        *             @OA\Property(property="status", type="boolean", example=false),
        *             @OA\Property(property="message", type="string", example="Insufficient raw material stock"),
        *             @OA\Property(property="errors", type="object", example={"raw_materials":{{"raw_material_id":10,"required":5}}})
        *         )
        *     ),
        *     @OA\Response(response=500, description="Internal server error", @OA\JsonContent(ref="#/components/schemas/ApiError"))
        * )
     */
    public function storeInternalManufacturing();

    /**
     * @OA\Patch(
     *     path="/api/products/{id}/update/internal-manufacturing",
     *     tags={"Products - Internal Manufacturing"},
     *     security={{"Bearer":{}}},
     *     summary="Update initial internally manufactured movement",
     *     description="BOM can be modified only when the initial INTERNAL_PRODUCED movement has not been sold yet. If sold, BOM and quantity are locked and only safe metadata/pricing fields can be updated.",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object",
     *             @OA\Property(property="product_name", type="string", example="Test Internal Product"),
     *             @OA\Property(property="product_status", type="string", enum={"DRAFT","WORK_IN_PROGRESS","PARTIALLY_COMPLETED","COMPLETED","BLOCKED"}, example="COMPLETED", description="Updates movement product status."),
     *             @OA\Property(property="product_description", type="string", example="Sample internally manufactured product for Postman test"),
    *             @OA\Property(property="sale_method", type="string", enum={"FIFO","LIFO"}, example="LIFO", description="Optional on update. When provided, updates the product stock consumption method."),
     *             @OA\Property(property="product_category_id", type="integer", example=1),
     *             @OA\Property(property="base_uom_id", type="integer", example=20),
     *             @OA\Property(property="supplier_id", type="integer", example=1, nullable=true),
     *             @OA\Property(property="warehouse_id", type="integer", example=1),
     *             @OA\Property(property="movement_date", type="string", format="date-time", example="2026-03-20T10:00:00Z"),
     *             @OA\Property(property="quantity", type="number", format="float", example=100, description="If this movement is already sold, quantity is immutable and must remain unchanged."),
     *             @OA\Property(property="selling_unit_price_in_usd", type="number", format="float", example=10),
     *             @OA\Property(property="selling_exchange_rate_from_usd_to_riel", type="number", format="float", example=4100),
     *             @OA\Property(property="raw_materials", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="raw_material_id", type="integer", example=10),
     *                     @OA\Property(property="quantity_per_unit", type="number", format="float", example=3),
     *                     @OA\Property(property="scrap_percentage", type="number", format="float", example=8)
     *                 )
     *             ),
     *             @OA\Property(property="note", type="string", example="BOM-based manufactured product")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Updated successfully",
     *         @OA\JsonContent(type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product updated successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="movement", type="object"),
     *                 @OA\Property(property="materials", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="raw_material_id", type="integer", example=10),
     *                         @OA\Property(property="required_qty", type="number", format="float", example=300),
     *                         @OA\Property(property="scrap_qty", type="number", format="float", example=24),
     *                         @OA\Property(property="total_consumption", type="number", format="float", example=324)
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error, sold-movement quantity immutable, or insufficient stock",
     *         @OA\JsonContent(type="object",
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Insufficient raw material stock"),
     *             @OA\Property(property="errors", type="object", example={"raw_materials":{{"raw_material_id":10,"required":5}}})
     *         )
     *     ),
     *     @OA\Response(response=500, description="Internal server error", @OA\JsonContent(ref="#/components/schemas/ApiError"))
     * )
     */
    public function updateInternalManufacturing($id);

    /**
     * @OA\Post(
     *     path="/api/products/{id}/reorder/external-purchase",
     *     tags={"Products - Reorder External"},
     *     security={{"Bearer":{}}},
        *     summary="Create external purchase reorder",
        *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
        *     @OA\RequestBody(
        *         required=true,
        *         @OA\JsonContent(type="object",
        *             @OA\Property(property="movement_date", type="string", format="date-time", example="2026-03-20T10:00:00Z"),
        *             @OA\Property(property="quantity", type="number", format="float", example=50),
        *             @OA\Property(property="purchase_unit_price_in_usd", type="number", format="float", example=5.5, description="Used directly from request for this reorder movement."),
        *             @OA\Property(property="exchange_rate_from_usd_to_riel", type="number", format="float", example=4100),
        *             @OA\Property(property="selling_unit_price_in_usd", type="number", format="float", example=7.5, description="Used directly from request for this reorder movement."),
        *             @OA\Property(property="selling_exchange_rate_from_usd_to_riel", type="number", format="float", example=4100),
        *             @OA\Property(property="note", type="string", example="Reorder purchase for restock")
        *         )
        *     ),
        *     @OA\Response(
        *         response=201,
        *         description="Reorder created successfully",
        *         @OA\JsonContent(type="object",
        *             @OA\Property(property="status", type="boolean", example=true),
        *             @OA\Property(property="message", type="string", example="Reorder created successfully"),
        *             @OA\Property(property="data", type="object")
        *         )
        *     ),
        *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError")),
        *     @OA\Response(response=500, description="Internal server error", @OA\JsonContent(ref="#/components/schemas/ApiError"))
        * )
     */
    public function reorderExternalPurchase($id);

    /**
     * @OA\Patch(
     *     path="/api/products/{productId}/reorder/external-purchase/{movementId}",
     *     tags={"Products - Reorder External"},
     *     security={{"Bearer":{}}},
     *     summary="Update external purchase reorder",
     *     @OA\Parameter(name="productId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="movementId", in="path", required=true, @OA\Schema(type="integer"), description="Reorder movement id"),
        *     @OA\RequestBody(
        *         required=true,
        *         @OA\JsonContent(type="object",
        *             @OA\Property(property="movement_date", type="string", format="date-time", example="2026-03-20T10:00:00Z"),
        *             @OA\Property(property="quantity", type="number", format="float", example=75),
        *             @OA\Property(property="purchase_unit_price_in_usd", type="number", format="float", example=5.75, description="Used directly from request for this reorder movement."),
        *             @OA\Property(property="exchange_rate_from_usd_to_riel", type="number", format="float", example=4100),
        *             @OA\Property(property="selling_unit_price_in_usd", type="number", format="float", example=8.0, description="Used directly from request for this reorder movement."),
        *             @OA\Property(property="selling_exchange_rate_from_usd_to_riel", type="number", format="float", example=4100),
        *             @OA\Property(property="note", type="string", example="Update reorder movement")
        *         )
        *     ),
        *     @OA\Response(
        *         response=201,
        *         description="Reorder updated successfully",
        *         @OA\JsonContent(type="object",
        *             @OA\Property(property="status", type="boolean", example=true),
        *             @OA\Property(property="message", type="string", example="Reorder updated successfully"),
        *             @OA\Property(property="data", type="object")
        *         )
        *     ),
        *     @OA\Response(response=422, description="Validation error (includes sold-movement quantity immutable case)", @OA\JsonContent(ref="#/components/schemas/ValidationError")),
        *     @OA\Response(response=404, description="Movement not found", @OA\JsonContent(ref="#/components/schemas/ApiError")),
        *     @OA\Response(response=500, description="Internal server error", @OA\JsonContent(ref="#/components/schemas/ApiError"))
        * )
     */
    public function updateReorderExternalPurchase($productId);

    /**
     * @OA\Get(
     *     path="/api/products/{productId}/reorder/external-purchase/{movementId}",
     *     tags={"Products - Reorder External"},
     *     security={{"Bearer":{}}},
     *     summary="Get external purchase reorder detail",
     *     @OA\Parameter(name="productId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="movementId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Reorder detail retrieved successfully"),
     *     @OA\Response(response=404, description="Movement not found or invalid for product"),
     *     @OA\Response(response=422, description="Invalid product type"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function getReorderExternalPurchase($productId, $movementId);

    /**
     * @OA\Delete(
     *     path="/api/products/{productId}/reorder/external-purchase/{movementId}",
     *     tags={"Products - Reorder External"},
     *     security={{"Bearer":{}}},
     *     summary="Delete external purchase reorder movement",
     *     description="Deletes a reorder movement for a product if it has not been used in sales.",
     *     @OA\Parameter(name="productId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="movementId", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Product reorder deleted successfully",
     *         @OA\JsonContent(type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product reorder deleted successfully"),
     *             @OA\Property(property="data", type="null", nullable=true, example=null)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Cannot delete used stock movement",
     *         @OA\JsonContent(type="object",
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Cannot delete used stock movement"),
     *             @OA\Property(property="errors", type="object",
     *                 @OA\Property(property="movement_id", type="array", @OA\Items(type="string", example="Movement has been sold and cannot be deleted"))
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Movement not found or not a reorder movement",
     *         @OA\JsonContent(type="object",
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Movement not found or not a reorder movement"),
     *             @OA\Property(property="errors", type="null", nullable=true, example=null)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Invalid product type",
     *         @OA\JsonContent(type="object",
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid product type for external purchase reorder deletion"),
     *             @OA\Property(property="errors", type="object",
     *                 @OA\Property(property="product_type", type="array", @OA\Items(type="string", example="Product must be of type EXTERNAL_PURCHASE"))
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiError", example={"status":false,"message":"Server error: Unexpected exception","errors":null})
     *     )
     * )
     */
    public function deleteReorderExternalPurchase($productId, $movementId);

   /**
    * @OA\Post(
    *     path="/api/products/{id}/reorder/internal-manufacturing",
    *     tags={"Products - Reorder Internal"},
    *     security={{"Bearer":{}}},
    *     summary="Create internal manufacturing reorder",
    *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
    *     @OA\RequestBody(
    *         required=true,
    *         @OA\JsonContent(type="object",
    *             @OA\Property(property="movement_date", type="string", format="date-time", example="2026-03-20T10:00:00Z"),
   *             @OA\Property(property="product_status", type="string", format="text", example="COMPLETED"),
    *             @OA\Property(property="quantity", type="number", format="float", example=100),
    *             @OA\Property(property="selling_unit_price_in_usd", type="number", format="float", example=10),
    *             @OA\Property(property="selling_exchange_rate_from_usd_to_riel", type="number", format="float", example=4100),
    *             @OA\Property(property="bom_override", type="array", description="Optional scrap override for this reorder transaction only. BOM structure and quantity_per_unit remain locked.",
    *                 @OA\Items(type="object",
    *                     @OA\Property(property="raw_material_id", type="integer", example=10),
    *                     @OA\Property(property="scrap_percentage", type="number", format="float", example=8)
    *                 )
    *             ),
    *             @OA\Property(property="note", type="string", example="BOM-based reorder for restock")
    *         )
    *     ),
    *     @OA\Response(
    *         response=201,
    *         description="Reorder created successfully",
    *         @OA\JsonContent(type="object",
    *             @OA\Property(property="status", type="boolean", example=true),
    *             @OA\Property(property="message", type="string", example="Product reordered (internal manufacturing) successfully"),
    *             @OA\Property(property="data", type="object",
    *                 @OA\Property(property="movement", type="object"),
    *                 @OA\Property(property="product_reorder_id", type="integer", example=12),
    *                 @OA\Property(property="materials", type="array",
    *                     @OA\Items(type="object",
    *                         @OA\Property(property="raw_material_id", type="integer", example=10),
    *                         @OA\Property(property="required_qty", type="number", format="float", example=200),
    *                         @OA\Property(property="scrap_qty", type="number", format="float", example=16),
    *                         @OA\Property(property="total_consumption", type="number", format="float", example=216)
    *                     )
    *                 )
    *             )
    *         )
    *     ),
    *     @OA\Response(
    *         response=422,
    *         description="Validation error, immutable BOM violation, or insufficient raw material stock",
    *         @OA\JsonContent(type="object",
    *             @OA\Property(property="status", type="boolean", example=false),
    *             @OA\Property(property="message", type="string", example="Insufficient raw material stock"),
    *             @OA\Property(property="errors", type="object", example={"raw_materials":{{"raw_material_id":10,"required":5}}})
    *         )
    *     ),
    *     @OA\Response(response=500, description="Internal server error", @OA\JsonContent(ref="#/components/schemas/ApiError"))
    * )
    */
   public function reorderInternalManufacturing($id);

   /**
    * @OA\Patch(
    *     path="/api/products/{productId}/reorder/internal-manufacturing/{movementId}",
    *     tags={"Products - Reorder Internal"},
    *     security={{"Bearer":{}}},
    *     summary="Update internal manufacturing reorder",
    *     @OA\Parameter(name="productId", in="path", required=true, @OA\Schema(type="integer")),
    *     @OA\Parameter(name="movementId", in="path", required=true, @OA\Schema(type="integer"), description="Reorder movement id"),
    *     @OA\RequestBody(
    *         required=true,
    *         @OA\JsonContent(type="object",
    *             @OA\Property(property="movement_date", type="string", format="date-time", example="2026-03-20T10:00:00Z"),
    *             @OA\Property(property="product_status", type="string", format="text", example="COMPLETED"),
    *             @OA\Property(property="quantity", type="number", format="float", example=120, description="If this movement is already sold, quantity is immutable and must remain unchanged."),
    *             @OA\Property(property="selling_unit_price_in_usd", type="number", format="float", example=11),
    *             @OA\Property(property="selling_exchange_rate_from_usd_to_riel", type="number", format="float", example=4100),
    *             @OA\Property(property="bom_override", type="array", description="Optional scrap override for this reorder update only.",
    *                 @OA\Items(type="object",
    *                     @OA\Property(property="raw_material_id", type="integer", example=10),
    *                     @OA\Property(property="scrap_percentage", type="number", format="float", example=6)
    *                 )
    *             ),
    *             @OA\Property(property="note", type="string", example="Update reorder (internal)")
    *         )
    *     ),
    *     @OA\Response(
    *         response=201,
    *         description="Reorder updated successfully",
    *         @OA\JsonContent(type="object",
    *             @OA\Property(property="status", type="boolean", example=true),
    *             @OA\Property(property="message", type="string", example="Product reorder (internal manufacturing) updated successfully"),
    *             @OA\Property(property="data", type="object",
    *                 @OA\Property(property="movement", type="object"),
    *                 @OA\Property(property="materials", type="array",
    *                     @OA\Items(type="object",
    *                         @OA\Property(property="raw_material_id", type="integer", example=10),
    *                         @OA\Property(property="required_qty", type="number", format="float", example=240),
    *                         @OA\Property(property="scrap_qty", type="number", format="float", example=14.4),
    *                         @OA\Property(property="total_consumption", type="number", format="float", example=254.4)
    *                     )
    *                 )
    *             )
    *         )
    *     ),
    *     @OA\Response(
    *         response=422,
    *         description="Validation error, sold-movement quantity immutable, immutable BOM violation, or insufficient stock",
    *         @OA\JsonContent(type="object",
    *             @OA\Property(property="status", type="boolean", example=false),
    *             @OA\Property(property="message", type="string", example="Insufficient raw material stock"),
    *             @OA\Property(property="errors", type="object", example={"raw_materials":{{"raw_material_id":10,"required":5}}})
    *         )
    *     ),
    *     @OA\Response(response=404, description="Movement not found", @OA\JsonContent(ref="#/components/schemas/ApiError")),
    *     @OA\Response(response=500, description="Internal server error", @OA\JsonContent(ref="#/components/schemas/ApiError"))
    * )
    */
   public function updateReorderInternalManufacturing($productId);

   
   /**
      * @OA\Delete(
      *     path="/api/products/{productId}/reorder/internal-manufacturing/{movementId}",
      *     tags={"Products - Reorder Internal"},
      *     security={{"Bearer":{}}},
      *     summary="Delete internal manufacturing reorder movement",
      *     description="Deletes a reorder movement for a product if it has not been used in sales.",
      *     @OA\Parameter(name="productId", in="path", required=true, @OA\Schema(type="integer")),
      *     @OA\Parameter(name="movementId", in="path", required=true, @OA\Schema(type="integer")),
      *
      *     @OA\Response(
      *         response=200,
      *         description="Product reorder deleted successfully",
      *         @OA\JsonContent(type="object",
      *             @OA\Property(property="status", type="boolean", example=true),
      *             @OA\Property(property="message", type="string", example="Product reorder (internal manufacturing) deleted successfully"),
      *             @OA\Property(property="data", type="null", nullable=true, example=null)
      *         )
      *     ),
      *
      *     @OA\Response(
      *         response=401,
      *         description="Cannot delete used stock movement",
      *         @OA\JsonContent(type="object",
      *             @OA\Property(property="status", type="boolean", example=false),
      *             @OA\Property(property="message", type="string", example="Cannot delete used stock movement"),
      *             @OA\Property(property="errors", type="object",
      *                 @OA\Property(property="movement_id", type="array", @OA\Items(type="string", example="Movement has been sold and cannot be deleted"))
      *             )
      *         )
      *     ),
      *
      *     @OA\Response(
      *         response=404,
      *         description="Movement not found or not a reorder movement",
      *         @OA\JsonContent(type="object",
      *             @OA\Property(property="status", type="boolean", example=false),
      *             @OA\Property(property="message", type="string", example="Movement not found or not a reorder movement"),
      *             @OA\Property(property="errors", type="null", nullable=true, example=null)
      *         )
      *     ),
      *     @OA\Response(
      *         response=422,
      *         description="Invalid product type",
      *         @OA\JsonContent(type="object",
      *             @OA\Property(property="status", type="boolean", example=false), 
      *             @OA\Property(property="message", type="string", example="Invalid product type for internal manufacturing reorder deletion"),
      *             @OA\Property(property="errors", type="object",
      *                 @OA\Property(property="product_type", type="array", @OA\Items(type="string", example="Product must be of type INTERNAL_MANUFACTURING"))
      *             )
      *         )
      *     ),
      *     @OA\Response(
      *         response=500, description="Internal server error",
      *         @OA\JsonContent(ref="#/components/schemas/ApiError", example={"status":false,"message":"Server error: Unexpected exception","errors":null})
      *     )
      * )
      */
    public function deleteReorderInternalManufacturing($productId, $movementId);

    /**
     * @OA\Get(
     *     path="/api/products/{productId}/reorder/internal-manufacturing/{movementId}",
     *     tags={"Products - Reorder Internal"},
     *     security={{"Bearer":{}}},
     *     summary="Get internal manufacturing reorder detail",
     *     @OA\Parameter(name="productId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="movementId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Reorder detail retrieved successfully"),
     *     @OA\Response(response=404, description="Movement not found or invalid for product"),
     *     @OA\Response(response=422, description="Invalid product type"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function getReorderInternalManufacturing($productId, $movementId);

   /**
    * @OA\Post(
    *     path="/api/products/{id}/scrap",
    *     tags={"Products - Scrap"},
    *     security={{"Bearer":{}}},
    *     summary="Create product scrap movement",
    *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
    *     @OA\RequestBody(
    *         required=true,
    *         @OA\JsonContent(type="object",
    *             @OA\Property(property="movement_date", type="string", format="date-time", example="2026-03-20T10:00:00Z"),
    *             @OA\Property(property="quantity", type="number", format="float", example=5),
    *             @OA\Property(property="note", type="string", example="Scrapped due to defect")
    *         )
    *     ),
    *     @OA\Response(
    *         response=201,
    *         description="Product scrapped successfully",
    *         @OA\JsonContent(type="object",
    *             @OA\Property(property="status", type="boolean", example=true),
    *             @OA\Property(property="message", type="string", example="Product scrapped successfully"),
    *             @OA\Property(property="data", type="object")
    *         )
    *     ),
    *     @OA\Response(response=422, description="Validation error or insufficient stock", @OA\JsonContent(ref="#/components/schemas/ValidationError")),
    *     @OA\Response(response=500, description="Internal server error", @OA\JsonContent(ref="#/components/schemas/ApiError"))
    * )
    */
   public function createScrap($id);


    /**
     * @OA\Get(
     *     path="/api/products/{productId}/scrap/{movementId}",
     *     tags={"Products - Scrap"},
     *     security={{"Bearer":{}}},
     *     summary="Get product scrap movement detail",
     *     description="Retrieves scrap movement detail. Validates that the movement belongs to the product and that the movement type is SCRAP.",
     *     @OA\Parameter(name="productId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="movementId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Scrap movement detail retrieved successfully"),
     *     @OA\Response(response=404, description="Movement not found or not a scrap movement"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function getScrap($productId, $movementId);

   /**
    * @OA\Patch(
    *     path="/api/products/{productId}/scrap/{movementId}",
    *     tags={"Products - Scrap"},
    *     security={{"Bearer":{}}},
    *     summary="Update product scrap movement",
    *     @OA\Parameter(name="productId", in="path", required=true, @OA\Schema(type="integer")),
    *     @OA\Parameter(name="movementId", in="path", required=true, @OA\Schema(type="integer")),
    *     @OA\RequestBody(
    *         required=true,
    *         @OA\JsonContent(type="object",
    *             @OA\Property(property="movement_date", type="string", format="date-time", example="2026-03-21T10:00:00Z"),
    *             @OA\Property(property="quantity", type="number", format="float", example=3),
    *             @OA\Property(property="note", type="string", example="Adjusted scrap quantity")
    *         )
    *     ),
    *     @OA\Response(
    *         response=201,
    *         description="Product scrap updated successfully",
    *         @OA\JsonContent(type="object",
    *             @OA\Property(property="status", type="boolean", example=true),
    *             @OA\Property(property="message", type="string", example="Product scrap updated successfully"),
    *             @OA\Property(property="data", type="object")
    *         )
    *     ),
    *     @OA\Response(
    *         response=401,
    *         description="Cannot update used stock movement",
    *         @OA\JsonContent(type="object",
    *             @OA\Property(property="status", type="boolean", example=false),
    *             @OA\Property(property="message", type="string", example="Cannot update used stock movement"),
    *             @OA\Property(property="errors", type="null", nullable=true, example=null)
    *         )
    *     ),
    *     @OA\Response(response=422, description="Validation error or insufficient stock", @OA\JsonContent(ref="#/components/schemas/ValidationError")),
    *     @OA\Response(response=404, description="Movement not found", @OA\JsonContent(ref="#/components/schemas/ApiError")),
    *     @OA\Response(response=500, description="Internal server error", @OA\JsonContent(ref="#/components/schemas/ApiError"))
    * )
    */
   public function updateScrap($productId, $movementId);
}
