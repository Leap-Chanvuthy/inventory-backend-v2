<?php

namespace App\Http\Controllers\API\Interfaces;

use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Customer Categories",
 *     description="API Endpoints for managing customer categories (Only Accessible for: VENDER Users)"
 * )
 */


/**
 * @OA\Schema(
 *   schema="CustomerCategory",
 *   type="object",
 *   title="Customer Category",
 *   description="Customer category model",
 *   @OA\Property(property="id", type="integer", example=1),
 *   @OA\Property(property="category_name", type="string", example="VIP"),
 *   @OA\Property(property="label_color", type="string", nullable=true, example="#5c52d6"),
 *   @OA\Property(property="description", type="string", nullable=true, example="High value customers"),
 *   @OA\Property(property="created_at", type="string", format="date-time", example="2025-01-01T12:00:00Z"),
 *   @OA\Property(property="updated_at", type="string", format="date-time", example="2025-01-02T12:00:00Z")
 * )
 */

interface CustomerCategoryAPIControllerInterface
{
    /**
     * @OA\Get(
     *     path="/api/customer-categories",
     *     tags={"Customer Categories"},
     *     security={{"Bearer":{}}},
     *     summary="Get all customer categories",
     *     description="Retrieve a paginated list of customer categories.",
     *   @OA\Parameter(
     *     name="per_page",
     *     in="query",
     *     description="Items per page (1..100)",
     *     required=false,
     *     @OA\Schema(type="integer", default=10, minimum=1, maximum=100)
     *   ),
     *   @OA\Parameter(
     *     name="filter[search]",
     *     in="query",
     *     description="Search by category_name or description",
     *     required=false,
     *     @OA\Schema(type="string")
     *   ),
     *   @OA\Parameter(
     *     name="filter[id]",
     *     in="query",
     *     description="Exact match by id",
     *     required=false,
     *     @OA\Schema(type="integer")
     *   ),
     *   @OA\Parameter(
     *     name="sort",
     *     in="query",
     *     description="Sort fields: id, category_name, created_at, updated_at. Prefix with '-' for desc (e.g. -created_at).",
     *     required=false,
     *     @OA\Schema(type="string")
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Customer categories retrieved successfully",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Customer categories retrieved successfully"),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         description="Laravel paginator payload",
     *         @OA\Property(
     *           property="data",
     *           type="array",
     *           @OA\Items(ref="#/components/schemas/CustomerCategory")
     *         )
     *       )
     *     )
     *   ),
     *   @OA\Response(response=401, description="Unauthenticated"),
     *   @OA\Response(response=403, description="Forbidden (ADMIN only)"),
     *   @OA\Response(response=500, description="Server error")
     * )
     */
    public function index(Request $request);

    /**
     * @OA\Get(
     *   path="/api/customer-categories/{id}",
     *   operationId="CustomerCategoriesShow",
     *   tags={"Customer Categories"},
     *   summary="Get a customer category by id",
     *   description="Requires auth and ADMIN role.",
     *   security={{"Bearer":{}}},
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     required=true,
     *     @OA\Schema(type="integer")
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Customer category retrieved successfully",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Customer category retrieved successfully"),
     *       @OA\Property(property="data", ref="#/components/schemas/CustomerCategory")
     *     )
     *   ),
     *   @OA\Response(response=401, description="Unauthenticated"),
     *   @OA\Response(response=403, description="Forbidden (ADMIN only)"),
     *   @OA\Response(response=404, description="Customer category not found"),
     *   @OA\Response(response=500, description="Server error")
     * )
     */
    public function show($id);

    /**
     * @OA\Post(
     *   path="/api/customer-categories",
     *   operationId="CustomerCategoriesStore",
     *   tags={"Customer Categories"},
     *   summary="Create a customer category",
     *   description="Requires auth and ADMIN role.",
     *   security={{"Bearer":{}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       required={"category_name"},
     *       @OA\Property(property="category_name", type="string", maxLength=255, example="VIP"),
     *       @OA\Property(property="label_color", type="string", nullable=true, example="#5c52d6"),
     *       @OA\Property(property="description", type="string", nullable=true, example="High value customers")
     *     )
     *   ),
     *   @OA\Response(
     *     response=201,
     *     description="Customer category created successfully",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Customer category created successfully"),
     *       @OA\Property(property="data", ref="#/components/schemas/CustomerCategory")
     *     )
     *   ),
     *   @OA\Response(response=401, description="Unauthenticated"),
     *   @OA\Response(response=403, description="Forbidden (ADMIN only)"),
     *   @OA\Response(response=422, description="Validation error"),
     *   @OA\Response(response=500, description="Server error")
     * )
     */
    public function store(Request $request);

    /**
     * @OA\Patch(
     *   path="/api/customer-categories/{id}",
     *   operationId="CustomerCategoriesUpdate",
     *   tags={"Customer Categories"},
     *   summary="Update a customer category",
     *   description="Requires auth and ADMIN role.",
     *   security={{"Bearer":{}}},
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     required=true,
     *     @OA\Schema(type="integer")
     *   ),
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *       @OA\Property(property="category_name", type="string", maxLength=255, example="Retail"),
     *       @OA\Property(property="label_color", type="string", nullable=true, example="#22c55e"),
     *       @OA\Property(property="description", type="string", nullable=true, example="Walk-in / retail customers")
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Customer category updated successfully",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Product category updated successfully"),
     *       @OA\Property(property="data", ref="#/components/schemas/CustomerCategory")
     *     )
     *   ),
     *   @OA\Response(response=401, description="Unauthenticated"),
     *   @OA\Response(response=403, description="Forbidden (ADMIN only)"),
     *   @OA\Response(response=404, description="Customer category not found"),
     *   @OA\Response(response=422, description="Validation error"),
     *   @OA\Response(response=500, description="Server error")
     * )
     */
    public function update(Request $request, $id);

    /**
     * @OA\Delete(
     *   path="/api/customer-categories/{id}",
     *   operationId="CustomerCategoriesDelete",
     *   tags={"Customer Categories"},
     *   summary="Delete a customer category",
     *   description="Requires auth and ADMIN role.",
     *   security={{"Bearer":{}}},
     *   @OA\Parameter(
     *     name="id",
     *     in="path",
     *     required=true,
     *     @OA\Schema(type="integer")
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Customer category deleted successfully",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Customer category deleted successfully")
     *     )
     *   ),
     *   @OA\Response(response=401, description="Unauthenticated"),
     *   @OA\Response(response=403, description="Forbidden (ADMIN only)"),
     *   @OA\Response(response=404, description="Customer category not found"),
     *   @OA\Response(response=500, description="Server error")
     * )
     */
    public function delete($id);

}

/**
 * @OA\Schema(
 *   schema="CustomerCategory",
 *   type="object",
 *   @OA\Property(property="id", type="integer", example=1),
 *   @OA\Property(property="category_name", type="string", example="VIP"),
 *   @OA\Property(property="label_color", type="string", nullable=true, example="#5c52d6"),
 *   @OA\Property(property="description", type="string", nullable=true, example="High value customers"),
 *   @OA\Property(property="created_at", type="string", format="date-time", example="2025-01-01T12:00:00Z"),
 *   @OA\Property(property="updated_at", type="string", format="date-time", example="2025-01-02T12:00:00Z")
 * )
 */