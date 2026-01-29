<?php

namespace App\Http\Controllers\API\Interfaces;

/**
 * @OA\Schema(
 *   schema="SupplierBankInput",
 *   type="object",
 *   required={"bank_name"},
 *   @OA\Property(
 *     property="bank_name",
 *     type="string",
 *     enum={"ABA","ACLEDA","WING","BAKONG"},
 *     example="ABA",
 *     description="Payment method/bank name (must match PaymentMethodEnum)"
 *   ),
 *   @OA\Property(property="account_number", type="string", nullable=true, example="123456789"),
 *   @OA\Property(property="account_holder_name", type="string", nullable=true, example="ABC Trading Co., Ltd"),
 *   @OA\Property(property="payment_link", type="string", nullable=true, example=null),
 *   @OA\Property(property="qr_code_image", type="string", format="binary", nullable=true, description="Optional QR image upload")
 * )
 *
 * @OA\Schema(
 *   schema="SupplierBank",
 *   type="object",
 *   @OA\Property(property="id", type="integer", example=1),
 *   @OA\Property(property="supplier_id", type="integer", example=1),
 *   @OA\Property(property="bank_name", type="string", example="ABA"),
 *   @OA\Property(property="account_number", type="string", nullable=true, example="123456789"),
 *   @OA\Property(property="account_holder_name", type="string", nullable=true, example="ABC Trading Co., Ltd"),
 *   @OA\Property(property="payment_link", type="string", nullable=true, example=null),
 *   @OA\Property(property="qr_code_image", type="string", nullable=true, example=null),
 *   @OA\Property(property="bank_label", type="string", nullable=true, example="https://.../aba-logo.png"),
 *   @OA\Property(property="created_at", type="string", example="2026-01-01 12:00:00"),
 *   @OA\Property(property="updated_at", type="string", example="2026-01-01 12:00:00")
 * )
 *
 * @OA\Schema(
 *   schema="Supplier",
 *   type="object",
 *   @OA\Property(property="id", type="integer", example=1),
 *   @OA\Property(property="official_name", type="string", example="ABC Trading Co., Ltd"),
 *   @OA\Property(property="supplier_code", type="string", example="SUP-8K2JQ9XA"),
 *   @OA\Property(property="contact_person", type="string", nullable=true, example="John Doe"),
 *   @OA\Property(property="phone", type="string", nullable=true, example="012345678"),
 *   @OA\Property(property="email", type="string", nullable=true, example="supplier@example.com"),
 *   @OA\Property(property="image", type="string", nullable=true, example=null),
 *
 *   @OA\Property(property="legal_business_name", type="string", nullable=true, example="ABC Trading Co., Ltd"),
 *   @OA\Property(property="tax_identification_number", type="string", nullable=true, example="1234567890"),
 *   @OA\Property(property="business_registration_number", type="string", nullable=true, example="BR-00001234"),
 *   @OA\Property(property="supplier_category", type="string", example="Raw Material"),
 *   @OA\Property(property="business_description", type="string", nullable=true, example="Supplier of raw materials"),
 *
 *   @OA\Property(property="address_line1", type="string", example="Street 123"),
 *   @OA\Property(property="address_line2", type="string", nullable=true, example="Building A"),
 *   @OA\Property(property="village", type="string", example="Village 1"),
 *   @OA\Property(property="commune", type="string", example="Commune 1"),
 *   @OA\Property(property="district", type="string", example="District 1"),
 *   @OA\Property(property="city", type="string", example="Phnom Penh"),
 *   @OA\Property(property="province", type="string", example="Phnom Penh"),
 *   @OA\Property(property="postal_code", type="string", nullable=true, example="12000"),
 *   @OA\Property(property="latitude", type="number", nullable=true, example=11.5564),
 *   @OA\Property(property="longitude", type="number", nullable=true, example=104.9282),
 *
 *   @OA\Property(property="banks_count", type="integer", example=2),
 *   @OA\Property(property="banks", type="array", @OA\Items(ref="#/components/schemas/SupplierBank")),
 *   @OA\Property(property="created_at", type="string", example="2026-01-01 12:00:00"),
 *   @OA\Property(property="updated_at", type="string", example="2026-01-01 12:00:00")
 * )
 *
 * @OA\Schema(
 *   schema="SupplierImportHistory",
 *   type="object",
 *   @OA\Property(property="id", type="integer", example=1),
 *   @OA\Property(property="filename", type="string", example="suppliers.xlsx"),
 *   @OA\Property(property="size", type="integer", example=204800),
 *   @OA\Property(property="uploaded_by", type="integer", example=5),
 *   @OA\Property(property="total_uploaded", type="integer", example=100),
 *   @OA\Property(property="uploaded_at", type="string", example="2026-01-10 10:30:00"),
 *   @OA\Property(property="created_at", type="string", example="2026-01-10 10:30:00"),
 *   @OA\Property(property="updated_at", type="string", example="2026-01-10 10:30:00"),
 *   @OA\Property(
 *     property="user",
 *     type="object",
 *     nullable=true,
 *     description="Uploader user (loaded via withRelations: ['user'])",
 *     @OA\Property(property="id", type="integer", example=5),
 *     @OA\Property(property="name", type="string", example="John Doe"),
 *     @OA\Property(property="email", type="string", example="john@example.com")
 *   ),
 *   @OA\Property(property="user_count", type="integer", nullable=true, example=1, description="If withCounts is enabled")
 * )
 *
 * @OA\Tag(
 *   name="Suppliers",
 *   description="API Endpoints for managing suppliers"
 * )
 *
 * 
 * @OA\Tag(
 *     name="Suppliers",
 *     description="API Endpoints for managing suppliers. (Only Accessible for: ADMIN, and STOCK_CONTROLLER Users)"
 * )
 */
