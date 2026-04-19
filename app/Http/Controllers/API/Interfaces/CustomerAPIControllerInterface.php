<?php

namespace App\Http\Controllers\API\Interfaces;

use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Customers",
 *     description="API Endpoints for managing customers (ADMIN & VENDER ROLE)"
 * )
 */
interface CustomerAPIControllerInterface
{
    /**
     * @OA\Get(
     *     path="/api/customers",
     *     tags={"Customers"},
     *     security={{"Bearer":{}}},
     *     summary="Get customers (pagination, filters, sorting)",
     *     description="Retrieve a paginated list of customers. Supports filtering, sorting, and search (based on your query builder).",
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page (default depends on backend)",
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Parameter(
     *         name="filter[id]",
     *         in="query",
     *         description="Filter by customer ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="filter[search]",
     *         in="query",
     *         description="Search by fullname, phone number, email, etc. (depends on implementation)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="filter[customer_status]",
     *         in="query",
    *         description="Filter by status (active, inactive, blacklisted)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="filter[customer_category_id]",
     *         in="query",
     *         description="Filter by customer category ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="sort",
     *         in="query",
     *         description="Sort fields (e.g., -created_at, fullname)",
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Customers retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Customers retrieved successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error fetching customers",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error fetching customers"),
     *             @OA\Property(property="errors", type="string", example="Exception message here")
     *         )
     *     )
     * )
     */
    public function index(Request $request);

    /**
     * @OA\Get(
     *     path="/api/customers/{id}",
     *     tags={"Customers"},
     *     security={{"Bearer":{}}},
     *     summary="Get a customer by ID",
     *     description="Retrieve a single customer by specifying the customer ID.",
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Customer ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Customer retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Customer retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="image", type="string", example="https://api.dicebear.com/9.x/adventurer/svg?seed=1"),
     *                 @OA\Property(property="fullname", type="string", example="John Doe"),
     *                 @OA\Property(property="customer_code", type="string", example="CUS00000001"),
     *                 @OA\Property(property="phone_number", type="string", example="099999999"),
     *                 @OA\Property(property="email_address", type="string", example="john@example.com"),
     *                 @OA\Property(property="social_media", type="string", example="https://facebook.com/john"),
     *                 @OA\Property(property="customer_address", type="string", example="Phnom Penh"),
     *                 @OA\Property(property="google_map_link", type="string", example="https://maps.google.com/..."),
    *                 @OA\Property(property="customer_status", type="string", example="active"),
     *                 @OA\Property(property="customer_category_id", type="integer", example=1),
     *                 @OA\Property(property="customer_note", type="string", example="Important customer"),
     *                 @OA\Property(property="created_at", type="string", example="2025-01-01 08:00:00"),
     *                 @OA\Property(property="updated_at", type="string", example="2025-01-01 08:00:00")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Customer not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Customer not found"),
     *             @OA\Property(property="errors", type="string", example=null)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error fetching customer",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error fetching customer"),
     *             @OA\Property(property="errors", type="string", example="Exception message here")
     *         )
     *     )
     * )
     */
    public function show($id);

    /**
     * @OA\Post(
     *     path="/api/customers",
     *     tags={"Customers"},
     *     security={{"Bearer":{}}},
     *     summary="Create a new customer",
     *     description="Create a new customer. Supports uploading image (jpg, png).",
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"fullname","phone_number","customer_address","customer_status","customer_category_id"},
     *                 @OA\Property(
     *                     description="Customer image (jpeg, png, jpg)",
     *                     property="image",
     *                     type="string",
     *                     format="binary"
     *                 ),
     *                 @OA\Property(property="customer_code", type="string", example="CUS00000001", description="Optional; auto-generated if not provided"),
     *                 @OA\Property(property="fullname", type="string", example="John Doe"),
     *                 @OA\Property(property="email_address", type="string", example="john@example.com"),
     *                 @OA\Property(property="phone_number", type="string", example="099999999"),
     *                 @OA\Property(property="social_media", type="string", example="https://facebook.com/john"),
     *                 @OA\Property(property="customer_address", type="string", example="Phnom Penh"),
     *                 @OA\Property(property="google_map_link", type="string", example="https://maps.google.com/..."),
    *                 @OA\Property(property="customer_status", type="string", example="active", description="active | inactive | blacklisted"),
     *                 @OA\Property(property="customer_category_id", type="integer", example=1),
     *                 @OA\Property(property="customer_note", type="string", example="Note...")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Customer created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Customer created successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation Errors",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation Errors"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error creating customer",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error creating customer"),
     *             @OA\Property(property="errors", type="string", example="Exception message here")
     *         )
     *     )
     * )
     */
    public function store(Request $request);

