<?php

namespace App\Service;

use App\Helpers\CurrencyPricingHelper;
use App\Helpers\GetCurrentUserHelper;
use App\Helpers\ResponseHelper;
use App\Models\Product;
use App\Models\ProductMovement;
use App\Models\ProductReorder;
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
	protected ManufacturingService $manufacturingService;
	protected GetCurrentUserHelper $getCurrentUserHelper;
	protected AuditLoggerService $auditLoggerService;

	public function __construct(
		ProductValidation $productValidation,
		ManufacturingService $manufacturingService,
		GetCurrentUserHelper $getCurrentUserHelper,
		AuditLoggerService $auditLoggerService
	) {
		$this->productValidation = $productValidation;
		$this->manufacturingService = $manufacturingService;
		$this->getCurrentUserHelper = $getCurrentUserHelper;
		$this->auditLoggerService = $auditLoggerService;
	}

	public function reorderInternalManufacturedProduct(Request $request, $productId)
	{
		try {
			$product = Product::findOrFail($productId);

				// Reorder keeps BOM structure locked; only scrap percentage can be overridden.
				$defaultBom = $this->getDefaultBomFromProduct($product);
				if (empty($defaultBom)) {
					return ResponseHelper::error(
						'Product BOM not found',
						422,
						'This product has no BOM formula to run internal production reorder.'
					);
				}

				$resolvedBom = $this->resolveLockedReorderBom($request, $defaultBom);
				if (isset($resolvedBom['response'])) {
					return $resolvedBom['response'];
				}

				$bomItems = $resolvedBom['bom'] ?? [];
				$request->merge([
					'raw_materials' => $bomItems,
				]);

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

				$consumptionPlan = $this->manufacturingService->buildConsumptionPlan(
					$bomItems,
					(float) $request->input('quantity', 0)
				);
			$shortfalls = $this->manufacturingService->validateSufficientStockForPlan($consumptionPlan);
			if (!empty($shortfalls)) {
				return ResponseHelper::error('Insufficient raw material stock', 422, $shortfalls);
			}

			$validated = Validator::make($request->all(), $rules)->validate();
			$validated['created_by'] = $validated['created_by'] ?? $this->getCurrentUserHelper->getUserId();
			$validated['last_updated_by'] = $validated['last_updated_by'] ?? $this->getCurrentUserHelper->getUserId();

			$movement = DB::transaction(function () use ($validated, $product, $bomItems, $consumptionPlan) {
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

				$this->manufacturingService->replaceReorderBom($productReorder, $bomItems);

				$referenceToken = $this->buildReorderReferenceToken((int) $movement->id);

				$this->manufacturingService->deductStockForPlan(
					$consumptionPlan,
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

				return ResponseHelper::success([
					'movement' => $movement['movement'] ?? null,
					'product_reorder_id' => $movement['product_reorder_id'] ?? null,
					'materials' => $this->manufacturingService->extractMaterialsSummary($consumptionPlan),
				], 'Product reordered (internal manufacturing) successfully', 201);
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

				// Reorder keeps BOM structure locked; only scrap percentage can be overridden.
				$lockedBom = $this->manufacturingService->getReorderBom($productReorder);
				if (empty($lockedBom)) {
					return ResponseHelper::error(
						'Reorder BOM not found',
						422,
						'This reorder has no BOM snapshot and cannot be updated.'
					);
				}

				$resolvedBom = $this->resolveLockedReorderBom($request, $lockedBom);
				if (isset($resolvedBom['response'])) {
					return $resolvedBom['response'];
				}

				$bomItems = $resolvedBom['bom'] ?? [];
				$request->merge([
					'raw_materials' => $bomItems,
				]);

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

				$isSoldReorderMovement = ($movement->is_sold === true);
				if ($isSoldReorderMovement) {
					$incomingQty = $request->input('quantity');
					$currentQty = (float) $movement->quantity;

				if ($incomingQty !== null && $incomingQty !== '' && (float) $incomingQty !== $currentQty) {
					return ResponseHelper::error(
						'Cannot update sold movement quantity',
						422,
						'The reordered product has been sold. Quantity cannot be updated to avoid data inconsistency.'
					);
				}

					if ($this->manufacturingService->isDifferentBom($bomItems, $lockedBom)) {
						return ResponseHelper::error(
							'Cannot update sold movement BOM',
							422,
							'The reordered product has been sold. BOM data cannot be updated to avoid inventory inconsistency.'
						);
					}

					// Allow safe metadata/pricing updates only.
					$bomItems = $this->manufacturingService->normalizeBomItems($lockedBom);
					$request->merge([
						'quantity' => $currentQty,
						'raw_materials' => $bomItems,
					]);
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
				'bom' => $this->manufacturingService->getReorderBom($productReorder),
			];

				$bomItems = $validated['raw_materials'] ?? [];
				$consumptionPlan = [];

				DB::beginTransaction();
				try {
					$referenceToken = $this->buildReorderReferenceToken((int) $movement->id);
					if (!$isSoldReorderMovement) {
						$deletedRawMaterialIds = $this->manufacturingService->deleteConsumptionMovementsByToken($referenceToken);

						$existingBomRawMaterialIds = $productReorder->bomItems()
							->pluck('raw_material_id')
							->all();

						$incomingBomRawMaterialIds = collect($bomItems)
							->pluck('raw_material_id')
							->map(fn ($id) => (int) $id)
							->values()
							->all();

						$rebuildIds = array_values(array_unique(array_merge(
							$deletedRawMaterialIds,
							$existingBomRawMaterialIds,
							$incomingBomRawMaterialIds
						)));
						$this->manufacturingService->rebuildInUsedFlags($rebuildIds);

						$consumptionPlan = $this->manufacturingService->buildConsumptionPlan(
							$bomItems,
							(float) ($validated['quantity'] ?? 0)
						);
						$shortfalls = $this->manufacturingService->validateSufficientStockForPlan($consumptionPlan);
						if (!empty($shortfalls)) {
							DB::rollBack();
							return ResponseHelper::error('Insufficient raw material stock', 422, $shortfalls);
						}

						$this->manufacturingService->replaceReorderBom($productReorder, $bomItems);

						$this->manufacturingService->deductStockForPlan(
							$consumptionPlan,
							$product->id,
							(int) $validated['last_updated_by'],
							$validated['movement_date'],
							$referenceToken
						);
					} else {
						$consumptionPlan = $this->manufacturingService->buildConsumptionPlan(
							$bomItems,
							(float) ($validated['quantity'] ?? 0)
						);
					}

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
				'bom' => $this->manufacturingService->getReorderBom($productReorder),
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

				return ResponseHelper::success([
					'movement' => $movement,
					'materials' => $this->manufacturingService->extractMaterialsSummary($consumptionPlan),
				], 'Product reorder (internal manufacturing) updated successfully', 201);
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
				$this->manufacturingService->deleteConsumptionMovementsByToken($referenceToken);

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
	private function resolveLockedReorderBom(Request $request, array $lockedBom): array
	{
		$normalizedLockedBom = $this->manufacturingService->normalizeBomItems($lockedBom);
		$incomingBom = $request->input('raw_materials');
		$incomingBomOverride = $request->input('bom_override');

		$hasIncomingBom = is_array($incomingBom) && !empty($incomingBom);
		$hasBomOverride = is_array($incomingBomOverride) && !empty($incomingBomOverride);

		if ($hasIncomingBom && $hasBomOverride) {
			return [
				'response' => ResponseHelper::validation([
					'bom_override' => ['Provide either raw_materials or bom_override, not both.'],
				], 'Validation Error'),
			];
		}

		if ($hasIncomingBom) {
			$normalizedIncomingBom = $this->manufacturingService->normalizeBomItems($incomingBom);
			if ($this->isLockedBomStructureDifferent($normalizedIncomingBom, $normalizedLockedBom)) {
				return [
					'response' => ResponseHelper::error(
						'BOM cannot be changed in reorder',
						422,
						'Reorder must keep the original raw materials and quantity per unit. Only scrap percentage can be changed.'
					),
				];
			}

			$incomingByRawMaterial = collect($normalizedIncomingBom)
				->keyBy('raw_material_id');

			$resolvedBom = collect($normalizedLockedBom)
				->map(function (array $item) use ($incomingByRawMaterial) {
					$incomingItem = $incomingByRawMaterial->get((int) $item['raw_material_id']);
					$scrapPercentage = isset($incomingItem['scrap_percentage'])
						? (float) $incomingItem['scrap_percentage']
						: (float) $item['scrap_percentage'];

					return [
						'raw_material_id' => (int) $item['raw_material_id'],
						'quantity_per_unit' => round((float) $item['quantity_per_unit'], 4),
						'scrap_percentage' => round(max(0, min(100, $scrapPercentage)), 4),
					];
				})
				->values()
				->all();

			return ['bom' => $resolvedBom];
		}

		if ($hasBomOverride) {
			$normalizedOverrideResult = $this->normalizeBomOverrideItems($incomingBomOverride);
			if (!empty($normalizedOverrideResult['errors'])) {
				return [
					'response' => ResponseHelper::validation($normalizedOverrideResult['errors'], 'Validation Error'),
				];
			}

			$normalizedOverrides = $normalizedOverrideResult['items'] ?? [];
			$lockedRawMaterialIds = collect($normalizedLockedBom)
				->pluck('raw_material_id')
				->map(fn ($id) => (int) $id)
				->all();

			$unknownOverrideRawMaterialIds = collect($normalizedOverrides)
				->pluck('raw_material_id')
				->map(fn ($id) => (int) $id)
				->filter(fn ($id) => !in_array($id, $lockedRawMaterialIds, true))
				->values()
				->all();

			if (!empty($unknownOverrideRawMaterialIds)) {
				return [
					'response' => ResponseHelper::error(
						'BOM cannot be changed in reorder',
						422,
						'Reorder BOM override contains raw materials that are not in the locked BOM.'
					),
				];
			}

			$overrideByRawMaterial = collect($normalizedOverrides)
				->keyBy('raw_material_id');

			$resolvedBom = collect($normalizedLockedBom)
				->map(function (array $item) use ($overrideByRawMaterial) {
					$overrideItem = $overrideByRawMaterial->get((int) $item['raw_material_id']);
					$scrapPercentage = isset($overrideItem['scrap_percentage'])
						? (float) $overrideItem['scrap_percentage']
						: (float) $item['scrap_percentage'];

					return [
						'raw_material_id' => (int) $item['raw_material_id'],
						'quantity_per_unit' => round((float) $item['quantity_per_unit'], 4),
						'scrap_percentage' => round(max(0, min(100, $scrapPercentage)), 4),
					];
				})
				->values()
				->all();

			return ['bom' => $resolvedBom];
		}

		return ['bom' => $normalizedLockedBom];
	}

	private function isLockedBomStructureDifferent(array $incomingBom, array $lockedBom): bool
	{
		$incomingByRawMaterial = collect($incomingBom)->keyBy('raw_material_id');
		$lockedByRawMaterial = collect($lockedBom)->keyBy('raw_material_id');

		if ($incomingByRawMaterial->count() !== $lockedByRawMaterial->count()) {
			return true;
		}

		foreach ($lockedByRawMaterial as $rawMaterialId => $lockedItem) {
			if (!$incomingByRawMaterial->has($rawMaterialId)) {
				return true;
			}

			$incomingItem = $incomingByRawMaterial->get($rawMaterialId);
			$incomingQtyPerUnit = (float) ($incomingItem['quantity_per_unit'] ?? 0);
			$lockedQtyPerUnit = (float) ($lockedItem['quantity_per_unit'] ?? 0);

			if (abs($incomingQtyPerUnit - $lockedQtyPerUnit) > 0.0001) {
				return true;
			}
		}

		return false;
	}

	private function normalizeBomOverrideItems(array $bomOverride): array
	{
		$normalizedItems = [];
		$errors = [];
		$seenRawMaterialIds = [];

		foreach ($bomOverride as $index => $item) {
			$rawMaterialId = (int) ($item['raw_material_id'] ?? 0);
			$hasRawMaterialId = array_key_exists('raw_material_id', $item);
			$hasScrapPercentage = array_key_exists('scrap_percentage', $item);
			$scrapPercentageRaw = $item['scrap_percentage'] ?? null;

			if (!$hasRawMaterialId || $rawMaterialId <= 0) {
				$errors["bom_override.{$index}.raw_material_id"][] = 'The raw_material_id field is required.';
			}

			if (!$hasScrapPercentage) {
				$errors["bom_override.{$index}.scrap_percentage"][] = 'The scrap_percentage field is required.';
			} elseif (!is_numeric($scrapPercentageRaw)) {
				$errors["bom_override.{$index}.scrap_percentage"][] = 'The scrap_percentage must be a number.';
			} else {
				$scrapPercentage = (float) $scrapPercentageRaw;
				if ($scrapPercentage < 0 || $scrapPercentage > 100) {
					$errors["bom_override.{$index}.scrap_percentage"][] = 'The scrap_percentage must be between 0 and 100.';
				}
			}

			if ($rawMaterialId > 0) {
				if (in_array($rawMaterialId, $seenRawMaterialIds, true)) {
					$errors["bom_override.{$index}.raw_material_id"][] = 'Duplicate raw_material_id is not allowed.';
				} else {
					$seenRawMaterialIds[] = $rawMaterialId;
				}
			}

			if (
				$hasRawMaterialId &&
				$rawMaterialId > 0 &&
				$hasScrapPercentage &&
				is_numeric($scrapPercentageRaw)
			) {
				$normalizedItems[] = [
					'raw_material_id' => $rawMaterialId,
					'scrap_percentage' => round(max(0, min(100, (float) $scrapPercentageRaw)), 4),
				];
			}
		}

		return [
			'items' => $normalizedItems,
			'errors' => $errors,
		];
	}

	private function getDefaultBomFromProduct(Product $product): array
	{
		return $this->manufacturingService->getProductBom((int) $product->id);
	}

	private function buildReorderReferenceToken(int $movementId): string
	{
		return "REORDER_MOVEMENT_ID:{$movementId}";
	}
}
