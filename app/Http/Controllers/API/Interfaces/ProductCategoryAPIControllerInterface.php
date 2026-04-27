<?php

namespace App\Http\Controllers\API\Interfaces;

/**
 * @OA\Tag(
 *     name="Product Categories",
 *     description="API Endpoints for managing product categories. (Accessible for: ADMIN and STOCK_CONTROLLER Users)"
 * )
 */
interface ProductCategoryAPIControllerInterface
{
    /**
     * @OA\Get(
     *     path="/api/product-categories",
     *     tags={"Product Categories"},
     *     security={{"Bearer":{}}},
     *     summary="Get all product categories",
     *     description="Retrieve a paginated list of product categories.",
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Number of categories per page",
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[search]",
     *         in="query",
     *         description="Search product categories by category name",
     *         required=false,
     *         @OA\Schema(type="string", example="")
     *     ),
     *
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="Sort product categories by a field (created_at, updated_at, category_name)",
     *         required=false,
     *         @OA\Schema(type="string", example="-created_at")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Product categories retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product categories retrieved successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Failed fetching product categories"
     *     )
     * )
     */
    public function index();

        /**
     * @OA\Get(
     *     path="/api/product-categories/trashed",
     *     tags={"Product Categories"},
     *     security={{"Bearer":{}}},
     *     summary="Get all trashed product categories",
     *     description="Retrieve a paginated list of trashed product categories.",
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Number of categories per page",
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[search]",
     *         in="query",
     *         description="Search product categories by category name",
     *         required=false,
     *         @OA\Schema(type="string", example="")
     *     ),
     *
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="Sort product categories by a field (created_at, updated_at, category_name)",
     *         required=false,
     *         @OA\Schema(type="string", example="-created_at")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Trashed product categories retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Trashed product categories retrieved successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Failed fetching trashed product categories"
     *     )
     * )
     */
    public function trashed();


    /**
     * @OA\Get(
     *     path="/api/product-categories/{id}",
     *     tags={"Product Categories"},
     *     security={{"Bearer":{}}},
     *     summary="Get a product category by ID",
     *     description="Retrieve details of a specific product category.",
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Product Category ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Product category retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product category retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="category_name", type="string", example="Electronics"),
     *                 @OA\Property(property="label_color", type="string", example="#FF5733"),
     *                 @OA\Property(property="description", type="string", example="Electronic products category"),
     *                 @OA\Property(property="created_at", type="string", example="2025-01-01 09:00:00"),
     *                 @OA\Property(property="updated_at", type="string", example="2025-01-05 10:00:00")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Product category not found"
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Failed fetching product category"
     *     )
     * )
     */
    public function show($id);


    /**
     * @OA\Post(
     *     path="/api/product-categories",
     *     tags={"Product Categories"},
     *     security={{"Bearer":{}}},
     *     summary="Create a new product category",
     *     description="Create a new product category with name, label color, and optional description.",
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"category_name"},
     *                 @OA\Property(property="category_name", type="string", example="Furniture"),
     *                 @OA\Property(property="label_color", type="string", example="#00AAFF"),
     *                 @OA\Property(property="description", type="string", example="Furniture products category")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Product category created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product category created successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Failed creating product category"
     *     )
     * )
     */
    public function store();


    /**
     * @OA\Patch(
     *     path="/api/product-categories/{id}",
     *     tags={"Product Categories"},
     *     security={{"Bearer":{}}},
     *     summary="Update an existing product category",
     *     description="Update product category details. Only provided fields will be updated.",
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Product Category ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(property="category_name", type="string", example="Updated Category Name"),
     *                 @OA\Property(property="label_color", type="string", example="#123456"),
     *                 @OA\Property(property="description", type="string", example="Updated description")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Product category updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product category updated successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Product category not found"
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Failed updating product category"
     *     )
     * )
     */
    public function update($id);




    /**
     * @OA\Delete(
     *     path="/api/product-categories/{id}",
     *     tags={"Product Categories"},
     *     security={{"Bearer":{}}},
     *     summary="Delete a product category",
     *     description="Delete a specific product category by its ID.",
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Product Category ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Product category deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product category deleted successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Product category not found"
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Failed deleting product category"
     *     )
     * )
     */
    public function delete($id);

    /**
     * @OA\Patch(
     *     path="/api/product-categories/{id}/restore",
     *     tags={"Product Categories"},
     *     security={{"Bearer":{}}},
     *     summary="Restore a deleted product category",
     *     description="Restore a specific deleted product category by its ID.",
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Product Category ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Product category restored successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product category restored successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Product category not found"
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Failed restoring product category"
     *     )
     * )
     */
    public function restore($id);

}
