<?php

namespace App\Http\Controllers\API\Interfaces;

/**
 * @OA\Tag(
 *     name="Company Information",
 *     description="API Endpoints for managing company information"
 * )
 */

interface CompanyInfoAPIControllerInterface
{
    /**
 * @OA\Get(
 *     path="/api/company/info",
 *     security={{"Bearer":{}}},
 *     tags={"Company"},
 *     summary="Get company information",
 *     description="Retrieve company information including general details and banking information.",
 *     @OA\Response(
 *         response=200,
 *         description="Company information retrieved successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="boolean", example=true),
 *             @OA\Property(property="message", type="string", example="Company information retrieved successfully"),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="id", type="integer", example=1),
 *                 @OA\Property(property="company_name", type="string", example="Toeuk Somrang Company"),
 *                 @OA\Property(property="email", type="string", example="info@company.com"),
 *                 @OA\Property(property="phone_number", type="string", example="012345678"),
 *                 @OA\Property(property="contact_person", type="string", example="John Doe"),
 *                 @OA\Property(property="industry_type", type="string", example="Technology"),
 *                 @OA\Property(property="website_url", type="string", example="https://company.com"),
 *                 @OA\Property(property="date_established", type="string", example="2020-01-01"),
 *                 @OA\Property(property="vat_number", type="string", example="VAT123456"),
 *                 @OA\Property(property="description", type="string", example="Company description here"),
 *                 @OA\Property(property="company_logo", type="string", example="https://cdn.example.com/logo.png"),
 *                 
 *                 @OA\Property(
 *                     property="banking_infos",
 *                     type="array",
 *                     @OA\Items(
 *                         type="object",
 *                         @OA\Property(property="id", type="integer", example=1),
 *                         @OA\Property(property="bank_name", type="string", example="ABA"),
 *                         @OA\Property(property="payment_link", type="string", example="https://aba.com.kh/payment"),
 *                         @OA\Property(property="bank_account_holder_name", type="string", example="Toeuk Somrang Company"),
 *                         @OA\Property(property="bank_account_number", type="string", example="123456789"),
 *                         @OA\Property(property="khqr_code", type="string", example="https://cdn.example.com/qrcode.png"),
 *                         @OA\Property(property="is_default", type="boolean", example=true)
 *                     )
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="Failed to retrieve company information",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Failed to retrieve company information"),
 *             @OA\Property(
 *                 property="errors",
 *                 type="object",
 *                 @OA\Property(property="exception", type="string", example="Database connection error")
 *             )
 *         )
 *     )
 * )
 */
public function getCompanyInfo();


    /**
     * @OA\Post(
     *     path="/api/company/general-info",
     *     summary="Update general company information",
     *     tags={"Company"},
     *     security={{"Bearer":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"company_name","email","phone_number","contact_person","industry_type"},
     *                 @OA\Property(property="company_name", type="string", example="Toeuk Somrang Company"),
     *                 @OA\Property(property="email", type="string", format="email", example="info@company.com"),
     *                 @OA\Property(property="phone_number", type="string", example="012345678"),
     *                 @OA\Property(property="contact_person", type="string", example="John Doe"),
     *                 @OA\Property(property="industry_type", type="string", example="Technology"),
     *                 @OA\Property(property="website_url", type="string", example="https://company.com"),
     *                 @OA\Property(property="date_established", type="string", format="date", example="2020-01-01"),
     *                 @OA\Property(property="vat_number", type="string", example="VAT123456"),
     *                 @OA\Property(property="description", type="string", example="Company description here"),
     *                 @OA\Property(property="company_logo", type="string", format="binary", description="Upload company logo")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="General info updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="General information updated successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function updateGeneral();

    /**
     * @OA\Post(
     *     path="/api/company/address-info",
     *     summary="Update company address information",
     *     tags={"Company"},
     *    security={{"Bearer":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"full_address"},
     *             @OA\Property(property="full_address", type="string", example="123 Street Name, Commune, City"),
     *             @OA\Property(property="house_number", type="string", example="12"),
     *             @OA\Property(property="street", type="string", example="Street Name"),
     *             @OA\Property(property="commune", type="string", example="Commune Name"),
     *             @OA\Property(property="district", type="string", example="District Name"),
     *             @OA\Property(property="city", type="string", example="Phnom Penh")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Address info updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Address information updated successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function updateAddress();

    /**
     * @OA\Post(
     *     path="/api/company/telegram-info",
     *     summary="Update Telegram notification chat ID",
     *    security={{"Bearer":{}}},
     *     tags={"Company"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"type","chat_id"},
     *             @OA\Property(property="type", type="string", enum={"inventory","sale","purchase"}, example="inventory"),
     *             @OA\Property(property="chat_id", type="string", example="1234567890")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Telegram chat ID updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Inventory chat ID updated"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function updateTelegram();

    /**
     * @OA\Post(
     *     path="/api/company/setup-payment",
     *     summary="Setup company payment method",
     *     security={{"Bearer":{}}},
     *     tags={"Company"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"bank_name","payment_link","bank_account_holder_name","bank_account_number","set_as_default"},
     *                 @OA\Property(property="bank_name", type="string", enum={"ABA","ACLEDA","WING","BAKONG"}, example="ABA"),
     *                 @OA\Property(property="payment_link", type="string", example="https://aba.com.kh/payment-link"),
     *                 @OA\Property(property="bank_account_holder_name", type="string", example="Toeuk Somrang Company"),
     *                 @OA\Property(property="bank_account_number", type="string", example="1234567890"),
     *                 @OA\Property(property="khqr_code", type="string", format="binary", description="Upload QR code image"),
     *                 @OA\Property(property="set_as_default", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Company payment setup successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Company payment setup successfully."),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function setupPayment();
}
