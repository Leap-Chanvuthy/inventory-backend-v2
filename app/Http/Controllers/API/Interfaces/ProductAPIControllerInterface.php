<?php

namespace App\Http\Controllers\API\Interfaces;

/**
 * @OA\Tag(name="Products - Core", description="Product listing and detail APIs")
 * @OA\Tag(name="Products - External Purchase", description="Create/update initial external purchased stock")
 * @OA\Tag(name="Products - Internal Manufacturing", description="Create/update initial internally manufactured stock")
 * @OA\Tag(name="Products - Reorder External", description="External purchase reorder create/update/detail")
 * @OA\Tag(name="Products - Reorder Internal", description="Internal manufacturing reorder create/update/detail")
 * @OA\Tag(name="Products - Scrap", description="Scrap movement create/update")
 */
interface ProductAPIControllerInterface
{
    /**
     * @OA\Get(
     *     path="/api/products",
     *     tags={"Products - Core"},
     *     security={{"Bearer":{}}},
     *     summary="Get all products",
     *     @OA\Response(response=200, description="Products retrieved successfully"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function index();

    /**
     * @OA\Get(
     *     path="/api/products/{id}",
     *     tags={"Products - Core"},
     *     security={{"Bearer":{}}},
     *     summary="Get product detail",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Product retrieved successfully"),
     *     @OA\Response(response=404, description="Product not found"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function show($id);

    /**
     * @OA\Post(
     *     path="/api/products/create/external-purchase",
     *     tags={"Products - External Purchase"},
     *     security={{"Bearer":{}}},
     *     summary="Create externally purchased product",
     *     @OA\Response(response=201, description="Created successfully"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function storeExternalPurchase();

    /**
     * @OA\Patch(
     *     path="/api/products/{id}/update/external-purchase",
     *     tags={"Products - External Purchase"},
     *     security={{"Bearer":{}}},
     *     summary="Update initial external purchased movement",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=201, description="Updated successfully"),
     *     @OA\Response(response=401, description="Cannot update used stock movement"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function updateExternalPurchase($id);

    /**
     * @OA\Post(
     *     path="/api/products/create/internal-manufacturing",
     *     tags={"Products - Internal Manufacturing"},
     *     security={{"Bearer":{}}},
     *     summary="Create internally manufactured product",
     *     @OA\Response(response=201, description="Created successfully"),
     *     @OA\Response(response=422, description="Validation error or insufficient stock"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function storeInternalManufacturing();

    /**
     * @OA\Patch(
     *     path="/api/products/{id}/update/internal-manufacturing",
     *     tags={"Products - Internal Manufacturing"},
     *     security={{"Bearer":{}}},
     *     summary="Update initial internally manufactured movement",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=201, description="Updated successfully"),
     *     @OA\Response(response=401, description="Cannot update used stock movement"),
     *     @OA\Response(response=422, description="Validation error or insufficient stock"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function updateInternalManufacturing($id);

    /**
     * @OA\Post(
     *     path="/api/products/{id}/reorder/external-purchase",
     *     tags={"Products - Reorder External"},
     *     security={{"Bearer":{}}},
     *     summary="Create external purchase reorder",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=201, description="Reorder created successfully"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function reorderExternalPurchase($id);

    /**
     * @OA\Patch(
     *     path="/api/products/{productId}/reorder/external-purchase",
     *     tags={"Products - Reorder External"},
     *     security={{"Bearer":{}}},
     *     summary="Update external purchase reorder",
     *     @OA\Parameter(name="productId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="movementId", in="query", required=true, @OA\Schema(type="integer"), description="Reorder movement id"),
     *     @OA\Response(response=201, description="Reorder updated successfully"),
     *     @OA\Response(response=401, description="Cannot update used stock movement"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function updateReorderExternalPurchase($productId);

    /**
     * @OA\Get(
     *     path="/api/products/{productId}/reorder/external-purchase/{movementId}",
     *     tags={"Products - Reorder External"},
     *     security={{"Bearer":{}}},
     *     summary="Get external purchase reorder detail",
     *     @OA\Parameter(name="productId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="movementId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Reorder detail retrieved successfully"),
     *     @OA\Response(response=404, description="Movement not found or invalid for product"),
     *     @OA\Response(response=422, description="Invalid product type"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function getReorderExternalPurchase($productId, $movementId);

    /**
     * @OA\Post(
     *     path="/api/products/{id}/reorder/internal-manufacturing",
     *     tags={"Products - Reorder Internal"},
     *     security={{"Bearer":{}}},
     *     summary="Create internal manufacturing reorder",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=201, description="Reorder created successfully"),
     *     @OA\Response(response=422, description="Validation error or insufficient raw material stock"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function reorderInternalManufacturing($id);

    /**
     * @OA\Patch(
     *     path="/api/products/{productId}/reorder/internal-manufacturing",
     *     tags={"Products - Reorder Internal"},
     *     security={{"Bearer":{}}},
     *     summary="Update internal manufacturing reorder",
     *     @OA\Parameter(name="productId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="movementId", in="query", required=true, @OA\Schema(type="integer"), description="Reorder movement id"),
     *     @OA\Response(response=201, description="Reorder updated successfully"),
     *     @OA\Response(response=401, description="Cannot update used stock movement"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function updateReorderInternalManufacturing($productId);

    /**
     * @OA\Get(
     *     path="/api/products/{productId}/reorder/internal-manufacturing/{movementId}",
     *     tags={"Products - Reorder Internal"},
     *     security={{"Bearer":{}}},
     *     summary="Get internal manufacturing reorder detail",
     *     @OA\Parameter(name="productId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="movementId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Reorder detail retrieved successfully"),
     *     @OA\Response(response=404, description="Movement not found or invalid for product"),
     *     @OA\Response(response=422, description="Invalid product type"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function getReorderInternalManufacturing($productId, $movementId);

    /**
     * @OA\Post(
     *     path="/api/products/{id}/scrap",
     *     tags={"Products - Scrap"},
     *     security={{"Bearer":{}}},
     *     summary="Create product scrap movement",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=201, description="Product scrapped successfully"),
     *     @OA\Response(response=422, description="Validation error or insufficient stock"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function createScrap($id);


    /**
     * @OA\Get(
     *     path="/api/products/{productId}/scrap/{movementId}",
     *     tags={"Products - Scrap"},
     *     security={{"Bearer":{}}},
     *     summary="Get product scrap movement detail",
     *     description="Retrieves scrap movement detail. Validates that the movement belongs to the product and that the movement type is SCRAP.",
     *     @OA\Parameter(name="productId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="movementId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Scrap movement detail retrieved successfully"),
     *     @OA\Response(response=404, description="Movement not found or not a scrap movement"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function getScrap($productId, $movementId);

    /**
     * @OA\Patch(
     *     path="/api/products/{productId}/scrap/{movementId}",
     *     tags={"Products - Scrap"},
     *     security={{"Bearer":{}}},
     *     summary="Update product scrap movement",
     *     @OA\Parameter(name="productId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="movementId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=201, description="Product scrap updated successfully"),
     *     @OA\Response(response=401, description="Cannot update used stock movement"),
     *     @OA\Response(response=422, description="Validation error or insufficient stock"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function updateScrap($productId, $movementId);
}
