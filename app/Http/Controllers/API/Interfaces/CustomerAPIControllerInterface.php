<?php

namespace App\Http\Controllers\API\Interfaces;

use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Customers",
 *     description="Customer CRUD, segmentation, profile, and POS support APIs."
 * )
 *
 */

/**
 * @OA\Schema(
 *   schema="PaymentTerm",
 *   type="string",
 *   enum={"NET_0","NET_7","NET_15","NET_30"},
 *   example="NET_7",
 *   description="Customer payment term policy."
 * )
 *
 * @OA\Schema(
 *   schema="Customer",
 *   type="object",
 *   @OA\Property(property="id", type="integer", example=1),
 *   @OA\Property(property="image", type="string", nullable=true, example="customers/avatar-001.png"),
 *   @OA\Property(property="customer_code", type="string", example="CUS00000001"),
 *   @OA\Property(property="fullname", type="string", example="John Doe"),
 *   @OA\Property(property="email_address", type="string", nullable=true, example="john@example.com"),
 *   @OA\Property(property="phone_number", type="string", example="099999999"),
 *   @OA\Property(property="social_media", type="string", nullable=true, example="https://facebook.com/john"),
 *   @OA\Property(property="customer_address", type="string", example="Phnom Penh"),
 *   @OA\Property(property="google_map_link", type="string", nullable=true, example="https://maps.google.com/..."),
 *   @OA\Property(property="customer_status", type="string", enum={"active","inactive","blacklisted"}, example="active"),
 *   @OA\Property(property="customer_category_id", type="integer", example=1),
 *   @OA\Property(property="customer_category_name", type="string", nullable=true, example="VIP"),
 *   @OA\Property(property="customer_category_discount_percentage", type="number", format="float", example=10.50),
 *   @OA\Property(property="customer_note", type="string", nullable=true, example="Important customer"),
 *   @OA\Property(property="created_at", type="string", format="date-time", example="2026-04-20 10:30:00"),
 *   @OA\Property(property="updated_at", type="string", format="date-time", example="2026-04-20 10:30:00")
 * )
 *
 * @OA\Schema(
 *   schema="PosCustomerSearchResult",
 *   type="object",
 *   @OA\Property(property="id", type="integer", example=1),
 *   @OA\Property(property="name", type="string", example="John Doe"),
 *   @OA\Property(property="phone", type="string", example="099999999"),
 *   @OA\Property(property="category", type="string", nullable=true, example="VIP"),
 *   @OA\Property(property="status", type="string", example="active"),
 *   @OA\Property(property="discount_percentage", type="number", format="float", example=10.50)
 * )
 *
 * @OA\Schema(
 *   schema="PosWalkInCustomer",
 *   type="object",
 *   @OA\Property(property="id", type="integer", example=1),
 *   @OA\Property(property="name", type="string", example="Walk-in Customer"),
 *   @OA\Property(property="phone", type="string", example="000000000"),
 *   @OA\Property(property="category", type="string", nullable=true, example="Retail"),
 *   @OA\Property(property="discount_percentage", type="number", format="float", example=0),
 *   @OA\Property(property="payment_terms", ref="#/components/schemas/PaymentTerm")
 * )
 *
 * @OA\Schema(
 *   schema="CustomerProfile",
 *   type="object",
 *   @OA\Property(
 *     property="basic_info",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="customer_code", type="string", example="CUS00000001"),
 *     @OA\Property(property="fullname", type="string", example="John Doe"),
 *     @OA\Property(property="email_address", type="string", nullable=true, example="john@example.com"),
 *     @OA\Property(property="phone_number", type="string", example="099999999"),
 *     @OA\Property(property="status", type="string", example="active"),
 *     @OA\Property(property="category", type="string", nullable=true, example="VIP"),
 *     @OA\Property(property="discount_percentage", type="number", format="float", example=10.50),
 *     @OA\Property(property="note", type="string", nullable=true, example="Important customer"),
 *     @OA\Property(property="extra_data", type="object", nullable=true)
 *   ),
 *   @OA\Property(
 *     property="financial",
 *     type="object",
 *     nullable=true,
 *     @OA\Property(property="payment_terms", ref="#/components/schemas/PaymentTerm")
 *   ),
 *   @OA\Property(
 *     property="addresses",
 *     type="array",
 *     @OA\Items(
 *       type="object",
 *       @OA\Property(property="id", type="integer", example=12),
 *       @OA\Property(property="type", type="string", nullable=true, example="billing"),
 *       @OA\Property(property="address", type="string", example="No. 99, Street 271"),
 *       @OA\Property(property="google_map_link", type="string", nullable=true, example="https://maps.google.com/..."),
 *       @OA\Property(property="is_default", type="boolean", example=true),
 *       @OA\Property(property="created_at", type="string", format="date-time", example="2026-04-20 10:30:00")
 *     )
 *   ),
 *   @OA\Property(
 *     property="tags",
 *     type="array",
 *     @OA\Items(
 *       type="object",
 *       @OA\Property(property="id", type="integer", example=2),
 *       @OA\Property(property="name", type="string", example="wholesale")
 *     )
 *   )
 * )
 *
 * @OA\Schema(
 *   schema="CustomerPricingPreview",
 *   type="object",
 *   @OA\Property(property="customer_id", type="integer", example=1),
 *   @OA\Property(property="amount", type="number", format="float", example=150.50),
 *   @OA\Property(property="discount_percentage", type="number", format="float", example=10.50),
 *   @OA\Property(property="discounted_amount", type="number", format="float", example=134.70),
 *   @OA\Property(property="payment_terms", ref="#/components/schemas/PaymentTerm"),
 *   @OA\Property(property="can_purchase", type="boolean", example=true)
 * )
 *
 * @OA\Schema(
 *   schema="CustomerSalePricing",
 *   type="object",
 *   @OA\Property(property="customer_id", type="integer", example=1),
 *   @OA\Property(property="amount", type="number", format="float", example=250),
 *   @OA\Property(property="discounted_amount", type="number", format="float", example=225),
 *   @OA\Property(property="payment_terms", ref="#/components/schemas/PaymentTerm")
 * )
 *
 * @OA\Schema(
 *   schema="CustomerPaymentTermResult",
 *   type="object",
 *   @OA\Property(property="customer_id", type="integer", example=1),
 *   @OA\Property(property="payment_terms", ref="#/components/schemas/PaymentTerm")
 * )
 *
 * @OA\Schema(
 *   schema="CustomerStats",
 *   type="object",
 *   @OA\Property(property="total_spent", type="number", format="float", example=3500.75),
 *   @OA\Property(property="order_count", type="integer", example=28),
 *   @OA\Property(property="last_purchase_date", type="string", nullable=true, format="date-time", example="2026-04-19 16:30:00")
 * )
 *
 * @OA\Schema(
 *   schema="CustomerTimelineItem",
 *   type="object",
 *   @OA\Property(property="source", type="string", example="audit"),
 *   @OA\Property(property="event", type="string", example="customer.update"),
 *   @OA\Property(property="payload", type="object", nullable=true),
 *   @OA\Property(property="occurred_at", type="string", format="date-time", example="2026-04-20 10:35:00")
 * )
 */
