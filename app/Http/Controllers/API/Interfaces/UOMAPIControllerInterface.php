<?php

namespace App\Http\Controllers\API\Interfaces;

use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *   name="UOM",
 *   description="Unit of Measurement management. (Only Accessible for: ADMIN & STOCK_CONTROLLER Users)"
 * )
 *
 * @OA\Schema(
 *   schema="UOM",
 *   type="object",
 *   required={"id","uom_code","name","uom_type","is_active","created_at","updated_at"},
 *   @OA\Property(property="id", type="integer", example=1),
 *   @OA\Property(property="uom_code", type="string", example="UOM00000001"),
 *   @OA\Property(property="name", type="string", example="Kilogram"),
 *   @OA\Property(property="symbol", type="string", nullable=true, example="kg"),
 *   @OA\Property(property="uom_type", type="string", example="WEIGHT"),
 *   @OA\Property(property="description", type="string", nullable=true, example="Metric weight unit"),
 *   @OA\Property(property="is_active", type="boolean", example=true),
 *   @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-22T10:00:00Z"),
 *   @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-22T10:00:00Z")
 * )
 *
 * @OA\Schema(
 *   schema="UOMCreateRequest",
 *   type="object",
 *   required={"name","uom_type"},
 *   @OA\Property(property="name", type="string", example="Piece"),
 *   @OA\Property(property="symbol", type="string", nullable=true, example="pc"),
 *   @OA\Property(property="uom_type", type="string", example="COUNT"),
 *   @OA\Property(property="description", type="string", nullable=true, example="Count-based UOM"),
 *   @OA\Property(property="is_active", type="boolean", example=true)
 * )
 *
 * @OA\Schema(
 *   schema="UOMUpdateRequest",
 *   type="object",
 *   required={"name","uom_type"},
 *   @OA\Property(property="name", type="string", example="Piece"),
 *   @OA\Property(property="symbol", type="string", nullable=true, example="pc"),
 *   @OA\Property(property="uom_type", type="string", example="COUNT"),
 *   @OA\Property(property="description", type="string", nullable=true, example="Updated description"),
 *   @OA\Property(property="is_active", type="boolean", example=true)
 * )
 *
 * @OA\Schema(
 *   schema="PaginationLinks",
 *   type="object",
 *   @OA\Property(property="first", type="string", nullable=true, example="https://api.example.com/uoms?page=1"),
 *   @OA\Property(property="last", type="string", nullable=true, example="https://api.example.com/uoms?page=10"),
 *   @OA\Property(property="prev", type="string", nullable=true, example=null),
 *   @OA\Property(property="next", type="string", nullable=true, example="https://api.example.com/uoms?page=2")
 * )
 *
 * @OA\Schema(
 *   schema="PaginationMeta",
 *   type="object",
 *   @OA\Property(property="current_page", type="integer", example=1),
 *   @OA\Property(property="from", type="integer", nullable=true, example=1),
 *   @OA\Property(property="last_page", type="integer", example=10),
 *   @OA\Property(property="path", type="string", example="https://api.example.com/uoms"),
 *   @OA\Property(property="per_page", type="integer", example=10),
 *   @OA\Property(property="to", type="integer", nullable=true, example=10),
 *   @OA\Property(property="total", type="integer", example=100)
 * )
 *
 * @OA\Schema(
 *   schema="UOMPagination",
 *   type="object",
 *   @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/UOM")),
 *   @OA\Property(property="links", ref="#/components/schemas/PaginationLinks"),
 *   @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
 * )
 *
 * @OA\Schema(
 *   schema="ApiError",
 *   type="object",
 *   @OA\Property(property="message", type="string", example="UOM not found"),
 *   @OA\Property(property="errors", type="object", nullable=true)
 * )
 *
 * @OA\Schema(
 *   schema="ValidationError",
 *   type="object",
 *   @OA\Property(property="message", type="string", example="The given data was invalid."),
 *   @OA\Property(
 *     property="errors",
 *     type="object",
 *     example={"name":{"The name has already been taken."},"uom_code":{"The uom code field is required."}}
 *   )
 * )
 *
 * @OA\Schema(
 *   schema="UOMConvertRequest",
 *   type="object",
 *   required={"quantity", "from_uom_id", "to_uom_id"},
 *   @OA\Property(property="quantity", type="number", example=100, description="Quantity to convert"),
 *   @OA\Property(property="from_uom_id", type="integer", example=1, description="Source UOM ID"),
 *   @OA\Property(property="to_uom_id", type="integer", example=2, description="Target UOM ID")
 * )
 *
 * @OA\Schema(
 *   schema="UOMConvertResponse",
 *   type="object",
 *   @OA\Property(property="status", type="boolean", example=true),
 *   @OA\Property(property="message", type="string", example="Conversion successful"),
 *   @OA\Property(
 *     property="data",
 *     type="object",
 *     @OA\Property(property="original_quantity", type="number", example=100),
 *     @OA\Property(
 *       property="from_uom",
 *       type="object",
 *       @OA\Property(property="id", type="integer", example=1),
 *       @OA\Property(property="name", type="string", example="Kilogram"),
 *       @OA\Property(property="symbol", type="string", example="kg")
 *     ),
 *     @OA\Property(
 *       property="to_uom",
 *       type="object",
 *       @OA\Property(property="id", type="integer", example=2),
 *       @OA\Property(property="name", type="string", example="Gram"),
 *       @OA\Property(property="symbol", type="string", example="g")
 *     ),
 *     @OA\Property(property="converted_quantity", type="number", example=100000)
 *   )
 * )
 */
