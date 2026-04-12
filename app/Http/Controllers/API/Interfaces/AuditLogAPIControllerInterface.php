<?php

namespace App\Http\Controllers\API\Interfaces;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

interface AuditLogAPIControllerInterface
{

    /**
     * @OA\Get(
     *     path="/api/audit-logs",
     *     summary="Get paginated audit logs",
     *     tags={"Audit Logs"},
     *     security={{"Bearer":{}}},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search across event, auditable_type, auditable_id",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of audit logs",
     *         @OA\JsonContent(type="object",
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="event", type="string"),
     *                     @OA\Property(property="auditable_type", type="string"),
     *                     @OA\Property(property="auditable_id", type="integer"),
     *                     @OA\Property(property="old_values", type="object"),
     *                     @OA\Property(property="new_values", type="object"),
     *                     @OA\Property(property="user_id", type="integer"),
     *                     @OA\Property(property="created_at", type="string", format="date-time")
     *                 )
     *             ),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request);

    /**
     * @OA\Get(
     *     path="/api/audit-logs/{id}",
     *     summary="Get a single audit log by id",
     *     tags={"Audit Logs"},
     *     security={{"Bearer":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Audit log object",
     *         @OA\JsonContent(type="object",
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="event", type="string"),
     *             @OA\Property(property="auditable_type", type="string"),
     *             @OA\Property(property="auditable_id", type="integer"),
     *             @OA\Property(property="old_values", type="object"),
     *             @OA\Property(property="new_values", type="object"),
     *             @OA\Property(property="user_id", type="integer"),
     *             @OA\Property(property="created_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Audit not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function show($id);


}