interface CustomerAPIControllerInterface
{
    /**
     * @OA\Get(
     *     path="/api/customers",
     *     operationId="CustomersIndex",
     *     tags={"Customers"},
     *     security={{"Bearer":{}}},
     *     summary="List customers",
     *     description="Returns paginated customers. Includes category display fields and category discount context used by frontend customer listing.",
     *
     *     @OA\Parameter(name="per_page", in="query", required=false, description="Items per page (1..100)", @OA\Schema(type="integer", default=10, minimum=1, maximum=100)),
     *     @OA\Parameter(name="filter[id]", in="query", required=false, description="Exact customer id", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="filter[search]", in="query", required=false, description="Search by fullname, code, phone, email, category name", @OA\Schema(type="string")),
     *     @OA\Parameter(name="filter[customer_status]", in="query", required=false, description="Status filter", @OA\Schema(type="string", enum={"active","inactive","blacklisted"})),
     *     @OA\Parameter(name="filter[customer_category_id]", in="query", required=false, description="Category id filter", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="sort", in="query", required=false, description="Sort fields: created_at, updated_at, fullname, email_address, phone_number, customer_status, customer_category_id, customer_categories.category_name, customer_categories.discount_percentage. Prefix '-' for descending.", @OA\Schema(type="string", example="-created_at")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Customers retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Customers retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Customer")),
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=10),
     *                 @OA\Property(property="total", type="integer", example=53)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=500, description="Error fetching customers")
     * )
     */
    public function index(Request $request);

