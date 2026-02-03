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
     *                 required={"material_name","minimum_stock_level","expiry_date","raw_material_category_id","uom_id","supplier_id","warehouse_id","quantity","unit_price_in_usd","exchange_rate_from_usd_to_riel"},
     *
     *                 @OA\Property(property="material_name", type="string", example="Steel Sheet"),
     *                 @OA\Property(property="minimum_stock_level", type="number", example=10),
     *                 @OA\Property(property="expiry_date", type="string", format="date", example="2026-12-01"),
     *                 @OA\Property(property="description", type="string", nullable=true, example="Some note"),
     *
     *                 @OA\Property(property="raw_material_category_id", type="integer", example=1),
     *                 @OA\Property(property="uom_id", type="integer", example=1),
     *                 @OA\Property(property="supplier_id", type="integer", example=1),
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
}
