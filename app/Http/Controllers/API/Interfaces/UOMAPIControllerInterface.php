<?php

namespace App\Http\Controllers\API\Interfaces;

use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *   name="UOM",
 *   description="Unit of Measurement management"
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
}
