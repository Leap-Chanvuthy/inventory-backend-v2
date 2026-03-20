<?php

namespace App\Http\Controllers\API\Interfaces;

use Illuminate\Http\Request;

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
     *         name="filter[base_uom_id]",
     *         in="query",
     *         description="Filter by base UOM ID (base_uom_id)",
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
    public function index(Request $request);

    /**
     * @OA\Get(
     *     path="/api/raw-materials/{id}",
     *     tags={"Raw Materials"},
     *     security={{"Bearer":{}}},
    *     summary="Get a raw material by ID",
    *     description="Retrieve a single raw material with relations: category, supplier, warehouse, base UOM (base_uom_id), stock movements, images.",
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
    public function show(int $id);

    /**
     * @OA\Get(
     *     path="/api/raw-materials/deleted",
     *     tags={"Raw Materials"},
     *     security={{"Bearer":{}}},
     *     summary="Get deleted (soft-deleted) raw materials",
     *     description="Retrieve a paginated list of soft-deleted raw materials. Useful for restore/review workflows.",
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
     *         description="Search by material name / SKU / related names",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="Sort fields (e.g. -deleted_at, material_name)",
     *         @OA\Schema(type="string", example="-deleted_at")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Deleted raw materials retrieved successfully",
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
    public function allDeleted(Request $request);

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
    *                 required={"material_name","minimum_stock_level","expiry_date","raw_material_category_id","base_uom_id","warehouse_id","quantity","unit_price_in_usd","exchange_rate_from_usd_to_riel"},
     *
     *                 @OA\Property(property="material_name", type="string", example="Steel Sheet"),
     *                 @OA\Property(property="minimum_stock_level", type="number", example=10),
     *                 @OA\Property(property="expiry_date", type="string", format="date", example="2026-12-01"),
     *                 @OA\Property(property="description", type="string", nullable=true, example="Some note"),
     *
     *                 @OA\Property(property="raw_material_category_id", type="integer", example=1),
    *                 @OA\Property(property="base_uom_id", type="integer", example=1, description="Base unit id used for storing stock (base_uom_id)"),
    *                 @OA\Property(property="supplier_id", type="integer", nullable=true, example=1, description="Optional; stored on raw_materials"),
     *                 @OA\Property(property="warehouse_id", type="integer", example=1),
     *
     *                 @OA\Property(property="quantity", type="number", example=50),
     *                 @OA\Property(property="unit_price_in_usd", type="number", example=2.5, description="Used to compute totals"),
     *                 @OA\Property(property="exchange_rate_from_usd_to_riel", type="number", example=4100),
     *                 @OA\Property(property="note", type="string", nullable=true, example="Purchased from supplier A"),
     *                 @OA\Property(property="production_method", type="string", nullable=true, example="FIFO", description="Optional; if not provided, defaults to FIFO. Stored on raw_materials for informational purposes but does not affect stock movement logic."),
     * 
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
    public function store(Request $request);

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
    *             required={"material_name","minimum_stock_level","expiry_date","raw_material_category_id","base_uom_id","warehouse_id","quantity","unit_price_in_usd","exchange_rate_from_usd_to_riel"},
     *
     *             @OA\Property(property="material_name", type="string", example="Steel Sheet"),
     *             @OA\Property(property="barcode", type="string", nullable=true, example="123456789"),
     *             @OA\Property(property="minimum_stock_level", type="number", example=10),
     *             @OA\Property(property="expiry_date", type="string", format="date", example="2026-12-01"),
     *             @OA\Property(property="description", type="string", nullable=true, example="Some note"),
     *
    *             @OA\Property(property="raw_material_category_id", type="integer", example=1),
    *             @OA\Property(property="base_uom_id", type="integer", example=1, description="Base unit id used for storing stock (base_uom_id)"),
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
    public function update(Request $request, int $id);

    /**
     * @OA\Delete(
     *     path="/api/raw-materials/{id}",
     *     tags={"Raw Materials"},
     *     security={{"Bearer":{}}},
     *     summary="Soft-delete a raw material",
     *     description="Soft-deletes the raw material with the given id. The model must use SoftDeletes for restore to work.",
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
     *         description="Raw material deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Raw material deleted successfully"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Cannot delete this raw material"),
     *             @OA\Property(property="errors", type="string", example="Exception message here")
     *         )
     *     )
     * )
     */
    public function delete(int $id);

    /**
     * @OA\Patch(
     *     path="/api/raw-materials/{id}/recover",
     *     tags={"Raw Materials"},
     *     security={{"Bearer":{}}},
     *     summary="Recover (restore) a soft-deleted raw material",
     *     description="Restores a previously soft-deleted raw material. The RawMaterial model must use SoftDeletes.",
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
     *         description="Raw material recovered successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Raw material recovered successfully"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Cannot recover this raw material"),
     *             @OA\Property(property="errors", type="string", example="Exception message here")
     *         )
     *     )
     * )
     */
    public function recover(int $id);



    /**
     * @OA\Delete(
     *     path="/api/raw-materials/{rawMaterialId}/images",
     *     tags={"Raw Materials"},
     *     security={{"Bearer":{}}},
     *     summary="Delete raw material images",
     *     description="Deletes one or more raw material images. Send an array of image IDs (use a single ID in the array to delete one image at a time). Images are deleted from storage and removed from the rm_images table.",
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
     *             required={"image_ids"},
     *             @OA\Property(
     *                 property="image_ids",
     *                 type="array",
     *                 description="Array of rm_images IDs to delete (max 4)",
     *                 @OA\Items(type="integer", example=10)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Raw material image(s) deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Raw material image(s) deleted successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="deleted_image_ids", type="array", @OA\Items(type="integer", example=10)),
     *                 @OA\Property(property="deleted_count", type="integer", example=1)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Raw Material not found or some images not found for this raw material"
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
    public function deleteImages(Request $request, int $rawMaterialId);



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
    public function reorder(Request $request, int $rawMaterialId);



    /**
     * @OA\Patch(
     *     path="/api/raw-materials/{rawMaterialId}/reorder/{movementId}",
     *     tags={"Raw Materials"},
     *     security={{"Bearer":{}}},
     *     summary="Update reorder stock movement (RE_ORDER / IN)",
     *     description="Updates an existing RE_ORDER stock movement. Only accepts quantity, unit_price_in_usd, exchange_rate_from_usd_to_riel, movement_date, and note. Other currency fields are computed server-side. If the movement is already in use, update is blocked.",
     *
     *     @OA\Parameter(
     *         name="rawMaterialId",
     *         in="path",
     *         required=true,
     *         description="Raw material ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="movementId",
     *         in="path",
     *         required=true,
     *         description="Stock movement ID",
     *         @OA\Schema(type="integer", example=100)
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
     *             @OA\Property(property="note", type="string", nullable=true, example="Updated reorder info")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Raw material reordered updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Raw material reordered updated successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Cannot update used stock movement",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Cannot update used stock movement"),
     *             @OA\Property(property="errors", type="string", nullable=true, example="The reordered material has been used. Data cannot be updated to avoid data inconsistency.")
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
     *         description="Internal server error"
     *     )
     * )
     */
    public function updateReorder(Request $request, int $rawMaterialId, int $movementId);



    /**
     * @OA\Post(
     *     path="/api/raw-materials/{rawMaterialId}/adjustment-out",
     *     tags={"Raw Materials"},
     *     security={{"Bearer":{}}},
     *     summary="Stock adjustment out (creates ADJUSTMENT_OUT / OUT movement)",
     *     description="Creates a stock movement with movement_type=ADJUSTMENT_OUT and direction=OUT. Pricing fields are forced to 0 (not calculated). Note is required. Fails if deduction quantity is greater than current stock quantity.",
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
     *             required={"quantity","note"},
     *             @OA\Property(property="quantity", type="number", example=2),
     *             @OA\Property(property="movement_date", type="string", format="date-time", nullable=true, example="2026-02-10 09:15:00"),
     *             @OA\Property(property="note", type="string", example="Damaged items")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Raw material adjustment out successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Raw material adjustment out successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Insufficient stock quantity",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Insuffiecient stock quantity"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="quantity",
     *                     type="array",
     *                     @OA\Items(type="string", example="Stock deduction qty must not be greater than current stock quantity.")
     *                 )
     *             )
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
     *         description="Internal server error"
     *     )
     * )
     */
    public function adjustmentOut(Request $request, int $rawMaterialId);
}
