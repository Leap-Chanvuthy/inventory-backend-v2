<?php

namespace App\Http\Controllers\API\Interfaces;

/**
 * @OA\Tag(
 *     name="Raw Materials",
 *     description="API Endpoints for managing raw materials (including initial PURCHASE stock movement and images)"
 * )
 */
interface RawMaterialAPIControllerInterface
{
    /**
     * @OA\Get(
     *     path="/api/raw-materials",
     *     tags={"Raw Materials"},
     *     security={{"Bearer":{}}},
     *     summary="Get raw materials (pagination, filters, sorting)",
     *     description="Retrieve a paginated list of raw materials. Supports filtering/sorting depending on the query builder implementation.",
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page (default 10, max 100)",
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Parameter(
     *         name="filter[search]",
     *         in="query",
     *         description="Search by material name / SKU / barcode / related names (depends on implementation)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="filter[raw_material_category_id]",
     *         in="query",
     *         description="Filter by category ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="filter[uom_id]",
     *         in="query",
     *         description="Filter by UOM ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="filter[supplier_id]",
     *         in="query",
     *         description="Filter by supplier ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="filter[warehouse_id]",
     *         in="query",
     *         description="Filter by warehouse ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="Sort fields (e.g. -created_at, material_name)",
     *         @OA\Schema(type="string", example="-created_at")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Raw materials retrieved successfully",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Something went wrong"),
     *             @OA\Property(property="errors", type="string", example="Exception message here")
     *         )
     *     )
     * )
     */
    public function index();

    /**
     * @OA\Get(
     *     path="/api/raw-materials/{id}",
     *     tags={"Raw Materials"},
     *     security={{"Bearer":{}}},
     *     summary="Get a raw material by ID",
     *     description="Retrieve a single raw material with relations: category, supplier, warehouse, uom, stock movements, images.",
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Raw material ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Raw material retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Raw Material retrieved successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Raw Material not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Raw Material not found"),
     *             @OA\Property(property="errors", type="string", example=null)
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Something went wrong"),
     *             @OA\Property(property="errors", type="string", example="Exception message here")
     *         )
     *     )
     * )
     */
    public function show();

    /**
     * @OA\Post(
     *     path="/api/raw-materials/create",
     *     tags={"Raw Materials"},
     *     security={{"Bearer":{}}},
     *     summary="Create raw material (with initial PURCHASE stock movement + optional images)",
     *     description="Creates raw material main data, then automatically creates one RM stock movement (movement_type=PURCHASE, direction=IN, movement_date=now). Supports up to 4 images.",
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
    *                 required={"material_name","minimum_stock_level","expiry_date","raw_material_category_id","uom_id","warehouse_id","quantity","unit_price_in_usd","exchange_rate_from_usd_to_riel"},
     *
     *                 @OA\Property(property="material_name", type="string", example="Steel Sheet"),
     *                 @OA\Property(property="minimum_stock_level", type="number", example=10),
     *                 @OA\Property(property="expiry_date", type="string", format="date", example="2026-12-01"),
     *                 @OA\Property(property="description", type="string", nullable=true, example="Some note"),
     *
     *                 @OA\Property(property="raw_material_category_id", type="integer", example=1),
     *                 @OA\Property(property="uom_id", type="integer", example=1),
    *                 @OA\Property(property="supplier_id", type="integer", nullable=true, example=1, description="Optional; stored on raw_materials"),
     *                 @OA\Property(property="warehouse_id", type="integer", example=1),
     *
     *                 @OA\Property(property="quantity", type="number", example=50),
     *                 @OA\Property(property="unit_price_in_usd", type="number", example=2.5, description="Used to compute totals"),
     *                 @OA\Property(property="exchange_rate_from_usd_to_riel", type="number", example=4100),
     *                 @OA\Property(property="note", type="string", nullable=true, example="Purchased from supplier A"),
     *
     *                 @OA\Property(
     *                     description="Single image (jpeg/png/jpg, <=2MB)",
     *                     property="image",
     *                     type="string",
     *                     format="binary"
     *                 ),
     *                 @OA\Property(
     *                     description="Multiple images (max 4, each <=2MB)",
     *                     property="images[]",
     *                     type="array",
     *                     @OA\Items(type="string", format="binary")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Raw material created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Raw material created successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation Error"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Something went wrong"),
     *             @OA\Property(property="errors", type="string", example="Exception message here")
     *         )
     *     )
     * )
     */
    public function store();