    /**
     * @OA\Get(
     *     path="/api/customers/{id}",
     *     operationId="CustomersShow",
     *     tags={"Customers"},
     *     security={{"Bearer":{}}},
     *     summary="Get customer detail",
     *     description="Returns customer detail by id.",
     *
     *     @OA\Parameter(name="id", in="path", required=true, description="Customer id", @OA\Schema(type="integer", example=1)),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Customer retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Customer retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Customer")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Customer not found"),
     *     @OA\Response(response=500, description="Error fetching customer")
     * )
     */
    public function show($id);

    /**
     * @OA\Post(
     *     path="/api/customers",
     *     operationId="CustomersStore",
     *     tags={"Customers"},
     *     security={{"Bearer":{}}},
     *     summary="Create customer",
     *     description="Creates a customer and optionally assigns payment_terms in customer_financials. If payment_terms is omitted, NET_0 is used.",
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"fullname","phone_number","customer_address","customer_status","customer_category_id"},
     *                 @OA\Property(property="image", type="string", format="binary", description="jpeg|png|jpg up to 2MB"),
     *                 @OA\Property(property="customer_code", type="string", maxLength=12, example="CUS00000001", description="Optional. Auto-generated when omitted."),
     *                 @OA\Property(property="fullname", type="string", maxLength=50, example="John Doe"),
     *                 @OA\Property(property="email_address", type="string", nullable=true, maxLength=50, example="john@example.com"),
     *                 @OA\Property(property="phone_number", type="string", maxLength=50, example="099999999"),
     *                 @OA\Property(property="social_media", type="string", nullable=true, maxLength=100, example="https://facebook.com/john"),
     *                 @OA\Property(property="customer_address", type="string", maxLength=255, example="Phnom Penh"),
     *                 @OA\Property(property="google_map_link", type="string", nullable=true, maxLength=100, example="https://maps.google.com/..."),
     *                 @OA\Property(property="customer_status", type="string", enum={"active","inactive","blacklisted"}, example="active"),
     *                 @OA\Property(property="customer_category_id", type="integer", example=1),
     *                 @OA\Property(property="customer_note", type="string", nullable=true, example="Important customer"),
     *                 @OA\Property(property="payment_terms", ref="#/components/schemas/PaymentTerm")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="Customer created successfully"),
     *     @OA\Response(response=422, description="Validation Errors"),
     *     @OA\Response(response=500, description="Error creating customer")
     * )
     */
    public function store(Request $request);

    /**
     * @OA\Patch(
     *     path="/api/customers/{id}",
     *     operationId="CustomersUpdate",
     *     tags={"Customers"},
     *     security={{"Bearer":{}}},
     *     summary="Update customer",
     *     description="Updates customer data. payment_terms is optional but must be a valid enum value when provided.",
     *
     *     @OA\Parameter(name="id", in="path", required=true, description="Customer id", @OA\Schema(type="integer", example=1)),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="image", type="string", format="binary", description="jpeg|png|jpg up to 2MB"),
     *                 @OA\Property(property="fullname", type="string", maxLength=50, example="Updated Name"),
     *                 @OA\Property(property="email_address", type="string", nullable=true, maxLength=50, example="updated@example.com"),
     *                 @OA\Property(property="phone_number", type="string", maxLength=50, example="099123456"),
     *                 @OA\Property(property="social_media", type="string", nullable=true, maxLength=100, example="https://facebook.com/updated"),
     *                 @OA\Property(property="customer_address", type="string", maxLength=255, example="Updated address"),
     *                 @OA\Property(property="google_map_link", type="string", nullable=true, maxLength=100, example="https://maps.google.com/..."),
     *                 @OA\Property(property="customer_status", type="string", enum={"active","inactive","blacklisted"}, example="inactive"),
     *                 @OA\Property(property="customer_category_id", type="integer", example=2),
     *                 @OA\Property(property="customer_note", type="string", nullable=true, example="Updated note"),
     *                 @OA\Property(property="payment_terms", ref="#/components/schemas/PaymentTerm")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Customer updated successfully"),
     *     @OA\Response(response=404, description="Customer not found"),
     *     @OA\Response(response=422, description="Validation Errors"),
     *     @OA\Response(response=500, description="Error updating customer")
     * )
     */
    public function update(Request $request, $id);

