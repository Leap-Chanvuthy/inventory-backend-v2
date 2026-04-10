<?php

namespace App\Service;

use App\Helpers\CurrencyPricingHelper;
use App\Helpers\GetCurrentUserHelper;
use App\Helpers\ResponseHelper;
use App\Models\Product;
use App\Models\ProductMovement;
use App\Models\ProductRawMaterial;
use App\Models\ProductReorder;
use App\Models\ReorderProductRawMaterial;
use App\Models\RMStockMovement;
use App\Validations\ProductValidation;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class InternalProductReorder
{
	protected ProductValidation $productValidation;
	protected RawMaterialStockDeductionService $stockDeductionService;
	protected GetCurrentUserHelper $getCurrentUserHelper;

	public function __construct(
		ProductValidation $productValidation,
		RawMaterialStockDeductionService $stockDeductionService,
		GetCurrentUserHelper $getCurrentUserHelper
	) {
		$this->productValidation = $productValidation;
		$this->stockDeductionService = $stockDeductionService;
		$this->getCurrentUserHelper = $getCurrentUserHelper;
	}

	public function reorderInternalManufacturedProduct(Request $request, $productId)
	{
		try {
			$product = Product::findOrFail($productId);

			$requestBomItems = $request->input('raw_materials', []);
			if (empty($requestBomItems)) {
				$request->merge([
					'raw_materials' => $this->getDefaultBomFromProduct($product),
				]);
			}

			$rules = $this->productValidation->createInternalManufacturingMovementRules();
			$validator = Validator::make($request->all(), $rules);
			if ($validator->fails()) {
				return ResponseHelper::validation($validator->errors()->toArray(), 'Validation Error');
			}

			$productTypeValue = ($product->product_type instanceof \BackedEnum)
				? $product->product_type->value
				: (string) $product->product_type;

			if ($productTypeValue !== \App\Enums\ProductTypeEnum::INTERNAL_PRODUCED->value) {
				return ResponseHelper::error(
					'Invalid product type for internal manufacturing reorder',
					422,
					'Product must be an internally produced product to create an internal manufacturing reorder.'
				);
			}

			$request->merge([
				'product_id' => $product->id,
				'direction' => \App\Enums\StockDirectionEnum::IN->value,
				'movement_type' => \App\Enums\ProductStockMovementTypeEnum::RE_ORDER->value,
				'movement_date' => $request->input('movement_date', now()->toDateTimeString()),
			]);

			$request->merge([
				'purchase_unit_price_in_usd' => 0,
				'purchase_total_price_in_usd' => 0,
				'purchase_unit_price_in_riel' => 0,
				'purchase_total_price_in_riel' => 0,
				'exchange_rate_from_usd_to_riel' => 0,
				'exchange_rate_from_riel_to_usd' => null,
			]);

			$currentUserId = $this->getCurrentUserHelper->getUserId();
			$request->merge([
				'created_by' => $currentUserId,
				'last_updated_by' => $currentUserId,
			]);

			$lastMovement = ProductMovement::where('product_id', $product->id)
				->orderBy('movement_date', 'desc')
				->first();

			if ($lastMovement) {
				$request->merge([
					'selling_unit_price_in_usd' => $lastMovement->selling_unit_price_in_usd ?? 0,
					'selling_exchange_rate_from_usd_to_riel' => $lastMovement->selling_exchange_rate_from_usd_to_riel ?? 0,
				]);
			} else {
				$request->merge([
					'selling_unit_price_in_usd' => $request->input('selling_unit_price_in_usd', 0),
					'selling_exchange_rate_from_usd_to_riel' => $request->input('selling_exchange_rate_from_usd_to_riel', 0),
				]);
			}

			CurrencyPricingHelper::fillProductPurchasingCurrencyFields($request);

			$bomItems = $request->input('raw_materials', []);
			$shortfalls = $this->stockDeductionService->validateSufficientStock($bomItems);
			if (!empty($shortfalls)) {
				return ResponseHelper::error('Insufficient raw material stock', 422, $shortfalls);
			}

			$validated = Validator::make($request->all(), $rules)->validate();
			$validated['created_by'] = $validated['created_by'] ?? $this->getCurrentUserHelper->getUserId();
			$validated['last_updated_by'] = $validated['last_updated_by'] ?? $this->getCurrentUserHelper->getUserId();

			$movement = DB::transaction(function () use ($validated, $product, $bomItems) {
				$userId = $this->getCurrentUserHelper->getUserId();
				$movementDate = $validated['movement_date'] ?? now()->toDateTimeString();

				$movement = ProductMovement::create([
					'product_id' => $product->id,
					'direction' => \App\Enums\StockDirectionEnum::IN->value,
					'movement_type' => \App\Enums\ProductStockMovementTypeEnum::RE_ORDER->value,
					'product_status' => \App\Enums\ProductStatusEnum::COMPLETED->value,
					'quantity' => $validated['quantity'],
					'is_sold' => false,
					'movement_date' => $movementDate,
					'note' => $validated['note'] ?? null,
					'created_by' => $validated['created_by'],
					'last_updated_by' => $validated['last_updated_by'],
					'purchase_unit_price_in_usd' => 0,
					'purchase_total_price_in_usd' => 0,
					'exchange_rate_from_usd_to_riel' => 0,
					'purchase_unit_price_in_riel' => 0,
					'purchase_total_price_in_riel' => 0,
					'exchange_rate_from_riel_to_usd' => 0,
					'selling_unit_price_in_usd' => $validated['selling_unit_price_in_usd'] ?? 0,
					'selling_unit_price_in_riel' => $validated['selling_unit_price_in_riel'] ?? 0,
					'selling_exchange_rate_from_usd_to_riel' => $validated['selling_exchange_rate_from_usd_to_riel'] ?? 0,
					'selling_exchange_rate_from_riel_to_usd' => $validated['selling_exchange_rate_from_riel_to_usd'] ?? 0,
				]);

				$productReorder = ProductReorder::create([
					'product_id' => $product->id,
					'product_movement_id' => $movement->id,
					'quantity' => $validated['quantity'],
					'status' => \App\Enums\ProductStatusEnum::COMPLETED->value,
					'is_finalized' => false,
					'created_by' => $validated['created_by'],
					'last_updated_by' => $validated['last_updated_by'],
					'notes' => $validated['note'] ?? null,
				]);

				foreach ($bomItems as $item) {
					ReorderProductRawMaterial::create([
						'product_reorder_id' => $productReorder->id,
						'raw_material_id' => $item['raw_material_id'],
						'quantity' => $item['quantity'],
					]);
				}

				$referenceToken = $this->buildReorderReferenceToken((int) $movement->id);

				$this->stockDeductionService->deductStock(
					$bomItems,
					$product->id,
					$userId,
					$movementDate,
					$referenceToken
				);

				return [
					'movement' => $movement,
					'product_reorder_id' => $productReorder->id,
				];
			});

			return ResponseHelper::success($movement, 'Product reordered (internal manufacturing) successfully', 201);
		} catch (ValidationException $e) {
			return ResponseHelper::validation($e->errors(), 'Validation Error');
		} catch (Exception $e) {
			return ResponseHelper::error($e->getMessage(), 500);
		}
	}

	public function updateReorderInternalManufacturedProduct(Request $request, $productId, $movementId)
	{
		try {
			$product = Product::findOrFail($productId);
			$movement = ProductMovement::findOrFail($movementId);

			$productReorder = ProductReorder::where('product_id', $product->id)
				->where('product_movement_id', $movement->id)
				->first();

			if (!$productReorder) {
				return ResponseHelper::error(
					'Reorder snapshot not found',
					404,
					'This reorder does not have BOM snapshot data. Only reorders created with the new snapshot flow can be updated.'
				);
			}

			if ($productReorder->is_finalized) {
				return ResponseHelper::error('Cannot update finalized reorder', 401, 'The reorder has been finalized and can no longer be updated.');
			}

			$requestBomItems = $request->input('raw_materials', []);
			if (empty($requestBomItems)) {
				$request->merge([
					'raw_materials' => $productReorder->bomItems()
						->get(['raw_material_id', 'quantity'])
						->map(fn($item) => [
							'raw_material_id' => (int) $item->raw_material_id,
							'quantity' => (float) $item->quantity,
						])
						->values()
						->all(),
				]);
			}

			$rules = $this->productValidation->createInternalManufacturingMovementRules();
			$validator = Validator::make($request->all(), $rules);
			if ($validator->fails()) {
				return ResponseHelper::validation($validator->errors()->toArray(), 'Validation Error');
			}

			$productTypeValue = ($product->product_type instanceof \BackedEnum)
				? $product->product_type->value
				: (string) $product->product_type;

			if ($productTypeValue !== \App\Enums\ProductTypeEnum::INTERNAL_PRODUCED->value) {
				return ResponseHelper::error(
					'Invalid product type for internal manufacturing reorder update',
					422,
					'Product must be an internally produced product to update an internal manufacturing reorder.'
				);
			}

			$request->merge([
				'product_id' => $product->id,
				'direction' => \App\Enums\StockDirectionEnum::IN->value,
				'movement_type' => \App\Enums\ProductStockMovementTypeEnum::RE_ORDER->value,
				'movement_date' => $request->input('movement_date', now()->toDateTimeString()),
			]);

			$request->merge([
				'purchase_unit_price_in_usd' => 0,
				'purchase_total_price_in_usd' => 0,
				'purchase_unit_price_in_riel' => 0,
				'purchase_total_price_in_riel' => 0,
				'exchange_rate_from_usd_to_riel' => 0,
				'exchange_rate_from_riel_to_usd' => null,
			]);

			$request->merge([
				'created_by' => $movement->created_by,
				'last_updated_by' => $this->getCurrentUserHelper->getUserId(),
			]);

			if ($movement->is_sold === true) {
				return ResponseHelper::error('Cannot update used stock movement', 401, 'The reordered product has been sold. Data cannot be updated to avoid data inconsistency.');
			}

			$lastMovement = ProductMovement::where('product_id', $product->id)
				->orderBy('movement_date', 'desc')
				->first();

			if ($lastMovement) {
				$request->merge([
					'selling_unit_price_in_usd' => $lastMovement->selling_unit_price_in_usd ?? 0,
					'selling_exchange_rate_from_usd_to_riel' => $lastMovement->selling_exchange_rate_from_usd_to_riel ?? 0,
				]);
			} else {
				$request->merge([
					'selling_unit_price_in_usd' => $request->input('selling_unit_price_in_usd', 0),
					'selling_exchange_rate_from_usd_to_riel' => $request->input('selling_exchange_rate_from_usd_to_riel', 0),
				]);
			}

			CurrencyPricingHelper::fillProductPurchasingCurrencyFields($request);

			$validated = Validator::make($request->all(), $rules)->validate();
			$validated['created_by'] = $validated['created_by'] ?? $movement->created_by ?? $this->getCurrentUserHelper->getUserId();
			$validated['last_updated_by'] = $validated['last_updated_by'] ?? $this->getCurrentUserHelper->getUserId();

			$bomItems = $validated['raw_materials'] ?? [];

			DB::beginTransaction();
			try {
				$referenceToken = $this->buildReorderReferenceToken((int) $movement->id);
				$deletedRawMaterialIds = $this->stockDeductionService->deleteReorderConsumptionMovementsByToken($referenceToken);

				$existingBomRawMaterialIds = $productReorder->bomItems()
					->pluck('raw_material_id')
					->all();

				$productReorder->bomItems()->delete();

				$rebuildIds = array_values(array_unique(array_merge($deletedRawMaterialIds, $existingBomRawMaterialIds)));
				$this->stockDeductionService->rebuildInUsedFlags($rebuildIds);

				$shortfalls = $this->stockDeductionService->validateSufficientStock($bomItems);
				if (!empty($shortfalls)) {
					DB::rollBack();
					return ResponseHelper::error('Insufficient raw material stock', 422, $shortfalls);
				}

				foreach ($bomItems as $item) {
					ReorderProductRawMaterial::create([
						'product_reorder_id' => $productReorder->id,
						'raw_material_id' => $item['raw_material_id'],
						'quantity' => $item['quantity'],
					]);
				}

				$this->stockDeductionService->deductStock(
					$bomItems,
					$product->id,
					(int) $validated['last_updated_by'],
					$validated['movement_date'],
					$referenceToken
				);

				$movement->update([
					'quantity' => $validated['quantity'],
					'direction' => \App\Enums\StockDirectionEnum::IN->value,
					'movement_type' => \App\Enums\ProductStockMovementTypeEnum::RE_ORDER->value,
					'movement_date' => $validated['movement_date'],
					'note' => $validated['note'] ?? null,
					'purchase_unit_price_in_usd' => 0,
					'purchase_total_price_in_usd' => 0,
					'exchange_rate_from_usd_to_riel' => 0,
					'purchase_unit_price_in_riel' => 0,
					'purchase_total_price_in_riel' => 0,
					'exchange_rate_from_riel_to_usd' => 0,
					'selling_unit_price_in_usd' => $validated['selling_unit_price_in_usd'] ?? 0,
					'selling_unit_price_in_riel' => $validated['selling_unit_price_in_riel'] ?? 0,
					'selling_exchange_rate_from_usd_to_riel' => $validated['selling_exchange_rate_from_usd_to_riel'] ?? 0,
					'selling_exchange_rate_from_riel_to_usd' => $validated['selling_exchange_rate_from_riel_to_usd'] ?? 0,
					'last_updated_by' => $validated['last_updated_by'],
				]);

				$productReorder->update([
					'quantity' => $validated['quantity'],
					'status' => $validated['product_status'] ?? $productReorder->status,
					'last_updated_by' => $validated['last_updated_by'],
					'notes' => $validated['note'] ?? null,
				]);

				DB::commit();
				$movement = $movement->fresh();
			} catch (Exception $e) {
				DB::rollBack();
				throw $e;
			}

			return ResponseHelper::success($movement, 'Product reorder (internal manufacturing) updated successfully', 201);
		} catch (ValidationException $e) {
			return ResponseHelper::validation($e->errors(), 'Validation Error');
		} catch (Exception $e) {
			return ResponseHelper::error($e->getMessage(), 500);
		}
	}

	public function getReorderInternalDetail(int $productId, int $movementId)
	{
		try {
			$product = Product::findOrFail($productId);

			$productTypeValue = ($product->product_type instanceof \BackedEnum)
				? $product->product_type->value
				: (string) $product->product_type;

			if ($productTypeValue !== \App\Enums\ProductTypeEnum::INTERNAL_PRODUCED->value) {
				return ResponseHelper::error('Invalid product type for internal manufacturing reorder', 422, 'Product must be an internally produced product.');
			}

			$movement = ProductMovement::findOrFail($movementId);
			$movementTypeValue = is_object($movement->movement_type) ? $movement->movement_type->value : (string) $movement->movement_type;
			if ($movement->product_id !== $product->id || $movementTypeValue !== \App\Enums\ProductStockMovementTypeEnum::RE_ORDER->value) {
				return ResponseHelper::error('Movement not found or not a reorder movement', 404);
			}

			$productReorder = ProductReorder::where('product_id', $product->id)
				->where('product_movement_id', $movement->id)
				->with(['bomItems.rawMaterial', 'createdBy', 'lastUpdatedBy'])
				->first();

			$referenceToken = $this->buildReorderReferenceToken((int) $movement->id);
			$rmMovements = RMStockMovement::where('note', 'like', '%' . $referenceToken . '%')->get();

			return ResponseHelper::success([
				'product' => $product,
				'movement' => $movement,
				'product_reorder' => $productReorder,
				'rm_stock_movements' => $rmMovements,
			]);
		} catch (Exception $e) {
			return ResponseHelper::error($e->getMessage(), 500);
		}
	}

	// Delete internal product reorder movement in case the movement is not used in any sales yet. This is to avoid data inconsistency since the reorder movement can be used as a reference for COGS calculation in sales.
	public function deleteReorderInternalManufacturedProduct(int $productId, int $movementId){
		try {
			$product = Product::findOrFail($productId);
			$movement = ProductMovement::findOrFail($movementId);

			$productTypeValue = ($product->product_type instanceof \BackedEnum)
				? $product->product_type->value
				: (string) $product->product_type;

			if ($productTypeValue !== \App\Enums\ProductTypeEnum::INTERNAL_PRODUCED->value) {
				return ResponseHelper::error('Invalid product type for internal manufacturing reorder deletion', 422, 'Product must be an internally produced product.');
			}

			$movementTypeValue = is_object($movement->movement_type) ? $movement->movement_type->value : (string) $movement->movement_type;
			if ($movement->product_id !== $product->id || $movementTypeValue !== \App\Enums\ProductStockMovementTypeEnum::RE_ORDER->value) {
				return ResponseHelper::error('Movement not found or not a reorder movement', 404);
			}

			if ($movement->is_sold === true) {
				return ResponseHelper::error('Cannot delete used stock movement', 401, 'The reordered product has been sold. Data cannot be deleted to avoid data inconsistency.');
			}

			// Remove dependent reorder snapshot and any RM stock movements created for the reorder
			$productReorder = ProductReorder::where('product_id', $product->id)
				->where('product_movement_id', $movement->id)
				->first();

			if ($productReorder) {
				$referenceToken = $this->buildReorderReferenceToken((int) $movement->id);
				// delete RM stock movements created for this reorder (if any)
				$this->stockDeductionService->deleteReorderConsumptionMovementsByToken($referenceToken);

				// delete BOM snapshot items
				$productReorder->bomItems()->delete();

				// delete the reorder snapshot
				$productReorder->delete();
			}

			$movement->delete();

			return ResponseHelper::success(null, 'Product reorder deleted successfully', 200);
		} catch (Exception $e) {
			return ResponseHelper::error($e->getMessage(), 500);
		}
	}



	private function getDefaultBomFromProduct(Product $product): array
	{
		return ProductRawMaterial::where('product_id', $product->id)
			->get(['raw_material_id', 'quantity'])
			->map(fn($item) => [
				'raw_material_id' => (int) $item->raw_material_id,
				'quantity' => (float) $item->quantity,
			])
			->values()
			->all();
	}

	private function buildReorderReferenceToken(int $movementId): string
	{
		return "REORDER_MOVEMENT_ID:{$movementId}";
	}
}
