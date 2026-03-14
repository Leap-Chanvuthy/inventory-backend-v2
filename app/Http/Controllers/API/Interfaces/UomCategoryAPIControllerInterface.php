<?php

namespace App\Http\Controllers\API\Interfaces;

use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="UOM Categories",
 *     description="API Endpoints for managing UOM Categories and their hierarchies. (Only Accessible for: ADMIN & STOCK_CONTROLLER Users)"
 * )
 *
 * @OA\Schema(
 *     schema="UomCategoryBase",
 *     type="object",
 *     required={"id", "name", "created_at", "updated_at"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Weight"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Weight measurement units"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-22T10:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-22T10:00:00Z")
 * )
 *
 * @OA\Schema(
 *     schema="UomUnit",
 *     type="object",
 *     required={"id", "name"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="category_id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Kilogram"),
 *     @OA\Property(property="symbol", type="string", nullable=true, example="kg"),
 *     @OA\Property(property="is_base_unit", type="boolean", example=true),
 *     @OA\Property(property="conversion_factor", type="number", example=1, description="Multiplier relative to base unit (base=1)"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-22T10:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-22T10:00:00Z"),
 *     @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true, example=null, description="Soft-delete timestamp")
 * )
 *
 * @OA\Schema(
 *     schema="UomCategory",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/UomCategoryBase"),
 *         @OA\Schema(
 *             type="object",
 *             @OA\Property(
 *                 property="base_unit",
 *                 ref="#/components/schemas/UomUnit",
 *                 nullable=true,
 *                 description="The base unit of this category (typically the reference unit)"
 *             ),
 *             @OA\Property(
 *                 property="units",
 *                 type="array",
 *                 @OA\Items(ref="#/components/schemas/UomUnit"),
 *                 description="All UOM units in this category (including base and children)"
 *             ),
 *             @OA\Property(
 *                 property="units_count",
 *                 type="integer",
 *                 example=3,
 *                 description="Total count of units in this category"
 *             ),
 *             @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true, example=null, description="Soft-delete timestamp")
 *         )
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="UomCategoryCreateRequest",
 *     type="object",
 *     required={"name"},
 *     @OA\Property(property="name", type="string", example="Length"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Length measurement units")
 * )
 *
 * @OA\Schema(
 *     schema="UomCategoryUpdateRequest",
 *     type="object",
 *     @OA\Property(property="name", type="string", example="Mass"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Mass and weight measurements")
 * )
 *
 * @OA\Schema(
 *     schema="UomCategoryPagination",
 *     type="object",
 *     @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/UomCategory")),
 *     @OA\Property(property="links", ref="#/components/schemas/PaginationLinks"),
 *     @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
 * )
 *
 * @OA\Schema(
 *     schema="ApiResponse",
 *     type="object",
 *     @OA\Property(property="status", type="boolean", example=true),
 *     @OA\Property(property="message", type="string", example="Operation successful"),
 *     @OA\Property(property="data", type="object", nullable=true),
 *     @OA\Property(property="errors", type="object", nullable=true)
 * )
 *
 */
interface UomCategoryAPIControllerInterface
{
    /**
     * Get all UOM categories with pagination, filters, and sorting.
     *
     * @OA\Get(
     *     path="/api/uom-categories",
     *     tags={"UOM Categories"},
     *     security={{"Bearer":{}}},
     *     summary="Get UOM categories (paginated)",
     *     description="Retrieve a paginated list of UOM categories. Supports search, filtering, and sorting.",
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number (default 1)",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Records per page (default 10, max 100)",
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Parameter(
     *         name="filter[search]",
     *         in="query",
     *         description="Search by category name",
     *         @OA\Schema(type="string", example="Weight")
     *     ),
     *     @OA\Parameter(
     *         name="filter[id]",
     *         in="query",
     *         description="Filter by category ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="Sort by field (e.g., name, -created_at). Prefix with '-' for descending.",
     *         @OA\Schema(type="string", example="-created_at")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="UOM categories retrieved successfully",
     *         @OA\JsonContent(ref="#/components/schemas/UomCategoryPagination")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/ApiError")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(ref="#/components/schemas/ApiError")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiError")
     *     )
     * )
     */
    public function index(Request $request);

    /**
     * Get a single UOM category by ID with all its units.
     *
     * @OA\Get(
     *     path="/api/uom-categories/{id}",
     *     tags={"UOM Categories"},
     *     security={{"Bearer":{}}},
     *     summary="Get single UOM category",
     *     description="Retrieve a single UOM category with all its units (base unit and children).",
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="UOM Category ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="UOM category retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="UOM Category retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/UomCategory")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="UOM Category not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiError")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/ApiError")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(ref="#/components/schemas/ApiError")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiError")
     *     )
     * )
     */
    public function show(int $id);

    /**
     * Create a new UOM category.
     *
     * @OA\Post(
     *     path="/api/uom-categories",
     *     tags={"UOM Categories"},
     *     security={{"Bearer":{}}},
     *     summary="Create new UOM category",
     *     description="Create a new UOM category. The category must have a unique name.",
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/UomCategoryCreateRequest")
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="UOM category created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="UOM Category created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/UomCategory")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationError")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/ApiError")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(ref="#/components/schemas/ApiError")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiError")
     *     )
     * )
     */
    public function store(Request $request);

    /**
     * Update an existing UOM category.
     *
     * @OA\Patch(
     *     path="/api/uom-categories/{id}",
     *     tags={"UOM Categories"},
     *     security={{"Bearer":{}}},
     *     summary="Update UOM category",
     *     description="Update an existing UOM category. The updated name must be unique.",
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="UOM Category ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/UomCategoryUpdateRequest")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="UOM category updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="UOM Category updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/UomCategory")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="UOM Category not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiError")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationError")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/ApiError")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(ref="#/components/schemas/ApiError")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiError")
     *     )
     * )
     */
    public function update(Request $request, int $id);

    /**
     * Delete a UOM category (soft delete).
     *
     * @OA\Delete(
     *     path="/api/uom-categories/{id}",
     *     tags={"UOM Categories"},
     *     security={{"Bearer":{}}},
     *     summary="Delete UOM category",
     *     description="Soft-delete a UOM category. The category and its units remain in the database but are marked as deleted.",
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="UOM Category ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="UOM category deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="UOM Category deleted successfully"),
     *             @OA\Property(property="data", type="object", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="UOM Category not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiError")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/ApiError")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(ref="#/components/schemas/ApiError")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiError")
     *     )
     * )
     */
    public function delete(int $id);

    /**
     * Get all soft-deleted UOM categories.
     *
     * @OA\Get(
     *     path="/api/uom-categories/trashed",
     *     tags={"UOM Categories"},
     *     security={{"Bearer":{}}},
     *     summary="Get trashed UOM categories",
     *     description="Retrieve a paginated list of soft-deleted UOM categories. Useful for restore/review workflows.",
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number (default 1)",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Records per page (default 10, max 100)",
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="Sort by field (e.g., -deleted_at, name)",
     *         @OA\Schema(type="string", example="-deleted_at")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Trashed UOM categories retrieved successfully",
     *         @OA\JsonContent(ref="#/components/schemas/UomCategoryPagination")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/ApiError")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(ref="#/components/schemas/ApiError")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiError")
     *     )
     * )
     */
    public function trashed(Request $request);

    /**
     * Restore a soft-deleted UOM category.
     *
     * @OA\Patch(
     *     path="/api/uom-categories/{id}/restore",
     *     tags={"UOM Categories"},
     *     security={{"Bearer":{}}},
     *     summary="Restore soft-deleted UOM category",
     *     description="Restore a soft-deleted UOM category and its units back to active state.",
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="UOM Category ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="UOM category restored successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="UOM Category restored successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/UomCategory")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="UOM Category not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiError")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/ApiError")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(ref="#/components/schemas/ApiError")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiError")
     *     )
     * )
     */
    public function restore(int $id);
}