    /**
     * @OA\Delete(
     *     path="/api/customers/{id}",
     *     operationId="CustomersDestroy",
     *     tags={"Customers"},
     *     security={{"Bearer":{}}},
     *     summary="Delete customer",
     *     description="Soft-deletes a customer record.",
     *
     *     @OA\Parameter(name="id", in="path", required=true, description="Customer id", @OA\Schema(type="integer", example=1)),
     *
     *     @OA\Response(response=200, description="Customer deleted successfully"),
     *     @OA\Response(response=404, description="Customer not found"),
     *     @OA\Response(response=500, description="Error deleting customer")
     * )
     */
    public function destroy($id);

    /**
     * @OA\Get(
     *     path="/api/customers/pos-search",
     *     operationId="CustomersPosSearch",
     *     tags={"Customers"},
     *     security={{"Bearer":{}}},
     *     summary="POS customer search",
     *     description="Fast search endpoint for POS customer picker.",
     *
     *     @OA\Parameter(name="keyword", in="query", required=true, description="Search text", @OA\Schema(type="string", maxLength=100, example="Sokha")),
     *     @OA\Parameter(name="limit", in="query", required=false, description="Result size (1..20)", @OA\Schema(type="integer", minimum=1, maximum=20, example=15)),
     *
     *     @OA\Response(
     *         response=200,
     *         description="POS customer search completed",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="POS customer search completed"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/PosCustomerSearchResult"))
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation Errors")
     * )
     */
    public function posSearch(Request $request);

    /**
     * @OA\Get(
     *     path="/api/customers/walk-in",
     *     operationId="CustomersWalkIn",
     *     tags={"Customers"},
     *     security={{"Bearer":{}}},
     *     summary="Resolve walk-in customer",
     *     description="Returns the default walk-in customer payload for POS checkout, including discount and payment_terms.",
     *
     *     @OA\Response(
     *         response=200,
     *         description="Walk-in customer resolved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Walk-in customer resolved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/PosWalkInCustomer")
     *         )
     *     )
     * )
     */
    public function walkIn();

    /**
     * @OA\Get(
     *     path="/api/customers/segmented",
     *     operationId="CustomersSegmented",
     *     tags={"Customers"},
     *     security={{"Bearer":{}}},
     *     summary="Segmented customer listing",
     *     description="Returns filtered customer segments for campaigning, segmentation views, and reporting.",
     *
     *     @OA\Parameter(name="category_id", in="query", required=false, @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string", enum={"active","inactive","blacklisted"}, example="active")),
     *     @OA\Parameter(name="tag_ids[]", in="query", required=false, description="Tag ids filter", @OA\Schema(type="array", @OA\Items(type="integer"))),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", minimum=1, maximum=100, example=10)),
     *
     *     @OA\Response(response=200, description="Segmented customers retrieved successfully"),
     *     @OA\Response(response=422, description="Validation Errors")
     * )
     */
    public function segmented(Request $request);

    /**
     * @OA\Get(
     *     path="/api/customers/{id}/profile",
     *     operationId="CustomersProfile",
     *     tags={"Customers"},
     *     security={{"Bearer":{}}},
     *     summary="Get customer profile",
     *     description="Aggregated profile including basic info, category discount, payment_terms, addresses, and tags.",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Customer profile retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Customer profile retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/CustomerProfile")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Customer not found")
     * )
     */
    public function profile(int $id);

    /**
     * @OA\Post(
     *     path="/api/customers/addresses/default",
     *     operationId="CustomersSetDefaultAddress",
     *     tags={"Customers"},
     *     security={{"Bearer":{}}},
     *     summary="Set default customer address",
     *     description="Marks one existing address as default for the target customer.",
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
     *     operationId="CustomersCanPurchase",
     *     tags={"Customers"},
     *     security={{"Bearer":{}}},
     *     summary="POS checkout pricing preview",
     *     description="Despite the legacy route name, this endpoint now returns POS pricing preview with category discount and payment_terms (no credit balance mutation).",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount"},
     *             @OA\Property(property="amount", type="number", format="float", example=150.50)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="POS checkout pricing preview completed",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="POS checkout pricing preview completed"),
     *             @OA\Property(property="data", ref="#/components/schemas/CustomerPricingPreview")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation Errors")
     * )
     */
    public function canPurchase(Request $request, int $id);

