<?php

namespace App\Http\Controllers\API\Interfaces;

use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Customer Categories",
 *     description="CRUD APIs for customer categories, including category-level default discount percentage."
 * )
 */

/**
 * @OA\Schema(
 *   schema="CustomerCategory",
 *   type="object",
 *   title="Customer Category",
 *   description="Customer category entity with default discount policy.",
 *   @OA\Property(property="id", type="integer", example=1),
 *   @OA\Property(property="category_name", type="string", example="VIP"),
 *   @OA\Property(property="label_color", type="string", nullable=true, example="#5c52d6"),
 *   @OA\Property(property="description", type="string", nullable=true, example="High value customers"),
 *   @OA\Property(property="discount_percentage", type="number", format="float", example=10.50, minimum=0, maximum=100, description="Default discount percentage automatically applied in customer pricing flows."),
 *   @OA\Property(property="created_at", type="string", format="date-time", example="2025-01-01T12:00:00Z"),
 *   @OA\Property(property="updated_at", type="string", format="date-time", example="2025-01-02T12:00:00Z")
 * )
 */
interface CustomerCategoryAPIControllerInterface
{
    /**
     * @OA\Get(
     *     path="/api/customer-categories",
     *     operationId="CustomerCategoriesIndex",
     *     tags={"Customer Categories"},
     *     security={{"Bearer":{}}},
     *     summary="List customer categories",
     *     description="Returns a paginated list of customer categories. Useful for frontend dropdowns and discount setup pages.",
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
     *     description="Sort fields: id, category_name, discount_percentage, created_at, updated_at. Prefix with '-' for descending.",
     *     required=false,
     *     @OA\Schema(type="string")
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Customer categories retrieved successfully",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="status", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Customer categories retrieved successfully"),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         description="Laravel paginator payload",
     *         @OA\Property(
     *           property="data",
     *           type="array",
     *           @OA\Items(ref="#/components/schemas/CustomerCategory")
     *         ),
     *         @OA\Property(property="current_page", type="integer", example=1),
     *         @OA\Property(property="per_page", type="integer", example=10),
     *         @OA\Property(property="total", type="integer", example=34)
     *       )
     *     )
     *   ),
     *   @OA\Response(response=401, description="Unauthenticated"),
     *   @OA\Response(response=403, description="Forbidden"),
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
     *   description="Returns one category including discount percentage.",
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
    *       @OA\Property(property="status", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Customer category retrieved successfully"),
     *       @OA\Property(property="data", ref="#/components/schemas/CustomerCategory")
     *     )
     *   ),
     *   @OA\Response(response=401, description="Unauthenticated"),
    *   @OA\Response(response=403, description="Forbidden"),
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
    *   description="Creates a category with optional color/description and required discount percentage range 0..100.",
     *   security={{"Bearer":{}}},
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
    *       required={"category_name","discount_percentage"},
     *       @OA\Property(property="category_name", type="string", maxLength=255, example="VIP"),
     *       @OA\Property(property="label_color", type="string", nullable=true, example="#5c52d6"),
    *       @OA\Property(property="description", type="string", nullable=true, example="High value customers"),
    *       @OA\Property(property="discount_percentage", type="number", format="float", minimum=0, maximum=100, example=10.50)
     *     )
     *   ),
     *   @OA\Response(
     *     response=201,
     *     description="Customer category created successfully",
     *     @OA\JsonContent(
     *       type="object",
    *       @OA\Property(property="status", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Customer category created successfully"),
     *       @OA\Property(property="data", ref="#/components/schemas/CustomerCategory")
     *     )
     *   ),
     *   @OA\Response(response=401, description="Unauthenticated"),
    *   @OA\Response(response=403, description="Forbidden"),
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
    *   description="Updates any category field. discount_percentage must remain within 0..100 when provided.",
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
    *       @OA\Property(property="description", type="string", nullable=true, example="Walk-in / retail customers"),
    *       @OA\Property(property="discount_percentage", type="number", format="float", minimum=0, maximum=100, example=5.00)
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Customer category updated successfully",
     *     @OA\JsonContent(
     *       type="object",
    *       @OA\Property(property="status", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Product category updated successfully"),
     *       @OA\Property(property="data", ref="#/components/schemas/CustomerCategory")
     *     )
     *   ),
     *   @OA\Response(response=401, description="Unauthenticated"),
    *   @OA\Response(response=403, description="Forbidden"),
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
    *   description="Soft-deletes a customer category.",
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
     *       @OA\Property(property="status", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Customer category deleted successfully")
     *     )
     *   ),
     *   @OA\Response(response=401, description="Unauthenticated"),
     *   @OA\Response(response=403, description="Forbidden"),
     *   @OA\Response(response=404, description="Customer category not found"),
     *   @OA\Response(response=500, description="Server error")
     * )
     */
    public function delete($id);

}