interface UOMAPIControllerInterface
{
    /**
     * List UOMs (paginated) with filtering/search/sorting.
     *
     * @OA\Get(
     *   path="/api/uoms",
     *   tags={"UOM"},
     *     security={{"Bearer":{}}},
     *   @OA\Parameter(
     *     name="per_page",
     *     in="query",
     *     required=false,
     *     description="Items per page (1..100). Default 10.",
     *     @OA\Schema(type="integer", example=10)
     *   ),
     *   @OA\Parameter(
     *     name="filter[search]",
     *     in="query",
     *     required=false,
     *     description="Search in name, symbol, uom_type, uom_code.",
     *     @OA\Schema(type="string")
     *   ),
     *   @OA\Parameter(
     *     name="filter[id]",
     *     in="query",
     *     required=false,
     *     description="Exact match by id.",
     *     @OA\Schema(type="integer")
     *   ),
     *   @OA\Parameter(
     *     name="filter[is_active]",
     *     in="query",
     *     required=false,
     *     description="Exact match by active flag.",
     *     @OA\Schema(type="boolean")
     *   ),
     *   @OA\Parameter(
     *     name="sort",
     *     in="query",
     *     required=false,
     *     description="Sort by fields. Prefix with '-' for desc (e.g. -created_at). Allowed: uom_code,name,symbol,uom_type,created_at,updated_at",
     *     @OA\Schema(type="string", example="-created_at")
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Paginated list of UOMs",
     *     @OA\JsonContent(ref="#/components/schemas/UOMPagination")
     *   ),
     *   @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/ApiError")),
     *   @OA\Response(response=403, description="Forbidden (ADMIN only)", @OA\JsonContent(ref="#/components/schemas/ApiError")),
     *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ApiError"))
     * )
     */
    public function index(Request $request);

    /**
     * Get a single UOM by id.
     *
     * @OA\Get(
     *   path="/api/uoms/{id}",
     *   tags={"UOM"},
     *     security={{"Bearer":{}}},
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     required=true,
     *     description="UOM id",
     *     @OA\Schema(type="integer")
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="UOM found",
     *     @OA\JsonContent(ref="#/components/schemas/UOM")
     *   ),
     *   @OA\Response(response=404, description="UOM not found", @OA\JsonContent(ref="#/components/schemas/ApiError")),
     *   @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/ApiError")),
     *   @OA\Response(response=403, description="Forbidden (ADMIN only)", @OA\JsonContent(ref="#/components/schemas/ApiError"))
     * )
     */
    public function show($id);

    /**
     * Create a new UOM.
     *
     * @OA\Post(
     *   path="/api/uoms",
     *   tags={"UOM"},
     *     security={{"Bearer":{}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(ref="#/components/schemas/UOMCreateRequest")
     *   ),
     *   @OA\Response(
     *     response=201,
     *     description="UOM created",
     *     @OA\JsonContent(ref="#/components/schemas/UOM")
     *   ),
     *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError")),
     *   @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/ApiError")),
     *   @OA\Response(response=403, description="Forbidden (ADMIN only)", @OA\JsonContent(ref="#/components/schemas/ApiError")),
     *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ApiError"))
     * )
     */
    public function create(Request $request);