    /**
     * @OA\Post(
     *     path="/api/customers/{id}/credit/apply-sale",
     *     operationId="CustomersApplySale",
     *     tags={"Customers"},
     *     security={{"Bearer":{}}},
     *     summary="Resolve sale pricing",
     *     description="Legacy route retained. Returns discounted_amount and payment_terms for sale flow; no credit balance write is performed in current feature logic.",
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
     *     @OA\Response(
     *         response=200,
     *         description="Sale pricing resolved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Sale pricing resolved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/CustomerSalePricing")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation Errors")
     * )
     */
    public function applySale(Request $request, int $id);

    /**
     * @OA\Post(
     *     path="/api/customers/{id}/credit/apply-payment",
     *     operationId="CustomersApplyPayment",
     *     tags={"Customers"},
     *     security={{"Bearer":{}}},
     *     summary="Confirm customer payment term",
     *     description="Legacy route retained. Returns customer payment_terms used by checkout settlement logic.",
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
     *     @OA\Response(
     *         response=200,
     *         description="Customer payment term confirmed successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Customer payment term confirmed successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/CustomerPaymentTermResult")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation Errors")
     * )
     */
    public function applyPayment(Request $request, int $id);

    /**
     * @OA\Get(
     *     path="/api/customers/{id}/stats",
     *     operationId="CustomersStats",
     *     tags={"Customers"},
     *     security={{"Bearer":{}}},
     *     summary="Customer analytics",
     *     description="Returns aggregate customer purchase stats from POS orders.",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Response(
     *         response=200,
     *         description="Customer analytics retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Customer analytics retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/CustomerStats")
     *         )
     *     )
     * )
     */
    public function stats(int $id);

    /**
     * @OA\Get(
     *     path="/api/customers/{id}/timeline",
     *     operationId="CustomersTimeline",
     *     tags={"Customers"},
     *     security={{"Bearer":{}}},
     *     summary="Customer timeline",
     *     description="Returns chronological items from audit and POS order sources.",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="limit", in="query", required=false, description="Timeline size (1..100)", @OA\Schema(type="integer", minimum=1, maximum=100, example=50)),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Customer timeline retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Customer timeline retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/CustomerTimelineItem"))
     *         )
     *     )
     * )
     */
    public function timeline(Request $request, int $id);

    /**
     * @OA\Post(
     *     path="/api/customers/{id}/tags/attach",
     *     operationId="CustomersAttachTags",
     *     tags={"Customers"},
     *     security={{"Bearer":{}}},
     *     summary="Attach tags",
     *     description="Attach one or more tags without removing existing tags.",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"tag_ids"},
     *             @OA\Property(property="tag_ids", type="array", @OA\Items(type="integer"), example={1,2,3})
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Tags attached successfully"),
     *     @OA\Response(response=422, description="Validation Errors")
     * )
     */
    public function attachTags(Request $request, int $id);

    /**
     * @OA\Put(
     *     path="/api/customers/{id}/tags/sync",
     *     operationId="CustomersSyncTags",
     *     tags={"Customers"},
     *     security={{"Bearer":{}}},
     *     summary="Sync tags",
     *     description="Replace all existing tags with provided tag ids.",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"tag_ids"},
     *             @OA\Property(property="tag_ids", type="array", @OA\Items(type="integer"), example={2,4})
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Tags synced successfully"),
     *     @OA\Response(response=422, description="Validation Errors")
     * )
     */
    public function syncTags(Request $request, int $id);

    /**
     * @OA\Delete(
     *     path="/api/customers/{id}/tags/{tagId}",
     *     operationId="CustomersDetachTag",
     *     tags={"Customers"},
     *     security={{"Bearer":{}}},
     *     summary="Detach tag",
     *     description="Detach one tag from customer.",
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer", example=1)),
     *     @OA\Parameter(name="tagId", in="path", required=true, @OA\Schema(type="integer", example=3)),
     *
     *     @OA\Response(response=200, description="Tag detached successfully")
     * )
     */
    public function detachTag(int $id, int $tagId);
}
