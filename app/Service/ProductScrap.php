<?php

namespace App\Service;

use App\Helpers\GetCurrentUserHelper;
use App\Helpers\ResponseHelper;
use App\Helpers\UomQuantityGuard;
use App\Models\Product;
use App\Models\ProductMovement;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProductScrap
{
	protected GetCurrentUserHelper $getCurrentUserHelper;
	protected AuditLoggerService $auditLoggerService;
	protected ProductStockAllocationService $productStockAllocationService;

	public function __construct(
		GetCurrentUserHelper $getCurrentUserHelper,
		AuditLoggerService $auditLoggerService,
		ProductStockAllocationService $productStockAllocationService
	)
	{
		$this->getCurrentUserHelper = $getCurrentUserHelper;
		$this->auditLoggerService = $auditLoggerService;
		$this->productStockAllocationService = $productStockAllocationService;
	}

	public function createScrapMovement(Request $request, $productId)
	{
		try {
			$product = Product::findOrFail($productId);

			$rules = [
				'quantity' => 'required|numeric|min:0.0001',
				'movement_date' => 'nullable|date',
				'note' => 'nullable|string',
			];

			$validator = Validator::make($request->all(), $rules);
			if ($validator->fails()) {
				return ResponseHelper::validation($validator->errors()->toArray(), 'Validation Error');
			}

			$validated = $validator->validate();
			UomQuantityGuard::assertQuantityByUomId(
				$validated['quantity'] ?? null,
				(int) $product->base_uom_id,
				'quantity'
			);

			$currentQtyInStock = $this->productStockAllocationService->getAvailableStock((int) $product->id);

			if ($currentQtyInStock < (float) $validated['quantity']) {
				return ResponseHelper::error('Insufficient product stock to scrap', 422, ['available_qty' => $currentQtyInStock]);
			}

			$currentUserId = $this->getCurrentUserHelper->getUserId();
			$movementDate = $validated['movement_date'] ?? now()->toDateTimeString();

			$allocationResult = $this->productStockAllocationService->allocateProductForSale(
				$product,
				(float) $validated['quantity'],
				$currentUserId,
				$movementDate,
				[
					'movement_type' => \App\Enums\ProductStockMovementTypeEnum::SCRAP->value,
					'product_status' => \App\Enums\ProductStatusEnum::COMPLETED->value,
					'note' => $validated['note'] ?? null,
				]
			);

			$movement = $allocationResult['sale_movement'];

			$this->auditLoggerService->logChange(
				'product.scrap.create',
				Product::class,
				(int) $product->id,
				[],
				['movement' => $this->auditLoggerService->snapshotModel($movement)],
				$currentUserId,
				['context' => 'product_scrap_service']
			);

			return ResponseHelper::success($movement, 'Product scrapped successfully', 201);
		} catch (ValidationException $e) {
			return ResponseHelper::validation($e->errors(), 'Validation Error');
		} catch (Exception $e) {
			return ResponseHelper::error($e->getMessage(), 500);
		}
	}

	public function updateScrapMovement(Request $request, $productId, $movementId)
	{
		try {
			$product = Product::findOrFail($productId);
			$movement = ProductMovement::findOrFail($movementId);

			if ($movement->product_id !== $product->id) {
				return ResponseHelper::error('Movement does not belong to product', 422);
			}

			$movementTypeValue = (is_object($movement->movement_type) ? $movement->movement_type->value : (string) $movement->movement_type);
			if ($movementTypeValue !== \App\Enums\ProductStockMovementTypeEnum::SCRAP->value) {
				return ResponseHelper::error('Movement is not a scrap movement', 422);
			}

			$rules = [
				'quantity' => 'required|numeric|min:0.0001',
				'movement_date' => 'nullable|date',
				'note' => 'nullable|string',
			];

			$validator = Validator::make($request->all(), $rules);
			if ($validator->fails()) {
				return ResponseHelper::validation($validator->errors()->toArray(), 'Validation Error');
			}

			$validated = $validator->validate();
			UomQuantityGuard::assertQuantityByUomId(
				$validated['quantity'] ?? null,
				(int) $product->base_uom_id,
				'quantity'
			);

			if ($movement->saleAllocations()->exists()) {
				return ResponseHelper::error(
					'Cannot update allocated stock movement',
					422,
					'This scrap movement already consumed stock batches and cannot be edited. Create a new adjustment movement instead.'
				);
			}

			$currentQtyInStock = $this->productStockAllocationService->getAvailableStock((int) $product->id);

			$availableQty = $currentQtyInStock + (float) ($movement->quantity ?? 0);
			if ($availableQty < (float) $validated['quantity']) {
				return ResponseHelper::error('Insufficient product stock to update scrap quantity', 422, ['available_qty' => $availableQty]);
			}

			$currentUserId = $this->getCurrentUserHelper->getUserId();
			$movementDate = $validated['movement_date'] ?? now()->toDateTimeString();

			$oldSnapshot = $this->auditLoggerService->snapshotModel($movement);

			$movement = DB::transaction(function () use ($movement, $validated, $currentUserId, $movementDate) {
				$movement->update([
					'quantity' => $validated['quantity'],
					'remaining_quantity' => 0,
					'direction' => \App\Enums\StockDirectionEnum::OUT->value,
					'movement_type' => \App\Enums\ProductStockMovementTypeEnum::SCRAP->value,
					'movement_date' => $movementDate,
					'note' => $validated['note'] ?? null,
					'purchase_unit_price_in_usd' => 0,
					'purchase_total_price_in_usd' => 0,
					'exchange_rate_from_usd_to_riel' => 0,
					'purchase_unit_price_in_riel' => 0,
					'purchase_total_price_in_riel' => 0,
					'exchange_rate_from_riel_to_usd' => 0,
					'selling_unit_price_in_usd' => 0,
					'selling_unit_price_in_riel' => 0,
					'selling_exchange_rate_from_usd_to_riel' => 0,
					'selling_exchange_rate_from_riel_to_usd' => 0,
					'last_updated_by' => $currentUserId,
				]);

				return $movement->fresh();
			});

			$this->auditLoggerService->logDiff(
				'product.scrap.update',
				Product::class,
				(int) $product->id,
				$oldSnapshot,
				$this->auditLoggerService->snapshotModel($movement),
				$currentUserId,
				['context' => 'product_scrap_service']
			);

			return ResponseHelper::success($movement, 'Product scrap updated successfully', 201);
		} catch (ValidationException $e) {
			return ResponseHelper::validation($e->errors(), 'Validation Error');
		} catch (Exception $e) {
			return ResponseHelper::error($e->getMessage(), 500);
		}
	}

	public function getScrapDetail(int $productId, int $movementId)
	{
		try {
			$product = Product::findOrFail($productId);
			$movement = ProductMovement::findOrFail($movementId);

			if ($movement->product_id !== $product->id) {
				return ResponseHelper::error('Movement does not belong to product', 404);
			}

			$movementTypeValue = is_object($movement->movement_type) ? $movement->movement_type->value : (string) $movement->movement_type;
			if ($movementTypeValue !== \App\Enums\ProductStockMovementTypeEnum::SCRAP->value) {
				return ResponseHelper::error('Movement is not a scrap movement', 404);
			}

			return ResponseHelper::success([
				'product' => $product,
				'movement' => $movement,
			]);
		} catch (Exception $e) {
			return ResponseHelper::error($e->getMessage(), 500);
		}
	}
}