interface SupplierAPIControllerInterface
{
    /**
     * @OA\Get(
     *     path="/api/suppliers",
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
     *     path="/api/suppliers/{id}",
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


    /**
     * @OA\Post(
     *   path="/api/suppliers",
     *   tags={"Suppliers"},
     *   security={{"Bearer":{}}},
     *   summary="Create supplier (with up to 4 payment methods)",
     *   description="Create supplier and optionally create supplier banks. banks[*][bank_name] must be one of PaymentMethodEnum values; max 4; no duplicates. Use multipart/form-data to upload images.",
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *       mediaType="multipart/form-data",
     *       @OA\Schema(
     *         required={"official_name","supplier_category","address_line1","village","commune","district","city","province"},
     *         @OA\Property(property="official_name", type="string", example="ABC Trading Co., Ltd"),
     *         @OA\Property(property="supplier_code", type="string", example="SUPXXXXXXX", description="Optional; generated if missing"),
     *         @OA\Property(property="contact_person", type="string", nullable=true, example="John Doe"),
     *         @OA\Property(property="phone", type="string", nullable=true, example="012345678"),
     *         @OA\Property(property="email", type="string", nullable=true, example="supplier@example.com"),
     *         @OA\Property(property="image", type="string", format="binary", nullable=true),
     *
     *         @OA\Property(property="legal_business_name", type="string", nullable=true, example="ABC Trading Co., Ltd"),
     *         @OA\Property(property="tax_identification_number", type="string", nullable=true, example="1234567890"),
     *         @OA\Property(property="business_registration_number", type="string", nullable=true, example="BR-00001234"),
     *         @OA\Property(property="supplier_category", type="string", example="Raw Material"),
     *         @OA\Property(property="business_description", type="string", nullable=true, example="Supplier of raw materials"),
     *
     *         @OA\Property(property="address_line1", type="string", example="Street 123"),
     *         @OA\Property(property="address_line2", type="string", nullable=true, example="Building A"),
     *         @OA\Property(property="village", type="string", example="Village 1"),
     *         @OA\Property(property="commune", type="string", example="Commune 1"),
     *         @OA\Property(property="district", type="string", example="District 1"),
     *         @OA\Property(property="city", type="string", example="Phnom Penh"),
     *         @OA\Property(property="province", type="string", example="Phnom Penh"),
     *         @OA\Property(property="postal_code", type="string", nullable=true, example="12000"),
     *         @OA\Property(property="latitude", type="number", nullable=true, example=11.5564),
     *         @OA\Property(property="longitude", type="number", nullable=true, example=104.9282),
    *         @OA\Property(property="banks[0][bank_name]", type="string", enum={"ABA","ACLEDA","WING","BAKONG"}, example="ABA"),
    *         @OA\Property(property="banks[0][account_number]", type="string", nullable=true, example="123456789"),
    *         @OA\Property(property="banks[0][account_holder_name]", type="string", nullable=true, example="ABC Trading Co., Ltd"),
    *         @OA\Property(property="banks[0][payment_link]", type="string", nullable=true, example=null),
    *         @OA\Property(property="banks[0][qr_code_image]", type="string", format="binary", nullable=true),
    *
    *         @OA\Property(property="banks[1][bank_name]", type="string", enum={"ABA","ACLEDA","WING","BAKONG"}, nullable=true, example="WING"),
    *         @OA\Property(property="banks[1][account_number]", type="string", nullable=true, example="987654321"),
    *         @OA\Property(property="banks[1][account_holder_name]", type="string", nullable=true, example="ABC Trading Co., Ltd"),
    *         @OA\Property(property="banks[1][payment_link]", type="string", nullable=true, example=null),
    *         @OA\Property(property="banks[1][qr_code_image]", type="string", format="binary", nullable=true),
    *
    *         @OA\Property(property="banks[2][bank_name]", type="string", enum={"ABA","ACLEDA","WING","BAKONG"}, nullable=true, example="ACLEDA"),
    *         @OA\Property(property="banks[2][account_number]", type="string", nullable=true),
    *         @OA\Property(property="banks[2][account_holder_name]", type="string", nullable=true),
    *         @OA\Property(property="banks[2][payment_link]", type="string", nullable=true),
    *         @OA\Property(property="banks[2][qr_code_image]", type="string", format="binary", nullable=true),
    *
    *         @OA\Property(property="banks[3][bank_name]", type="string", enum={"ABA","ACLEDA","WING","BAKONG"}, nullable=true, example="BAKONG"),
    *         @OA\Property(property="banks[3][account_number]", type="string", nullable=true),
    *         @OA\Property(property="banks[3][account_holder_name]", type="string", nullable=true),
    *         @OA\Property(property="banks[3][payment_link]", type="string", nullable=true),
    *         @OA\Property(property="banks[3][qr_code_image]", type="string", format="binary", nullable=true)
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=201,
     *     description="Supplier created successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="status", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Supplier created successfully"),
     *       @OA\Property(property="data", ref="#/components/schemas/Supplier")
     *     )
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Validation Error",
     *     @OA\JsonContent(
     *       @OA\Property(property="status", type="boolean", example=false),
     *       @OA\Property(property="message", type="string", example="Validation Error"),
     *       @OA\Property(property="errors", type="object")
     *     )
     *   )
     * )
     */