    /**
     * @OA\Patch(
     *     path="/api/raw-materials/{id}",
     *     tags={"Raw Materials"},
     *     security={{"Bearer":{}}},
     *     summary="Update raw material + its PURCHASE stock movement",
     *     description="Updates raw material fields and updates the existing PURCHASE stock movement fields (quantity, unit_price_in_usd, exchange_rate_from_usd_to_riel, note, movement_date). PURCHASE movement type cannot be changed. Supplier is stored on raw_materials.",
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Raw material ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"material_name","minimum_stock_level","expiry_date","raw_material_category_id","uom_id","warehouse_id","quantity","unit_price_in_usd","exchange_rate_from_usd_to_riel"},
     *
     *             @OA\Property(property="material_name", type="string", example="Steel Sheet"),
     *             @OA\Property(property="barcode", type="string", nullable=true, example="123456789"),
     *             @OA\Property(property="minimum_stock_level", type="number", example=10),
     *             @OA\Property(property="expiry_date", type="string", format="date", example="2026-12-01"),
     *             @OA\Property(property="description", type="string", nullable=true, example="Some note"),
     *
     *             @OA\Property(property="raw_material_category_id", type="integer", example=1),
     *             @OA\Property(property="uom_id", type="integer", example=1),
     *             @OA\Property(property="supplier_id", type="integer", nullable=true, example=1),
     *             @OA\Property(property="warehouse_id", type="integer", example=1),
     *
     *             @OA\Property(property="quantity", type="number", example=50, description="PURCHASE movement quantity"),
     *             @OA\Property(property="unit_price_in_usd", type="number", example=2.5, description="PURCHASE unit price"),
     *             @OA\Property(property="exchange_rate_from_usd_to_riel", type="number", example=4100, description="PURCHASE exchange rate"),
     *             @OA\Property(property="movement_date", type="string", format="date-time", nullable=true, example="2026-02-04 10:30:00"),
     *             @OA\Property(property="note", type="string", nullable=true, example="Updated purchase info")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Raw material updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Raw material updated successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Raw Material not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error"
     *     )
     * )
     */
    public function update();



    /**
     * @OA\Post(
     *     path="/api/raw-materials/{rawMaterialId}/reorder",
     *     tags={"Raw Materials"},
     *     security={{"Bearer":{}}},
     *     summary="Reorder raw material stock (creates RE_ORDER / IN movement)",
     *     description="Creates a stock movement with movement_type=RE_ORDER and direction=IN. Pricing/valuation is computed from quantity, unit_price_in_usd and exchange_rate_from_usd_to_riel.",
     *
     *     @OA\Parameter(
     *         name="rawMaterialId",
     *         in="path",
     *         required=true,
     *         description="Raw material ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"quantity","unit_price_in_usd","exchange_rate_from_usd_to_riel"},
     *             @OA\Property(property="quantity", type="number", example=10),
     *             @OA\Property(property="unit_price_in_usd", type="number", example=5),
     *             @OA\Property(property="exchange_rate_from_usd_to_riel", type="number", example=4100),
     *             @OA\Property(property="movement_date", type="string", format="date-time", nullable=true, example="2026-02-03 10:30:00"),
     *             @OA\Property(property="note", type="string", nullable=true, example="Reorder due to low stock")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Raw material reordered successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Raw material reordered successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error"
     *     )
     * )
     */
    public function reorder();
}
