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
	protected AuditLoggerService $auditLoggerService;

	public function __construct(
		ProductValidation $productValidation,
		RawMaterialStockDeductionService $stockDeductionService,
		GetCurrentUserHelper $getCurrentUserHelper,
		AuditLoggerService $auditLoggerService
	) {
		$this->productValidation = $productValidation;
		$this->stockDeductionService = $stockDeductionService;
		$this->getCurrentUserHelper = $getCurrentUserHelper;
		$this->auditLoggerService = $auditLoggerService;
	}

	public function reorderInternalManufacturedProduct(Request $request, $productId)
	{
		try {
			$product = Product::findOrFail($productId);

			// BOM is immutable for reorder flow: always use product formula.
			$defaultBom = $this->getDefaultBomFromProduct($product);
			if (empty($defaultBom)) {
				return ResponseHelper::error(
					'Product BOM not found',
					422,
					'This product has no BOM formula to run internal production reorder.'
				);
			}
			$incomingBom = $request->input('raw_materials');
			if (is_array($incomingBom) && !empty($incomingBom) && $this->isDifferentBom($incomingBom, $defaultBom)) {
				return ResponseHelper::error(
					'BOM cannot be changed in reorder',
					422,
					'Reorder must use the original product BOM formula.'
				);
			}
			$request->merge(['raw_materials' => $defaultBom]);

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

			$request->merge([
				// Keep incoming financial values from request; fallback only when missing.
				'selling_unit_price_in_usd' => $request->input(
					'selling_unit_price_in_usd',
					$lastMovement->selling_unit_price_in_usd ?? 0
				),
				'selling_exchange_rate_from_usd_to_riel' => $request->input(
					'selling_exchange_rate_from_usd_to_riel',
					$lastMovement->selling_exchange_rate_from_usd_to_riel ?? 0
				),
			]);

			CurrencyPricingHelper::fillProductPurchasingCurrencyFields($request);

			$bomItems = $request->input('raw_materials', []);
			$movementDate = $request->input('movement_date', now()->toDateTimeString());
			$shortfalls = $this->stockDeductionService->validateSufficientStock($bomItems, $movementDate);
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

			$this->auditLoggerService->logChange(
				'product.reorder.internal.create',
				Product::class,
				(int) $product->id,
				[],
				[
					'movement' => $this->auditLoggerService->snapshotModel($movement['movement'] ?? null),
					'bom' => $bomItems,
				],
				(int) ($validated['last_updated_by'] ?? $this->getCurrentUserHelper->getUserId()),
				['context' => 'internal_reorder_service']
			);

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

			// BOM is immutable for reorder flow: always use stored reorder snapshot.
			$lockedBom = $productReorder->bomItems()
				->get(['raw_material_id', 'quantity'])
				->map(fn($item) => [
					'raw_material_id' => (int) $item->raw_material_id,
					'quantity' => (float) $item->quantity,
				])
				->values()
				->all();
			if (empty($lockedBom)) {
				return ResponseHelper::error(
					'Reorder BOM not found',
					422,
					'This reorder has no BOM snapshot and cannot be updated.'
				);
			}
			$incomingBom = $request->input('raw_materials');
			if (is_array($incomingBom) && !empty($incomingBom) && $this->isDifferentBom($incomingBom, $lockedBom)) {
				return ResponseHelper::error(
					'BOM cannot be changed in reorder',
					422,
					'Reorder must keep the original BOM formula.'
				);
			}
			$request->merge(['raw_materials' => $lockedBom]);

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
				$incomingQty = $request->input('quantity');
				$currentQty = (float) $movement->quantity;

				if ($incomingQty !== null && $incomingQty !== '' && (float) $incomingQty !== $currentQty) {
					return ResponseHelper::error(
						'Cannot update sold movement quantity',
						422,
						'The reordered product has been sold. Quantity cannot be updated to avoid data inconsistency.'
					);
				}

				// Allow updating other fields, but force quantity to current sold quantity.
				$request->merge(['quantity' => $currentQty]);
			}

			$lastMovement = ProductMovement::where('product_id', $product->id)
				->orderBy('movement_date', 'desc')
				->first();

			$request->merge([
				// Keep incoming financial values from request; fallback only when missing.
				'selling_unit_price_in_usd' => $request->input(
					'selling_unit_price_in_usd',
					$lastMovement->selling_unit_price_in_usd ?? 0
				),
				'selling_exchange_rate_from_usd_to_riel' => $request->input(
					'selling_exchange_rate_from_usd_to_riel',
					$lastMovement->selling_exchange_rate_from_usd_to_riel ?? 0
				),
			]);

			CurrencyPricingHelper::fillProductPurchasingCurrencyFields($request);

			$validated = Validator::make($request->all(), $rules)->validate();
			$validated['created_by'] = $validated['created_by'] ?? $movement->created_by ?? $this->getCurrentUserHelper->getUserId();
			$validated['last_updated_by'] = $validated['last_updated_by'] ?? $this->getCurrentUserHelper->getUserId();

			$oldSnapshot = [
				'movement' => $this->auditLoggerService->snapshotModel($movement),
				'bom' => $productReorder->bomItems()
					->get(['raw_material_id', 'quantity'])
					->map(fn($item) => [
						'raw_material_id' => (int) $item->raw_material_id,
						'quantity' => (float) $item->quantity,
					])
					->values()
					->all(),
			];

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

				$shortfalls = $this->stockDeductionService->validateSufficientStock(
					$bomItems,
					$validated['movement_date'] ?? null
				);
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

			$newSnapshot = [
				'movement' => $this->auditLoggerService->snapshotModel($movement),
				'bom' => $productReorder->bomItems()
					->get(['raw_material_id', 'quantity'])
					->map(fn($item) => [
						'raw_material_id' => (int) $item->raw_material_id,
						'quantity' => (float) $item->quantity,
					])
					->values()
					->all(),
			];

			$this->auditLoggerService->logDiff(
				'product.reorder.internal.update',
				Product::class,
				(int) $product->id,
				$oldSnapshot,
				$newSnapshot,
				(int) ($validated['last_updated_by'] ?? $this->getCurrentUserHelper->getUserId()),
				['context' => 'internal_reorder_service']
			);

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

			$oldSnapshot = $this->auditLoggerService->snapshotModel($movement);
			$movement->delete();

			$this->auditLoggerService->logChange(
				'product.reorder.internal.delete',
				Product::class,
				(int) $product->id,
				$oldSnapshot,
				[],
				$this->getCurrentUserHelper->getUserId(),
				['context' => 'internal_reorder_service']
			);

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

	private function isDifferentBom(array $incomingBom, array $expectedBom): bool
	{
		$normalize = function (array $items): array {
			return collect($items)
				->map(function ($item) {
					return [
						'raw_material_id' => (int) ($item['raw_material_id'] ?? 0),
						'quantity' => round((float) ($item['quantity'] ?? 0), 4),
					];
				})
				->sortBy('raw_material_id')
				->values()
				->all();
		};

		return $normalize($incomingBom) !== $normalize($expectedBom);
	}
}