    public function store();


    /**
     * @OA\Post(
     *   path="/api/suppliers/{id}",
     *   tags={"Suppliers"},
     *   security={{"Bearer":{}}},
     *   summary="Update supplier (banks upsert by bank_name)",
     *   description="Updates supplier fields and upserts banks by bank_name: if bank_name exists -> update, else -> create; total cannot exceed 4. Use multipart/form-data for images.",
     *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *       mediaType="multipart/form-data",
     *       @OA\Schema(
     *                 required={"_method"},
     *
     *                 @OA\Property(
     *                     property="_method",
     *                     type="string",
     *                     example="PATCH",
     *                     description="Set this to PATCH to emulate PATCH request using POST"
     *                 ),
     *         @OA\Property(property="official_name", type="string", example="ABC Trading Co., Ltd"),
     *         @OA\Property(property="contact_person", type="string", nullable=true, example="John Doe"),
     *         @OA\Property(property="phone", type="string", nullable=true, example="012345678"),
     *         @OA\Property(property="email", type="string", nullable=true, example="supplier@example.com"),
     *         @OA\Property(property="image", type="string", format="binary", nullable=true),
     *         @OA\Property(property="supplier_category", type="string", example="Raw Material"),
     *
     *         @OA\Property(property="address_line1", type="string", example="Street 123"),
     *         @OA\Property(property="address_line2", type="string", nullable=true, example="Building A"),
     *         @OA\Property(property="village", type="string", example="Village 1"),
     *         @OA\Property(property="commune", type="string", example="Commune 1"),
     *         @OA\Property(property="district", type="string", example="District 1"),
     *         @OA\Property(property="city", type="string", example="Phnom Penh"),
     *         @OA\Property(property="province", type="string", example="Phnom Penh"),
    *          
    *         @OA\Property(property="banks[0][bank_name]", type="string", enum={"ABA","ACLEDA","WING","BAKONG"}, nullable=true, example="ABA"),
    *         @OA\Property(property="banks[0][account_number]", type="string", nullable=true, example="123456789"),
    *         @OA\Property(property="banks[0][account_holder_name]", type="string", nullable=true, example="ABC Trading Co., Ltd"),
    *         @OA\Property(property="banks[0][payment_link]", type="string", nullable=true, example=null),
    *         @OA\Property(property="banks[0][qr_code_image]", type="string", format="binary", nullable=true),
    *
    *         @OA\Property(property="banks[1][bank_name]", type="string", enum={"ABA","ACLEDA","WING","BAKONG"}, nullable=true),
    *         @OA\Property(property="banks[1][account_number]", type="string", nullable=true),
    *         @OA\Property(property="banks[1][account_holder_name]", type="string", nullable=true),
    *         @OA\Property(property="banks[1][payment_link]", type="string", nullable=true),
    *         @OA\Property(property="banks[1][qr_code_image]", type="string", format="binary", nullable=true),
    *
    *         @OA\Property(property="banks[2][bank_name]", type="string", enum={"ABA","ACLEDA","WING","BAKONG"}, nullable=true),
    *         @OA\Property(property="banks[2][account_number]", type="string", nullable=true),
    *         @OA\Property(property="banks[2][account_holder_name]", type="string", nullable=true),
    *         @OA\Property(property="banks[2][payment_link]", type="string", nullable=true),
    *         @OA\Property(property="banks[2][qr_code_image]", type="string", format="binary", nullable=true),
    *
    *         @OA\Property(property="banks[3][bank_name]", type="string", enum={"ABA","ACLEDA","WING","BAKONG"}, nullable=true),
    *         @OA\Property(property="banks[3][account_number]", type="string", nullable=true),
    *         @OA\Property(property="banks[3][account_holder_name]", type="string", nullable=true),
    *         @OA\Property(property="banks[3][payment_link]", type="string", nullable=true),
    *         @OA\Property(property="banks[3][qr_code_image]", type="string", format="binary", nullable=true)
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Supplier updated successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="status", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Supplier updated successfully"),
     *       @OA\Property(property="data", ref="#/components/schemas/Supplier")
     *     )
     *   ),
     *   @OA\Response(
     *     response=404,
     *     description="Supplier not found",
     *     @OA\JsonContent(
     *       @OA\Property(property="status", type="boolean", example=false),
     *       @OA\Property(property="message", type="string", example="Supplier not found")
     *     )
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Validation Error",
     *     @OA\JsonContent(
     *       @OA\Property(property="status", type="boolean", example=false),
     *       @OA\Property(property="message", type="string", example="Validation Error"),
     *       @OA\Property(property="errors", type="object")
     *     )
     *   )
     * )
     */
    public function update();



