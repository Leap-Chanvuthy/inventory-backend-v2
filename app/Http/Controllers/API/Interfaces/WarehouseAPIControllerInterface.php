<?php

namespace App\Http\Controllers\API\Interfaces;

/**
 * @OA\Tag(
 *     name="Warehouses",
 *     description="API Endpoints for managing warehouses"
 * )
 */
interface WarehouseAPIControllerInterface
{

    /**
     * @OA\Get(
     *     path="/api/warehouses",
     *     tags={"Warehouses"},
     *     security={{"Bearer":{}}},
     *     summary="Get all warehouses",
     *     description="Retrieve a paginated list of warehouses.",
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Number of warehouses per page",
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Parameter(
     *         name="filter[search]",
     *         in="query",
     *         description="Search warehouse by warehouse name and warehouse manager",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="Sort warehouses by a specific field (e.g., created_at, updated_at, warehouse_manager)",
     *         required=false,
     *         @OA\Schema(type="string", example="-created_at")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Warehouses retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Warehouses retrieved successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error querying warehouses"
     *     )
     * )
     */
    public function index();


    /**
     * @OA\Get(
     *     path="/api/warehouses/{id}",
     *     tags={"Warehouses"},
     *     security={{"Bearer":{}}},
     *     summary="Get warehouse by ID",
     *     description="Retrieve details of a specific warehouse.",
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Warehouse ID",
     *         @OA\Schema(type="integer", example=5)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Warehouse retrieved successfully",
     *         @OA\JsonContent(
     *              @OA\Property(property="status", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Warehouse retrieved successfully"),
     *              @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Warehouse not found"
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Failed getting warehouse"
     *     )
     * )
     */
    public function show($id);


    /**
     * @OA\Post(
     *     path="/api/warehouses",
     *     tags={"Warehouses"},
     *     security={{"Bearer":{}}},
     *     summary="Create a new warehouse",
     *     description="Store a new warehouse with optional images.",
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"warehouse_name", "warehouse_address"},
     *
     *                 @OA\Property(property="warehouse_name", type="string", example="Main Warehouse"),
     *                 @OA\Property(property="warehouse_manager", type="string", example="John Doe"),
     *                 @OA\Property(property="warehouse_manager_contact", type="string", example="0123456789"),
     *                 @OA\Property(property="warehouse_manager_email", type="string", example="manager@example.com"),
     *                 @OA\Property(property="warehouse_address", type="string", example="Phnom Penh"),
     *                 @OA\Property(property="latitude", type="string", example="11.562108"),
     *                 @OA\Property(property="longitude", type="string", example="104.888535"),
     *                 @OA\Property(property="warehouse_description", type="string", example="Large warehouse for packaging"),
     *
     *                 @OA\Property(
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
     *         description="Warehouse created successfully"
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed"
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Failed creating warehouse"
     *     )
     * )
     */
    public function store();


    /**
     * @OA\Post(
     *     path="/api/warehouses/{id}",
     *     tags={"Warehouses"},
     *     security={{"Bearer":{}}},
     *     summary="Update a warehouse",
     *     description="Update warehouse information and optionally append more images.",
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Warehouse ID",
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"_method","warehouse_name","warehouse_manager_contact","warehouse_manager_email","warehouse_manager","warehouse_address"},
     *
     *                 @OA\Property(
     *                     property="_method",
     *                     type="string",
     *                     example="PATCH",
     *                     description="Set this to PATCH to emulate PATCH request using POST"
     *                 ),
     *
     *                 @OA\Property(property="warehouse_name", type="string", example="Updated Warehouse"),
     *                 @OA\Property(property="warehouse_manager", type="string", example="Jane Doe"),
     *                 @OA\Property(property="warehouse_manager_contact", type="string", example="099999999"),
     *                 @OA\Property(property="warehouse_manager_email", type="string", example="manager@example.com"),
     *                 @OA\Property(property="warehouse_address", type="string", example="Siem Reap"),
     *                 @OA\Property(property="latitude", type="string", example="13.361201"),
     *                 @OA\Property(property="longitude", type="string", example="103.859"),
     *                 @OA\Property(property="warehouse_description", type="string", example="Updated description"),
     *
     *                 @OA\Property(
     *                     property="images[]",
     *                     type="array",
     *                     @OA\Items(type="string", format="binary")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Warehouse updated successfully"
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Warehouse not found"
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Failed updating warehouse"
     *     )
     * )
     */
    public function update($id);


    /**
     * @OA\Delete(
     *     path="/api/warehouses/{warehouseId}/images/{imageId}",
     *     tags={"Warehouses"},
     *     security={{"Bearer":{}}},
     *     summary="Delete a specific warehouse image",
     *     description="Remove a warehouse image from storage and database.",
     *
     *     @OA\Parameter(
     *         name="warehouseId",
     *         in="path",
     *         required=true,
     *         description="Warehouse ID",
     *         @OA\Schema(type="integer", example=8)
     *     ),
     *
     *     @OA\Parameter(
     *         name="imageId",
     *         in="path",
     *         required=true,
     *         description="Image ID",
     *         @OA\Schema(type="integer", example=25)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Warehouse image deleted successfully"
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Warehouse or image not found"
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Failed deleting warehouse image"
     *     )
     * )
     */
    public function deleteWarehouseImage($warehouseId, $imageId);
}