    /**
     * @OA\Patch(
     *     path="/api/customers/{id}",
     *     tags={"Customers"},
     *     security={{"Bearer":{}}},
     *     summary="Update an existing customer",
     *     description="Update customer fields. Supports uploading image (jpg, png).",
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Customer ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     description="Customer image (jpeg, png, jpg)",
     *                     property="image",
     *                     type="string",
     *                     format="binary"
     *                 ),
     *                 @OA\Property(property="fullname", type="string", example="Updated Name"),
     *                 @OA\Property(property="email_address", type="string", example="updated@example.com"),
     *                 @OA\Property(property="phone_number", type="string", example="099123456"),
     *                 @OA\Property(property="social_media", type="string", example="https://facebook.com/updated"),
     *                 @OA\Property(property="customer_address", type="string", example="Updated address"),
     *                 @OA\Property(property="google_map_link", type="string", example="https://maps.google.com/..."),
    *                 @OA\Property(property="customer_status", type="string", example="inactive"),
     *                 @OA\Property(property="customer_category_id", type="integer", example=2),
     *                 @OA\Property(property="customer_note", type="string", example="Updated note")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Customer updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Customer updated successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Customer not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Customer not found"),
     *             @OA\Property(property="errors", type="string", example=null)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation Errors",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation Errors"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error updating customer",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error updating customer"),
     *             @OA\Property(property="errors", type="string", example="Exception message here")
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id);

    /**
     * @OA\Delete(
     *     path="/api/customers/{id}",
     *     tags={"Customers"},
     *     security={{"Bearer":{}}},
     *     summary="Delete a customer",
     *     description="Delete a customer by ID.",
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Customer ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Customer deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Customer deleted successfully"),
     *             @OA\Property(property="data", type="object", example=null)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Customer not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Customer not found"),
     *             @OA\Property(property="errors", type="string", example=null)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Error deleting customer",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error deleting customer"),
     *             @OA\Property(property="errors", type="string", example="Exception message here")
     *         )
     *     )
     * )
     */
    public function destroy($id);

    /**
     * @OA\Get(
     *     path="/api/customers/pos-search",
     *     tags={"Customers"},
     *     security={{"Bearer":{}}},
     *     summary="POS customer search",
     *     description="Fast customer lookup for POS by keyword (name, code, phone).",
     *
     *     @OA\Parameter(
     *         name="keyword",
     *         in="query",
     *         required=true,
     *         description="Search keyword",
     *         @OA\Schema(type="string", example="Sokha")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         required=false,
     *         description="Max records returned (1-50)",
     *         @OA\Schema(type="integer", example=15)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="POS customer search completed",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="POS customer search completed"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Errors"
     *     )
     * )
     */
    public function posSearch(Request $request);

    /**
     * @OA\Get(
     *     path="/api/customers/walk-in",
     *     tags={"Customers"},
     *     security={{"Bearer":{}}},
     *     summary="Resolve walk-in customer",
     *     description="Returns the default walk-in customer for POS checkout.",
     *
     *     @OA\Response(
     *         response=200,
     *         description="Walk-in customer resolved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Walk-in customer resolved successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function walkIn();

    /**
     * @OA\Get(
     *     path="/api/customers/segmented",
     *     tags={"Customers"},
     *     security={{"Bearer":{}}},
     *     summary="Get segmented customers",
     *     description="Retrieve segmented customers by category, status, and tag filters.",
     *
     *     @OA\Parameter(name="category_id", in="query", @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", example="active")),
     *     @OA\Parameter(
     *         name="tag_ids[]",
     *         in="query",
     *         description="Tag ids filter",
     *         @OA\Schema(type="array", @OA\Items(type="integer"))
     *     ),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", example=10)),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Segmented customers retrieved successfully"
     *     ),
     *     @OA\Response(response=422, description="Validation Errors")
     * )
     */
    public function segmented(Request $request);

    /**
     * @OA\Get(
     *     path="/api/customers/{id}/profile",
     *     tags={"Customers"},
     *     security={{"Bearer":{}}},
     *     summary="Get customer profile",
     *     description="Get aggregated customer profile including financials, addresses, and tags.",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *
     *     @OA\Response(response=200, description="Customer profile retrieved successfully"),
     *     @OA\Response(response=404, description="Customer not found")
     * )
     */
    public function profile(int $id);