    /**
     * Update an existing UOM.
     *
     * @OA\Patch(
     *   path="/api/uoms/{id}",
     *   tags={"UOM"},
     *     security={{"Bearer":{}}},
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     required=true,
     *     description="UOM id",
     *     @OA\Schema(type="integer", example=1)
     *   ),
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(ref="#/components/schemas/UOMUpdateRequest")
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="UOM updated",
     *     @OA\JsonContent(ref="#/components/schemas/UOM")
     *   ),
     *   @OA\Response(response=404, description="UOM not found", @OA\JsonContent(ref="#/components/schemas/ApiError")),
     *   @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationError")),
     *   @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/ApiError")),
     *   @OA\Response(response=403, description="Forbidden (ADMIN only)", @OA\JsonContent(ref="#/components/schemas/ApiError")),
     *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ApiError"))
     * )
     */
    public function update(Request $request, $id);


    /**
     * Convert a quantity from one UOM to another.
     *
     * @OA\Post(
     *   path="/api/uoms/convert",
     *   tags={"UOM"},
     *   security={{"Bearer":{}}},
     *   summary="Convert quantity between UOMs",
     *   description="Convert a quantity from one unit of measurement to another. Both UOMs must be valid and in the same category or directly convertible.",
     *
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(ref="#/components/schemas/UOMConvertRequest")
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Conversion successful",
     *     @OA\JsonContent(ref="#/components/schemas/UOMConvertResponse")
     *   ),
     *   @OA\Response(response=422, description="Validation error or conversion not possible", @OA\JsonContent(ref="#/components/schemas/ValidationError")),
     *   @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/ApiError")),
     *   @OA\Response(response=403, description="Forbidden (ADMIN only)", @OA\JsonContent(ref="#/components/schemas/ApiError")),
     *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ApiError"))
     * )
     */
    public function convert(Request $request);

    /**
     * Get trashed (soft-deleted) UOMs.
     *
     * @OA\Get(
     *   path="/api/uoms/trashed",
     *   tags={"UOM"},
     *   security={{"Bearer":{}}},
     *   summary="Get trashed UOMs",
     *   description="Retrieve a paginated list of soft-deleted UOMs. Useful for restore/review workflows.",
     *
     *   @OA\Parameter(
     *     name="per_page",
     *     in="query",
     *     required=false,
     *     description="Items per page (1..100). Default 10.",
     *     @OA\Schema(type="integer", example=10)
     *   ),
     *   @OA\Parameter(
     *     name="sort",
     *     in="query",
     *     required=false,
     *     description="Sort by fields. Prefix with '-' for desc.",
     *     @OA\Schema(type="string", example="-deleted_at")
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Paginated list of trashed UOMs",
     *     @OA\JsonContent(ref="#/components/schemas/UOMPagination")
     *   ),
     *   @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/ApiError")),
     *   @OA\Response(response=403, description="Forbidden (ADMIN only)", @OA\JsonContent(ref="#/components/schemas/ApiError")),
     *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ApiError"))
     * )
     */
    public function trashed(Request $request);

    /**
     * Restore a soft-deleted UOM.
     *
     * @OA\Patch(
     *   path="/api/uoms/{id}/restore",
     *   tags={"UOM"},
     *   security={{"Bearer":{}}},
     *   summary="Restore soft-deleted UOM",
     *   description="Restore a soft-deleted UOM back to active state.",
     *
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     required=true,
     *     description="UOM id",
     *     @OA\Schema(type="integer", example=1)
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="UOM restored",
     *     @OA\JsonContent(ref="#/components/schemas/UOM")
     *   ),
     *   @OA\Response(response=404, description="UOM not found or not deleted", @OA\JsonContent(ref="#/components/schemas/ApiError")),
     *   @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/ApiError")),
     *   @OA\Response(response=403, description="Forbidden (ADMIN only)", @OA\JsonContent(ref="#/components/schemas/ApiError")),
     *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ApiError"))
     * )
     */
    public function restore($id);

    /**
     * Delete a UOM by id.
     *
     * @OA\Delete(
     *   path="/api/uoms/{id}",
     *   tags={"UOM"},
     *   security={{"Bearer":{}}},
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     required=true,
     *     description="UOM id",
     *     @OA\Schema(type="integer")
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="UOM deleted",
     *     @OA\JsonContent(
     *       @OA\Property(property="message", type="string", example="UOM deleted successfully")
     *     )
     *   ),
     *   @OA\Response(response=404, description="UOM not found", @OA\JsonContent(ref="#/components/schemas/ApiError")),
     *   @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/ApiError")),
     *   @OA\Response(response=403, description="Forbidden (ADMIN only)", @OA\JsonContent(ref="#/components/schemas/ApiError")),
     *   @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ApiError"))
     * )
     */
    public function delete($id);
}
