<?php

namespace App\Http\Controllers\API\Interfaces;

/**
 * @OA\Tag(
 *     name="Suppliers",
 *     description="API Endpoints for managing suppliers"
 * )
 */
interface SupplierAPIControllerInterface
{
    /**
     * @OA\Get(
     *     path="/api/suppliers/suppliers",
     *     tags={"Suppliers"},
     *     security={{"Bearer":{}}},
     *     summary="Get all suppliers with pagination, filters, sorting",
     *     description="Retrieve a paginated list of suppliers. Supports search via filter[search], sorting, and pagination.",
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of records per page (default 10, max 100)",
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[id]",
     *         in="query",
     *         description="Filter by supplier id",
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[search]",
     *         in="query",
     *         description="Search by official_name, email, supplier_code, tax_identification_number, phone",
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="Sort by fields (e.g. -created_at, supplier_code, supplier_category)",
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Suppliers retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Suppliers retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=10),
     *                 @OA\Property(property="total", type="integer", example=100),
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="official_name", type="string", example="ABC Trading Co., Ltd"),
     *                         @OA\Property(property="supplier_code", type="string", example="SUP-8K2JQ9XA"),
     *                         @OA\Property(property="contact_person", type="string", example="John Doe"),
     *                         @OA\Property(property="phone", type="string", example="012345678"),
     *                         @OA\Property(property="email", type="string", example="supplier@example.com"),
     *
     *                         @OA\Property(property="legal_business_name", type="string", example="ABC Trading Co., Ltd"),
     *                         @OA\Property(property="tax_identification_number", type="string", example="1234567890"),
     *                         @OA\Property(property="business_registration_number", type="string", example="BR-00001234"),
     *                         @OA\Property(property="supplier_category", type="string", example="Raw Material"),
     *                         @OA\Property(property="business_description", type="string", example="Supplier of raw materials"),
     *
     *                         @OA\Property(property="address_line1", type="string", example="Street 123"),
     *                         @OA\Property(property="address_line2", type="string", example="Building A"),
     *                         @OA\Property(property="village", type="string", example="Village 1"),
     *                         @OA\Property(property="commune", type="string", example="Commune 1"),
     *                         @OA\Property(property="district", type="string", example="District 1"),
     *                         @OA\Property(property="city", type="string", example="Phnom Penh"),
     *                         @OA\Property(property="province", type="string", example="Phnom Penh"),
     *                         @OA\Property(property="postal_code", type="string", example="12000"),
     *                         @OA\Property(property="latitude", type="string", example="11.5564"),
     *                         @OA\Property(property="longitude", type="string", example="104.9282"),
     *
     *                         @OA\Property(property="banks_count", type="integer", example=2),
     *                         @OA\Property(
     *                             property="banks",
     *                             type="array",
     *                             @OA\Items(
     *                                 @OA\Property(property="id", type="integer", example=1),
     *                                 @OA\Property(property="supplier_id", type="integer", example=1),
     *                                 @OA\Property(property="bank_name", type="string", example="ABA"),
     *                                 @OA\Property(property="account_number", type="string", example="123456789"),
     *                                 @OA\Property(property="account_holder_name", type="string", example="ABC Trading Co., Ltd"),
     *                                 @OA\Property(property="payment_link", type="string", nullable=true, example=null),
     *                                 @OA\Property(property="qr_code_image", type="string", nullable=true, example=null),
     *                                 @OA\Property(property="bank_label", type="string", example="https://.../aba-logo.png"),
     *                                 @OA\Property(property="created_at", type="string", example="2026-01-01 12:00:00"),
     *                                 @OA\Property(property="updated_at", type="string", example="2026-01-01 12:00:00")
     *                             )
     *                         ),
     *
     *                         @OA\Property(property="created_at", type="string", example="2026-01-01 12:00:00"),
     *                         @OA\Property(property="updated_at", type="string", example="2026-01-01 12:00:00")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error fetching suppliers",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error fetching suppliers"),
     *             @OA\Property(property="errors", type="string", example="Exception message here")
     *         )
     *     )
     * )
     */
    public function getSuppliers();

    /**
     * @OA\Get(
     *     path="/api/suppliers/suppliers/{id}",
     *     tags={"Suppliers"},
     *     security={{"Bearer":{}}},
     *     summary="Get supplier by ID",
     *     description="Retrieve a single supplier including banks.",
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Supplier ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Supplier retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Supplier retrieved successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Supplier not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Supplier not found"),
     *             @OA\Property(property="errors", type="string", nullable=true, example=null)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Failed getting supplier",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed getting supplier"),
     *             @OA\Property(property="errors", type="string", example="Exception message here")
     *         )
     *     )
     * )
     */
    public function getSupplierById();
}