    /**
     * @OA\Delete(
     *     path="/api/suppliers/{id}",
     *     tags={"Suppliers"},
     *     security={{"Bearer":{}}},
     *     summary="Delete supplier by ID",
     *     description="Deletes a supplier by its ID.",
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
     *         description="Supplier deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Supplier deleted successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Supplier not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Supplier not found")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Failed to delete supplier",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to delete supplier"),
     *             @OA\Property(property="errors", type="string", example="Exception message here")
     *         )
     *     )
     * )
     */
    public function delete($id);


    /**
     * @OA\Post(
     *   path="/api/suppliers/import",
     *   tags={"Suppliers"},
     *   security={{"Bearer":{}}},
     *   summary="Import suppliers from Excel/CSV",
     *   description="Upload an .xlsx or .csv file using multipart/form-data. Requires ADMIN role.",
     *
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *       mediaType="multipart/form-data",
     *       @OA\Schema(
     *         required={"supplier_file"},
     *         @OA\Property(
     *           property="supplier_file",
     *           type="string",
     *           format="binary",
     *           description="Excel/CSV file (.xlsx or .csv), max 5MB"
     *         )
     *       )
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=201,
     *     description="Supplier imported successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="status", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Supplier imported successfully"),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="total", type="integer", example=100)
     *       )
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=422,
     *     description="Import validation failed",
     *     @OA\JsonContent(
     *       @OA\Property(property="status", type="boolean", example=false),
     *       @OA\Property(property="message", type="string", example="Import validation failed"),
     *       @OA\Property(property="errors", type="object")
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=500,
     *     description="Import failed",
     *     @OA\JsonContent(
     *       @OA\Property(property="status", type="boolean", example=false),
     *       @OA\Property(property="message", type="string", example="Import failed"),
     *       @OA\Property(property="errors", type="string", nullable=true, example="Exception message here")
     *     )
     *   )
     * )
     */
    public function import();

