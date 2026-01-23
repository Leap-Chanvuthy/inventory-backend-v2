<?php

namespace App\Http\Controllers\API\Interfaces;

/**
 * @OA\Tag(
 *     name="Raw Material Categories",
 *     description="API Endpoints for managing raw material categories"
 * )
 */
interface RawMaterialCategoryAPIControllerInterface
{
    /**
     * @OA\Get(
     *     path="/api/raw-material-categories",
     *     tags={"Raw Material Categories"},
     *     security={{"Bearer":{}}},
     *     summary="Get all raw material categories",
     *     description="Retrieve a paginated list of raw material categories.",
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
     *         description="Search raw material categories by category name and description",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="Sort raw material categories by a specific field (e.g., created_at, updated_at, category_name)",
     *         required=false,
     *         @OA\Schema(type="string", example="-created_at")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Raw material categories retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Raw material categories retrieved successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Failed fetching categories"
     *     )
     * )
     */
    public function index();


    /**
     * @OA\Get(
     *     path="/api/raw-material-categories/{id}",
     *     tags={"Raw Material Categories"},
     *     security={{"Bearer":{}}},
     *     summary="Get a raw material category by ID",
     *     description="Retrieve details of a specific raw material category.",
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Category ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Category retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Raw material category retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="category_name", type="string", example="Metal"),
     *                 @OA\Property(property="label_color", type="string", example="#FF0000"),
     *                 @OA\Property(property="description", type="string", example="Raw materials for metal production"),
     *                 @OA\Property(property="created_at", type="string", example="2025-01-01 09:00:00"),
     *                 @OA\Property(property="updated_at", type="string", example="2025-01-05 10:00:00")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Category not found"
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Failed fetching category"
     *     )
     * )
     */
    public function show($id);


    /**
     * @OA\Post(
     *     path="/api/raw-material-categories",
     *     tags={"Raw Material Categories"},
     *     security={{"Bearer":{}}},
     *     summary="Create a new raw material category",
     *     description="Create a new category with name, label color, and optional description.",
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"category_name"},
     *                 @OA\Property(property="category_name", type="string", example="Plastic"),
     *                 @OA\Property(property="label_color", type="string", example="#00FF00"),
     *                 @OA\Property(property="description", type="string", example="Plastic raw materials")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Category created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Raw material category created successfully"),
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
     *         description="Failed creating category"
     *     )
     * )
     */
    public function store();


    /**
     * @OA\Patch(
     *     path="/api/raw-material-categories/{id}",
     *     tags={"Raw Material Categories"},
     *     security={{"Bearer":{}}},
     *     summary="Update an existing raw material category",
     *     description="Update category details. Only provided fields will be updated.",
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Category ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(property="category_name", type="string", example="Updated Name"),
     *                 @OA\Property(property="label_color", type="string", example="#0000FF"),
     *                 @OA\Property(property="description", type="string", example="Updated description")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Category updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Raw material category updated successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Category not found"
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Failed updating category"
     *     )
     * )
     */
    public function update($id);


    /**
     * @OA\Delete(
     *     path="/api/raw-material-categories/{id}",
     *     tags={"Raw Material Categories"},
     *     security={{"Bearer":{}}},
     *     summary="Delete a raw material category",
     *     description="Delete a specific raw material category by its ID.",
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Category ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Category deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Raw material category deleted successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Category not found"
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Failed deleting category"
     *     )
     * )
     */
    public function delete($id);
}
