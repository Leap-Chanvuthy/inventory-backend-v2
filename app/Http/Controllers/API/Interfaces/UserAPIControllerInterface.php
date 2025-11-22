<?php

namespace App\Http\Controllers\API\Interfaces;

/**
 * @OA\Tag(
 *     name="Users",
 *     description="API Endpoints for retrieving user information"
 * )
 */
interface UserAPIControllerInterface
{
    /**
     * @OA\Get(
     *     path="/api/users/{id}",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     summary="Get a user by ID",
     *     description="Retrieve a single user by specifying the user ID.",
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="User ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="User retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="phone_number", type="string", example="099999999"),
     *                 @OA\Property(property="profile_picture", type="string", example="uploads/profile.jpg"),
     *                 @OA\Property(property="role", type="string", example="ADMIN"),
     *                 @OA\Property(property="email", type="string", example="john@gmail.com"),
     *                 @OA\Property(property="ip_address", type="string", example="192.168.1.10"),
     *                 @OA\Property(property="device", type="string", example="Chrome on MacOS"),
     *                 @OA\Property(property="last_activity", type="string", example="2025-01-12 12:00:00"),
     *                 @OA\Property(property="created_at", type="string", example="2024-01-01 08:00:00"),
     *                 @OA\Property(property="updated_at", type="string", example="2024-02-01 08:00:00")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="User not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="User not found"),
     *             @OA\Property(property="errors", type="string", example=null)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed getting user"),
     *             @OA\Property(property="errors", type="string", example="Exception message here")
     *         )
     *     )
     * )
     */
    public function getUserById();


    /**
     * @OA\Get(
     *     path="/api/users",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     summary="Get all users with pagination, filters, and sorting",
     *     description="Retrieve a paginated list of all users. Supports filtering, sorting, and search.",
     *
     *     @OA\Parameter(
     *         name="filter[id]",
     *         in="query",
     *         description="Filter by user ID",
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[role]",
     *         in="query",
     *         description="Filter by user role",
     *         @OA\Schema(type="string", example="ADMIN")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[search]",
     *         in="query",
     *         description="Search by name, email, or phone number",
     *         @OA\Schema(type="string", example="John")
     *     ),
     *
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="Sort fields (e.g., -created_at, name, email)",
     *         @OA\Schema(type="string", example="-created_at")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Users retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Users retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=10),
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="John Doe"),
     *                         @OA\Property(property="email", type="string", example="john@gmail.com"),
     *                         @OA\Property(property="phone_number", type="string", example="099999999"),
     *                         @OA\Property(property="role", type="string", example="ADMIN"),
     *                         @OA\Property(property="created_at", type="string", example="2025-01-01 12:00:00")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Failed getting users",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed getting users"),
     *             @OA\Property(property="errors", type="string", example="Exception message here")
     *         )
     *     )
     * )
     */
    public function getUsers();
}