    /**
     * @OA\Get(
     *   path="/api/suppliers/import-histories",
     *   tags={"Suppliers"},
     *   security={{"Bearer":{}}},
     *   summary="Get supplier import histories (paginated)",
     *   description="Retrieve paginated import history records. Supports filter[search] which searches filename and uploader (user.name / user.email). Requires ADMIN role.",
     *
     *   @OA\Parameter(
     *     name="page",
     *     in="query",
     *     description="Page number",
     *     @OA\Schema(type="integer", example=1)
     *   ),
     *
     *   @OA\Parameter(
     *     name="per_page",
     *     in="query",
     *     description="Number of records per page (default 10, max 100)",
     *     @OA\Schema(type="integer", example=10)
     *   ),
     *
     *   @OA\Parameter(
     *     name="filter[id]",
     *     in="query",
     *     description="Filter by import history id",
     *     @OA\Schema(type="integer")
     *   ),
     *
     *   @OA\Parameter(
     *     name="filter[search]",
     *     in="query",
     *     description="Search by filename or uploader name/email",
     *     @OA\Schema(type="string")
     *   ),
     *
     *   @OA\Parameter(
     *     name="sort",
     *     in="query",
     *     description="Sort by fields (e.g. -created_at, uploaded_by, updated_at)",
     *     @OA\Schema(type="string", example="-created_at")
     *   ),
     *
     *   @OA\Response(
     *     response=200,
     *     description="Import histories retrieved successfully",
     *     @OA\JsonContent(
     *       @OA\Property(property="status", type="boolean", example=true),
     *       @OA\Property(property="message", type="string", example="Import histories retrieved successfully"),
     *       @OA\Property(
     *         property="data",
     *         type="object",
     *         @OA\Property(property="current_page", type="integer", example=1),
     *         @OA\Property(property="per_page", type="integer", example=10),
     *         @OA\Property(property="total", type="integer", example=25),
     *         @OA\Property(
     *           property="data",
     *           type="array",
     *           @OA\Items(ref="#/components/schemas/SupplierImportHistory")
     *         )
     *       )
     *     )
     *   ),
     *
     *   @OA\Response(
     *     response=500,
     *     description="Error fetching import histories",
     *     @OA\JsonContent(
     *       @OA\Property(property="status", type="boolean", example=false),
     *       @OA\Property(property="message", type="string", example="Error fetching import histories"),
     *       @OA\Property(property="errors", type="string", nullable=true, example="Exception message here")
     *     )
     *   )
     * )
     */
    public function getImportHistories();

}
