<?php

namespace App\Swagger;

/**
 * @OA\Tag(name="Products - Stock Lots", description="Product stock batch hierarchy, scrap, BOM and image APIs")
 * @OA\Tag(name="Raw Materials - Stock Lots", description="Raw material stock batch hierarchy, allocation preview and scrap APIs")
 *
 * @OA\Schema(
 *   schema="ProductStockLotChild",
 *   type="object",
 *   @OA\Property(property="id", type="integer", example=501),
 *   @OA\Property(property="type", type="string", example="SALE_ORDER"),
 *   @OA\Property(property="reference", type="string", nullable=true, example="SO-0001"),
 *   @OA\Property(property="quantity", type="number", format="float", example=100),
 *   @OA\Property(property="date", type="string", format="date-time", nullable=true),
 *   @OA\Property(property="unit_price", type="number", format="float", nullable=true, example=6.0),
 *   @OA\Property(property="total", type="number", format="float", nullable=true, example=600.0),
 *   @OA\Property(property="reason", type="string", nullable=true, example="Damaged packaging")
 * )
 *
 * @OA\Schema(
 *   schema="ProductStockLotHierarchy",
 *   type="object",
 *   @OA\Property(property="id", type="integer", example=101),
 *   @OA\Property(property="batch_code", type="string", example="PM-101"),
 *   @OA\Property(property="movement_type", type="string", example="RE_ORDER"),
 *   @OA\Property(property="direction", type="string", example="IN"),
 *   @OA\Property(property="movement_date", type="string", format="date-time", nullable=true),
 *   @OA\Property(property="expiry_date", type="string", format="date", nullable=true, example="2026-12-31"),
 *   @OA\Property(property="is_expired", type="boolean", example=false),
 *   @OA\Property(property="days_until_expiry", type="integer", nullable=true, example=220),
 *   @OA\Property(property="quantity", type="number", format="float", example=1000),
 *   @OA\Property(property="remaining_quantity", type="number", format="float", example=820),
 *   @OA\Property(property="sold_quantity", type="number", format="float", example=150),
 *   @OA\Property(property="scrapped_quantity", type="number", format="float", example=30),
 *   @OA\Property(property="adjusted_out_quantity", type="number", format="float", example=0),
 *   @OA\Property(property="available_quantity", type="number", format="float", example=820),
 *   @OA\Property(property="selling_unit_price_in_usd", type="number", format="float", example=6),
 *   @OA\Property(property="status", type="string", example="PARTIALLY_USED"),
 *   @OA\Property(property="can_sale", type="boolean", example=true),
 *   @OA\Property(property="can_scrap", type="boolean", example=true),
 *   @OA\Property(property="disabled_reason", type="string", nullable=true),
 *   @OA\Property(property="children", type="array", @OA\Items(ref="#/components/schemas/ProductStockLotChild"))
 * )
 *
 * @OA\Schema(
 *   schema="RawMaterialStockLotHierarchy",
 *   type="object",
 *   @OA\Property(property="id", type="integer", example=701),
 *   @OA\Property(property="batch_code", type="string", example="RM-701"),
 *   @OA\Property(property="movement_type", type="string", example="PURCHASE"),
 *   @OA\Property(property="movement_date", type="string", format="date-time", nullable=true),
 *   @OA\Property(property="expiry_date", type="string", format="date", nullable=true, example="2026-09-20"),
 *   @OA\Property(property="is_expired", type="boolean", example=false),
 *   @OA\Property(property="quantity", type="number", format="float", example=500),
 *   @OA\Property(property="remaining_quantity", type="number", format="float", example=120),
 *   @OA\Property(property="used_in_production_quantity", type="number", format="float", example=340),
 *   @OA\Property(property="scrapped_quantity", type="number", format="float", example=40),
 *   @OA\Property(property="available_quantity", type="number", format="float", example=120),
 *   @OA\Property(property="unit_cost_usd", type="number", format="float", example=0.3),
 *   @OA\Property(property="status", type="string", example="PARTIALLY_USED"),
 *   @OA\Property(property="can_use_for_production", type="boolean", example=true),
 *   @OA\Property(property="can_scrap", type="boolean", example=true)
 * )
 *
 * @OA\Get(
 *   path="/api/products/{id}/scrap-eligible-stock-lots",
 *   tags={"Products - Stock Lots"},
 *   security={{"Bearer":{}}},
 *   summary="Get product stock batches eligible for scrap",
 *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *   @OA\Parameter(name="include_disabled", in="query", @OA\Schema(type="boolean"), description="Include disabled lots and reasons"),
 *   @OA\Response(response=200, description="Eligible stock lots retrieved")
 * )
 *
 * @OA\Post(
 *   path="/api/products/{id}/scraps",
 *   tags={"Products - Stock Lots"},
 *   security={{"Bearer":{}}},
 *   summary="Create product scrap from a selected stock batch",
 *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *   @OA\RequestBody(
 *     required=true,
 *     @OA\JsonContent(
 *       required={"source_movement_id","quantity"},
 *       @OA\Property(property="source_movement_id", type="integer", example=101),
 *       @OA\Property(property="quantity", type="number", format="float", example=5),
 *       @OA\Property(property="movement_date", type="string", format="date-time", nullable=true),
 *       @OA\Property(property="reason", type="string", nullable=true, example="Damaged packaging"),
 *       @OA\Property(property="note", type="string", nullable=true, example="Manual scrap by QA")
 *     )
 *   ),
 *   @OA\Response(response=201, description="Scrap created successfully"),
 *   @OA\Response(response=422, description="Validation error")
 * )
 *
 * @OA\Get(
 *   path="/api/products/{id}/bom-summary",
 *   tags={"Products - Stock Lots"},
 *   security={{"Bearer":{}}},
 *   summary="Get internal product BOM summary",
 *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *   @OA\Response(response=200, description="BOM summary retrieved")
 * )
 *
 * @OA\Get(
 *   path="/api/products/{productId}/movements/{movementId}/bom-summary",
 *   tags={"Products - Stock Lots"},
 *   security={{"Bearer":{}}},
 *   summary="Get internal product BOM summary by production movement",
 *   @OA\Parameter(name="productId", in="path", required=true, @OA\Schema(type="integer")),
 *   @OA\Parameter(name="movementId", in="path", required=true, @OA\Schema(type="integer")),
 *   @OA\Response(response=200, description="Movement BOM summary retrieved")
 * )
 *
 * @OA\Post(
 *   path="/api/products/{id}/images",
 *   tags={"Products - Stock Lots"},
 *   security={{"Bearer":{}}},
 *   summary="Upload product images",
 *   @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
 *   @OA\RequestBody(
 *     required=true,
 *     @OA\MediaType(
 *       mediaType="multipart/form-data",
 *       @OA\Schema(
 *         required={"images"},
 *         @OA\Property(property="images[]", type="array", @OA\Items(type="string", format="binary"))
 *       )
 *     )
 *   ),
 *   @OA\Response(response=201, description="Images uploaded")
 * )
 *
 * @OA\Delete(
 *   path="/api/products/{productId}/images/{imageId}",
 *   tags={"Products - Stock Lots"},
 *   security={{"Bearer":{}}},
 *   summary="Delete a product image",
 *   @OA\Parameter(name="productId", in="path", required=true, @OA\Schema(type="integer")),
 *   @OA\Parameter(name="imageId", in="path", required=true, @OA\Schema(type="integer")),
 *   @OA\Response(response=200, description="Image deleted")
 * )
 *
 * @OA\Patch(
 *   path="/api/products/{productId}/images/{imageId}/primary",
 *   tags={"Products - Stock Lots"},
 *   security={{"Bearer":{}}},
 *   summary="Set a primary product image",
 *   @OA\Parameter(name="productId", in="path", required=true, @OA\Schema(type="integer")),
 *   @OA\Parameter(name="imageId", in="path", required=true, @OA\Schema(type="integer")),
 *   @OA\Response(response=200, description="Primary image updated")
 * )
 *
 * @OA\Get(
 *   path="/api/raw-materials/{rawMaterialId}/stock-lots",
 *   tags={"Raw Materials - Stock Lots"},
 *   security={{"Bearer":{}}},
 *   summary="Get raw material stock batch hierarchy",
 *   @OA\Parameter(name="rawMaterialId", in="path", required=true, @OA\Schema(type="integer")),
 *   @OA\Response(response=200, description="Stock lots retrieved")
 * )
 *
 * @OA\Get(
 *   path="/api/raw-materials/{rawMaterialId}/scrap-eligible-stock-lots",
 *   tags={"Raw Materials - Stock Lots"},
 *   security={{"Bearer":{}}},
 *   summary="Get raw material stock batches eligible for scrap",
 *   @OA\Parameter(name="rawMaterialId", in="path", required=true, @OA\Schema(type="integer")),
 *   @OA\Response(response=200, description="Eligible stock lots retrieved")
 * )
 *
 * @OA\Post(
 *   path="/api/raw-materials/{rawMaterialId}/production-allocation-preview",
 *   tags={"Raw Materials - Stock Lots"},
 *   security={{"Bearer":{}}},
 *   summary="Preview raw material FIFO/LIFO allocation",
 *   @OA\Parameter(name="rawMaterialId", in="path", required=true, @OA\Schema(type="integer")),
 *   @OA\RequestBody(
 *     required=true,
 *     @OA\JsonContent(
 *       required={"quantity"},
 *       @OA\Property(property="quantity", type="number", format="float", example=150)
 *     )
 *   ),
 *   @OA\Response(response=200, description="Allocation preview generated")
 * )
 *
 * @OA\Post(
 *   path="/api/raw-materials/{rawMaterialId}/scraps",
 *   tags={"Raw Materials - Stock Lots"},
 *   security={{"Bearer":{}}},
 *   summary="Create raw material scrap from a selected stock batch",
 *   @OA\Parameter(name="rawMaterialId", in="path", required=true, @OA\Schema(type="integer")),
 *   @OA\RequestBody(
 *     required=true,
 *     @OA\JsonContent(
 *       required={"source_movement_id","quantity"},
 *       @OA\Property(property="source_movement_id", type="integer", example=701),
 *       @OA\Property(property="quantity", type="number", format="float", example=12),
 *       @OA\Property(property="movement_date", type="string", format="date-time", nullable=true),
 *       @OA\Property(property="reason", type="string", nullable=true, example="Damaged bag"),
 *       @OA\Property(property="note", type="string", nullable=true)
 *     )
 *   ),
 *   @OA\Response(response=201, description="Scrap created successfully"),
 *   @OA\Response(response=422, description="Validation error")
 * )
 */
class InventoryStockLotSwagger
{
}
