<?php

namespace App\Http\Controllers\API\Interfaces;

/**
 * @OA\Tag(
 *     name="Raw Material Categories",
 *     description="API Endpoints for managing raw material categories. (Accessible for: ADMIN and STOCK_CONTROLLER Users)"
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
    *     description="Retrieve a paginated list of raw material categories with optional soft-delete filter and linked raw material count.",
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
    *         description="Search raw material categories by category name",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
    *     @OA\Parameter(
    *         name="filter[is_deleted]",
    *         in="query",
    *         description="Filter by soft-delete state. true/1 returns deleted categories, false/0 returns active categories.",
    *         required=false,
    *         @OA\Schema(type="boolean", example=false)
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
    *             @OA\Property(
    *                 property="data",
    *                 type="object",
    *                 @OA\Property(property="current_page", type="integer", example=1),
    *                 @OA\Property(
    *                     property="data",
    *                     type="array",
    *                     @OA\Items(
    *                         type="object",
    *                         @OA\Property(property="id", type="integer", example=1),
    *                         @OA\Property(property="category_name", type="string", example="Metals"),
    *                         @OA\Property(property="label_color", type="string", example="#4d0507"),
    *                         @OA\Property(property="description", type="string", example="Raw materials category"),
    *                         @OA\Property(property="raw_materials_count", type="integer", example=14, description="Total raw materials linked to this category"),
    *                         @OA\Property(property="deleted_at", type="string", nullable=true, format="date-time", example=null),
    *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-03-08T08:09:21.000000Z"),
    *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2026-03-08T08:09:21.000000Z")
    *                     )
    *                 ),
    *                 @OA\Property(property="first_page_url", type="string", example="http://127.0.0.1:8000/api/raw-material-categories?page=1"),
    *                 @OA\Property(property="from", type="integer", example=1),
    *                 @OA\Property(property="last_page", type="integer", example=2),
    *                 @OA\Property(property="last_page_url", type="string", example="http://127.0.0.1:8000/api/raw-material-categories?page=2"),
    *                 @OA\Property(property="next_page_url", type="string", nullable=true, example="http://127.0.0.1:8000/api/raw-material-categories?page=2"),
    *                 @OA\Property(property="path", type="string", example="http://127.0.0.1:8000/api/raw-material-categories"),
    *                 @OA\Property(property="per_page", type="integer", example=10),
    *                 @OA\Property(property="prev_page_url", type="string", nullable=true, example=null),
    *                 @OA\Property(property="to", type="integer", example=10),
    *                 @OA\Property(property="total", type="integer", example=20)
    *             )
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
    *                 @OA\Property(property="deleted_at", type="string", nullable=true, format="date-time", example=null),
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

    /**
     * @OA\Patch(
     *     path="/api/raw-material-categories/{id}/restore",
     *     tags={"Raw Material Categories"},
     *     security={{"Bearer":{}}},
     *     summary="Restore a soft-deleted raw material category",
     *     description="Restore a raw material category that was previously soft-deleted.",
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
     *         description="Category restored successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Raw material category restored successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="category_name", type="string", example="Metals"),
     *                 @OA\Property(property="label_color", type="string", example="#4d0507"),
     *                 @OA\Property(property="description", type="string", example="Raw materials category"),
     *                 @OA\Property(property="deleted_at", type="string", nullable=true, format="date-time", example=null),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-03-08T08:09:21.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-03-08T08:09:21.000000Z")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Category is already active"
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Category not found"
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Failed restoring category"
     *     )
     * )
     */

    public function restore($id);
}