    /**
     * @OA\Post(
     *     path="/api/customers/addresses/default",
     *     tags={"Customers"},
     *     security={{"Bearer":{}}},
     *     summary="Set default customer address",
     *     description="Set a billing/shipping address as default for a customer.",
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"customer_id","address_id"},
     *             @OA\Property(property="customer_id", type="integer", example=1),
     *             @OA\Property(property="address_id", type="integer", example=12)
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Default address updated successfully"),
     *     @OA\Response(response=422, description="Validation Errors")
     * )
     */
    public function setDefaultAddress(Request $request);

    /**
     * @OA\Post(
     *     path="/api/customers/{id}/credit/can-purchase",
     *     tags={"Customers"},
     *     security={{"Bearer":{}}},
     *     summary="Check credit purchase eligibility",
     *     description="Checks if customer can purchase by credit for provided amount.",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount"},
     *             @OA\Property(property="amount", type="number", format="float", example=150.5)
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Credit purchase check completed"),
     *     @OA\Response(response=422, description="Validation Errors")
     * )
     */
    public function canPurchase(Request $request, int $id);

    /**
     * @OA\Post(
     *     path="/api/customers/{id}/credit/apply-sale",
     *     tags={"Customers"},
     *     security={{"Bearer":{}}},
     *     summary="Apply sale to customer credit",
     *     description="Increases customer outstanding credit balance.",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount"},
     *             @OA\Property(property="amount", type="number", format="float", example=250)
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Sale applied to customer credit successfully"),
     *     @OA\Response(response=422, description="Validation Errors")
     * )
     */
    public function applySale(Request $request, int $id);

    /**
     * @OA\Post(
     *     path="/api/customers/{id}/credit/apply-payment",
     *     tags={"Customers"},
     *     security={{"Bearer":{}}},
     *     summary="Apply payment to customer credit",
     *     description="Reduces customer outstanding credit balance.",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount"},
     *             @OA\Property(property="amount", type="number", format="float", example=100)
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Payment applied to customer credit successfully"),
     *     @OA\Response(response=422, description="Validation Errors")
     * )
     */
    public function applyPayment(Request $request, int $id);

    /**
     * @OA\Get(
     *     path="/api/customers/{id}/stats",
     *     tags={"Customers"},
     *     security={{"Bearer":{}}},
     *     summary="Get customer analytics stats",
     *     description="Returns customer-level analytics and order summary metrics.",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(response=200, description="Customer analytics retrieved successfully")
     * )
     */
    public function stats(int $id);

    /**
     * @OA\Get(
     *     path="/api/customers/{id}/timeline",
     *     tags={"Customers"},
     *     security={{"Bearer":{}}},
     *     summary="Get customer timeline",
     *     description="Returns timeline items from audit logs and POS orders.",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="limit", in="query", required=false, @OA\Schema(type="integer", example=50)),
     *     @OA\Response(response=200, description="Customer timeline retrieved successfully")
     * )
     */
    public function timeline(Request $request, int $id);

    /**
     * @OA\Post(
     *     path="/api/customers/{id}/tags/attach",
     *     tags={"Customers"},
     *     security={{"Bearer":{}}},
     *     summary="Attach customer tags",
     *     description="Attach one or many tags to customer without removing existing tags.",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"tag_ids"},
     *             @OA\Property(property="tag_ids", type="array", @OA\Items(type="integer"), example={1,2,3})
     *         )
     *     ),
     *     @OA\Response(response=200, description="Tags attached successfully")
     * )
     */
    public function attachTags(Request $request, int $id);

    /**
     * @OA\Put(
     *     path="/api/customers/{id}/tags/sync",
     *     tags={"Customers"},
     *     security={{"Bearer":{}}},
     *     summary="Sync customer tags",
     *     description="Replace current customer tags with provided tag ids.",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"tag_ids"},
     *             @OA\Property(property="tag_ids", type="array", @OA\Items(type="integer"), example={2,4})
     *         )
     *     ),
     *     @OA\Response(response=200, description="Tags synced successfully")
     * )
     */
    public function syncTags(Request $request, int $id);

    /**
     * @OA\Delete(
     *     path="/api/customers/{id}/tags/{tagId}",
     *     tags={"Customers"},
     *     security={{"Bearer":{}}},
     *     summary="Detach a customer tag",
     *     description="Detach a single tag from a customer.",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="tagId", in="path", required=true, @OA\Schema(type="integer", example=3)),
     *     @OA\Response(response=200, description="Tag detached successfully")
     * )
     */
    public function detachTag(int $id, int $tagId);
}
