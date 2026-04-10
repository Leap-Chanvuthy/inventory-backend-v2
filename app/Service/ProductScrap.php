<?php

namespace App\Service;

use App\Helpers\GetCurrentUserHelper;
use App\Helpers\ResponseHelper;
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

	public function __construct(GetCurrentUserHelper $getCurrentUserHelper, AuditLoggerService $auditLoggerService)
	{
		$this->getCurrentUserHelper = $getCurrentUserHelper;
		$this->auditLoggerService = $auditLoggerService;
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

			$currentQtyInStock = 0;
			$movements = ProductMovement::where('product_id', $product->id)->get();
			foreach ($movements as $m) {
				$qty = (float) ($m->quantity ?? 0);
				$dir = is_object($m->direction) ? $m->direction->value : (string) $m->direction;
				$currentQtyInStock += ($dir === 'OUT') ? (-$qty) : $qty;
			}

			if ($currentQtyInStock < (float) $validated['quantity']) {
				return ResponseHelper::error('Insufficient product stock to scrap', 422, ['available_qty' => $currentQtyInStock]);
			}

			$currentUserId = $this->getCurrentUserHelper->getUserId();
			$movementDate = $validated['movement_date'] ?? now()->toDateTimeString();

			$movement = DB::transaction(function () use ($product, $validated, $currentUserId, $movementDate) {
				return ProductMovement::create([
					'product_id' => $product->id,
					'direction' => \App\Enums\StockDirectionEnum::OUT->value,
					'movement_type' => \App\Enums\ProductStockMovementTypeEnum::SCRAP->value,
					'product_status' => \App\Enums\ProductStatusEnum::COMPLETED->value,
					'quantity' => $validated['quantity'],
					'is_sold' => false,
					'movement_date' => $movementDate,
					'note' => $validated['note'] ?? null,
					'created_by' => $currentUserId,
					'last_updated_by' => $currentUserId,
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
				]);
			});

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

			if ($movement->is_sold === true) {
				return ResponseHelper::error('Cannot update used stock movement', 401, 'The scrap movement has been sold/used. Data cannot be updated to avoid data inconsistency.');
			}

			$currentQtyInStock = 0;
			$movements = ProductMovement::where('product_id', $product->id)->get();
			foreach ($movements as $m) {
				$qty = (float) ($m->quantity ?? 0);
				$dir = is_object($m->direction) ? $m->direction->value : (string) $m->direction;
				$currentQtyInStock += ($dir === 'OUT') ? (-$qty) : $qty;
			}

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
