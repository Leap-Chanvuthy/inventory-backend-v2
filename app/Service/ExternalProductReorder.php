<?php

namespace App\Service;

use App\Helpers\CurrencyPricingHelper;
use App\Helpers\GetCurrentUserHelper;
use App\Helpers\ResponseHelper;
use App\Helpers\UomQuantityGuard;
use App\Models\Product;
use App\Models\ProductMovement;
use App\Validations\ProductValidation;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ExternalProductReorder
{
	protected ProductValidation $productValidation;
	protected GetCurrentUserHelper $getCurrentUserHelper;
	protected AuditLoggerService $auditLoggerService;

	public function __construct(
		ProductValidation $productValidation,
		GetCurrentUserHelper $getCurrentUserHelper,
		AuditLoggerService $auditLoggerService
	) {
		$this->productValidation = $productValidation;
		$this->getCurrentUserHelper = $getCurrentUserHelper;
		$this->auditLoggerService = $auditLoggerService;
	}

	public function reorderExternalPurchasedProduct(Request $request, $productId)
	{
		try {
			$product = Product::findOrFail($productId);

			$rules = $this->productValidation->createExternalPurchaseMovementRules();
			$validator = Validator::make($request->all(), $rules);
			if ($validator->fails()) {
				return ResponseHelper::validation($validator->errors()->toArray(), 'Validation Error');
			}

			$productTypeValue = ($product->product_type instanceof \BackedEnum)
				? $product->product_type->value
				: (string) $product->product_type;

			if ($productTypeValue !== \App\Enums\ProductTypeEnum::EXTERNAL_PURCHASED->value) {
				return ResponseHelper::error(
					'Invalid product type for external reorder',
					422,
					'Product must be an externally purchased product to create an external reorder.'
				);
			}

			$request->merge([
				'product_id' => $product->id,
				'direction' => \App\Enums\StockDirectionEnum::IN->value,
				'movement_type' => \App\Enums\ProductStockMovementTypeEnum::RE_ORDER->value,
				'movement_date' => $request->input('movement_date', now()->toDateTimeString()),
			]);
			$request->request->remove('warehouse_id');

			$request->merge([
				'purchase_total_price_in_usd' => null,
				'purchase_unit_price_in_riel' => null,
				'purchase_total_price_in_riel' => null,
				'exchange_rate_from_riel_to_usd' => null,
			]);

			$currentUserId = $this->getCurrentUserHelper->getUserId();
			$request->merge([
				'created_by'      => $currentUserId,
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

			$validated = Validator::make($request->all(), $rules)->validate();
			UomQuantityGuard::assertQuantityByUomId(
				$validated['quantity'] ?? null,
				(int) $product->base_uom_id,
				'quantity'
			);
			$validated['created_by'] = $validated['created_by'] ?? $this->getCurrentUserHelper->getUserId();
			$validated['last_updated_by'] = $validated['last_updated_by'] ?? $this->getCurrentUserHelper->getUserId();

			$movement = DB::transaction(function () use ($validated, $product) {
				return ProductMovement::create([
					'product_id' => $product->id,
					'direction' => \App\Enums\StockDirectionEnum::IN->value,
					'movement_type' => \App\Enums\ProductStockMovementTypeEnum::RE_ORDER->value,
					'product_status' => \App\Enums\ProductStatusEnum::COMPLETED->value,
					'quantity' => $validated['quantity'],
					'remaining_quantity' => $validated['quantity'],
					'is_sold' => false,
					'movement_date' => $validated['movement_date'],
					'note' => $validated['note'] ?? null,
					'created_by' => $validated['created_by'],
					'last_updated_by' => $validated['last_updated_by'],
					'purchase_unit_price_in_usd' => $validated['purchase_unit_price_in_usd'],
					'purchase_total_price_in_usd' => $validated['purchase_total_price_in_usd'] ?? 0,
					'exchange_rate_from_usd_to_riel' => $validated['exchange_rate_from_usd_to_riel'],
					'purchase_unit_price_in_riel' => $validated['purchase_unit_price_in_riel'] ?? 0,
					'purchase_total_price_in_riel' => $validated['purchase_total_price_in_riel'] ?? 0,
					'exchange_rate_from_riel_to_usd' => $validated['exchange_rate_from_riel_to_usd'] ?? 0,
					'selling_unit_price_in_usd' => $validated['selling_unit_price_in_usd'],
					'selling_unit_price_in_riel' => $validated['selling_unit_price_in_riel'] ?? 0,
					'selling_exchange_rate_from_usd_to_riel' => $validated['selling_exchange_rate_from_usd_to_riel'],
					'selling_exchange_rate_from_riel_to_usd' => $validated['selling_exchange_rate_from_riel_to_usd'] ?? 0,
				]);
			});

			$this->auditLoggerService->logChange(
				'product.reorder.external.create',
				Product::class,
				(int) $product->id,
				[],
				[
					'movement' => $this->auditLoggerService->snapshotModel($movement),
				],
				(int) ($validated['last_updated_by'] ?? $currentUserId),
				['context' => 'external_reorder_service']
			);

			return ResponseHelper::success($movement, 'Product reordered successfully', 201);
		} catch (ValidationException $e) {
			return ResponseHelper::validation($e->errors(), 'Validation Error');
		} catch (Exception $e) {
			return ResponseHelper::error($e->getMessage(), 500);
		}
	}

	public function updateReorderExternalPurchasedProduct(Request $request, $productId, $movementId)
	{
		try {
			$product = Product::findOrFail($productId);
			$movement = ProductMovement::findOrFail($movementId);

			$rules = $this->productValidation->createExternalPurchaseMovementRules();
			$validator = Validator::make($request->all(), $rules);
			if ($validator->fails()) {
				return ResponseHelper::validation($validator->errors()->toArray(), 'Validation Error');
			}

			$productTypeValue = ($product->product_type instanceof \BackedEnum)
				? $product->product_type->value
				: (string) $product->product_type;

			if ($productTypeValue !== \App\Enums\ProductTypeEnum::EXTERNAL_PURCHASED->value) {
				return ResponseHelper::error(
					'Invalid product type for external reorder update',
					422,
					'Product must be an externally purchased product to update an external reorder.'
				);
			}

			$request->merge([
				'product_id' => $product->id,
				'direction' => \App\Enums\StockDirectionEnum::IN->value,
				'movement_type' => \App\Enums\ProductStockMovementTypeEnum::RE_ORDER->value,
				'movement_date' => $request->input('movement_date', now()->toDateTimeString()),
			]);
			$request->request->remove('warehouse_id');

			$request->merge([
				'purchase_total_price_in_usd' => null,
				'purchase_unit_price_in_riel' => null,
				'purchase_total_price_in_riel' => null,
				'exchange_rate_from_riel_to_usd' => null,
			]);

			$request->merge([
				'created_by' => $movement->created_by,
				'last_updated_by' => $this->getCurrentUserHelper->getUserId(),
			]);

			$hasAllocations = $movement->sourceAllocations()->exists();
			if ($hasAllocations) {
				return ResponseHelper::error(
					'Cannot update allocated stock lot',
					422,
					'This stock lot has already been used in a sale. Quantity, price, movement date, and stock status cannot be changed. Use an adjustment or reorder movement instead.'
				);
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
			UomQuantityGuard::assertQuantityByUomId(
				$validated['quantity'] ?? null,
				(int) $product->base_uom_id,
				'quantity'
			);
			$validated['created_by'] = $validated['created_by'] ?? $movement->created_by ?? $this->getCurrentUserHelper->getUserId();
			$validated['last_updated_by'] = $validated['last_updated_by'] ?? $this->getCurrentUserHelper->getUserId();

			$oldSnapshot = $this->auditLoggerService->snapshotModel($movement);

			$movement = DB::transaction(function () use ($validated, $movement) {
				$currentQty = (float) $movement->quantity;
				$currentRemaining = (float) $movement->remaining_quantity;
				$alreadyAllocated = max(0, round($currentQty - $currentRemaining, 4));
				$nextQty = (float) $validated['quantity'];
				$nextRemaining = max(0, round($nextQty - $alreadyAllocated, 4));

				$movement->update([
					'quantity' => $nextQty,
					'remaining_quantity' => $nextRemaining,
					'direction' => \App\Enums\StockDirectionEnum::IN->value,
					'movement_type' => \App\Enums\ProductStockMovementTypeEnum::RE_ORDER->value,
					'movement_date' => $validated['movement_date'],
					'note' => $validated['note'] ?? null,
					'purchase_unit_price_in_usd' => $validated['purchase_unit_price_in_usd'],
					'purchase_total_price_in_usd' => $validated['purchase_total_price_in_usd'] ?? 0,
					'exchange_rate_from_usd_to_riel' => $validated['exchange_rate_from_usd_to_riel'],
					'purchase_unit_price_in_riel' => $validated['purchase_unit_price_in_riel'] ?? 0,
					'purchase_total_price_in_riel' => $validated['purchase_total_price_in_riel'] ?? 0,
					'exchange_rate_from_riel_to_usd' => $validated['exchange_rate_from_riel_to_usd'] ?? 0,
					'selling_unit_price_in_usd' => $validated['selling_unit_price_in_usd'],
					'selling_unit_price_in_riel' => $validated['selling_unit_price_in_riel'] ?? 0,
					'selling_exchange_rate_from_usd_to_riel' => $validated['selling_exchange_rate_from_usd_to_riel'],
					'selling_exchange_rate_from_riel_to_usd' => $validated['selling_exchange_rate_from_riel_to_usd'] ?? 0,
					'last_updated_by' => $validated['last_updated_by'],
				]);

				return $movement->fresh();
			});

			$this->auditLoggerService->logDiff(
				'product.reorder.external.update',
				Product::class,
				(int) $product->id,
				$oldSnapshot,
				$this->auditLoggerService->snapshotModel($movement),
				(int) ($validated['last_updated_by'] ?? $this->getCurrentUserHelper->getUserId()),
				['context' => 'external_reorder_service']
			);

			return ResponseHelper::success($movement, 'Product reorder updated successfully', 201);
		} catch (ValidationException $e) {
			return ResponseHelper::validation($e->errors(), 'Validation Error');
		} catch (Exception $e) {
			return ResponseHelper::error($e->getMessage(), 500);
		}
	}

	public function getReorderExternalDetail(int $productId, int $movementId)
	{
		try {
			$product = Product::findOrFail($productId);

			$productTypeValue = ($product->product_type instanceof \BackedEnum)
				? $product->product_type->value
				: (string) $product->product_type;

			if ($productTypeValue !== \App\Enums\ProductTypeEnum::EXTERNAL_PURCHASED->value) {
				return ResponseHelper::error('Invalid product type for external purchase reorder', 422, 'Product must be an externally purchased product.');
			}

			$movement = ProductMovement::findOrFail($movementId);
			$movementTypeValue = is_object($movement->movement_type) ? $movement->movement_type->value : (string) $movement->movement_type;

			if ($movement->product_id !== $product->id || $movementTypeValue !== \App\Enums\ProductStockMovementTypeEnum::RE_ORDER->value) {
				return ResponseHelper::error('Movement not found or not a reorder movement', 404);
			}

			return ResponseHelper::success([
				'product' => $product,
				'movement' => $movement,
			]);
		} catch (Exception $e) {
			return ResponseHelper::error($e->getMessage(), 500);
		}
	}

	

	// Delete external product reorder movement in case the movement is not used in any sales yet. This is to avoid data inconsistency since the reorder movement can be used as a reference for COGS calculation in sales.
	public function deleteReorderExternalPurchasedProduct(int $productId, int $movementId){
		try {
			$product = Product::findOrFail($productId);
			$movement = ProductMovement::findOrFail($movementId);

			$productTypeValue = ($product->product_type instanceof \BackedEnum)
				? $product->product_type->value
				: (string) $product->product_type;

			if ($productTypeValue !== \App\Enums\ProductTypeEnum::EXTERNAL_PURCHASED->value) {
				return ResponseHelper::error('Invalid product type for external purchase reorder deletion', 422, 'Product must be an externally purchased product.');
			}

			$movementTypeValue = is_object($movement->movement_type) ? $movement->movement_type->value : (string) $movement->movement_type;
			if ($movement->product_id !== $product->id || $movementTypeValue !== \App\Enums\ProductStockMovementTypeEnum::RE_ORDER->value) {
				return ResponseHelper::error('Movement not found or not a reorder movement', 404);
			}

			if ($movement->sourceAllocations()->exists()) {
				return ResponseHelper::error('Cannot delete used stock movement', 401, 'This stock lot has already been used in a sale and cannot be deleted.');
			}

			$oldSnapshot = $this->auditLoggerService->snapshotModel($movement);
			$movement->delete();

			$this->auditLoggerService->logChange(
				'product.reorder.external.delete',
				Product::class,
				(int) $product->id,
				$oldSnapshot,
				[],
				$this->getCurrentUserHelper->getUserId(),
				['context' => 'external_reorder_service']
			);

			return ResponseHelper::success(null, 'Product reorder deleted successfully', 200);
		} catch (Exception $e) {
			return ResponseHelper::error($e->getMessage(), 500);
		}
	}




}
