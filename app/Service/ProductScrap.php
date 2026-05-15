<?php

namespace App\Service;

use App\Enums\ProductStockMovementTypeEnum;
use App\Enums\StockDirectionEnum;
use App\Helpers\GetCurrentUserHelper;
use App\Helpers\ResponseHelper;
use App\Helpers\UomQuantityGuard;
use App\Models\Product;
use App\Models\ProductMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProductScrap
{
    public function __construct(
        protected GetCurrentUserHelper $getCurrentUserHelper,
        protected AuditLoggerService $auditLoggerService,
        protected ProductStockLotService $productStockLotService
    ) {
    }

    public function createScrapMovement(Request $request, int $productId)
    {
        try {
            $product = Product::query()->findOrFail($productId);

            $validator = Validator::make($request->all(), [
                'source_movement_id' => 'required|integer',
                'quantity' => 'required|numeric|min:0.0001',
                'movement_date' => 'nullable|date',
                'reason' => 'nullable|string|max:255',
                'note' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return ResponseHelper::validation($validator->errors()->toArray(), 'Validation Error');
            }

            $validated = $validator->validate();
            UomQuantityGuard::assertQuantityByUomId(
                $validated['quantity'] ?? null,
                (int) $product->base_uom_id,
                'quantity'
            );

            $currentUserId = $this->getCurrentUserHelper->getUserId();
            $result = $this->productStockLotService->createScrap($product, $validated, (int) $currentUserId);

            $this->auditLoggerService->logChange(
                'product.scrap.create',
                Product::class,
                (int) $product->id,
                [],
                [
                    'scrap_movement' => $this->auditLoggerService->snapshotModel($result['scrap_movement'] ?? null),
                    'source_lot' => $this->auditLoggerService->snapshotModel($result['source_lot'] ?? null),
                ],
                (int) $currentUserId,
                ['context' => 'product_scrap_service']
            );

            return ResponseHelper::success($result, 'Product scrapped successfully', 201);
        } catch (ValidationException $e) {
            return ResponseHelper::validation($e->errors(), 'Validation Error');
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function updateScrapMovement(Request $request, int $productId, int $movementId)
    {
        try {
            $product = Product::query()->findOrFail($productId);

            /** @var ProductMovement $movement */
            $movement = ProductMovement::query()->findOrFail($movementId);

            if ((int) $movement->product_id !== (int) $product->id) {
                return ResponseHelper::error('Movement does not belong to this product.', 422);
            }

            if ($this->movementType($movement) !== ProductStockMovementTypeEnum::SCRAP->value) {
                return ResponseHelper::error('Movement is not a scrap movement.', 422);
            }

            $validator = Validator::make($request->all(), [
                'source_movement_id' => 'required|integer',
                'quantity' => 'required|numeric|min:0.0001',
                'movement_date' => 'nullable|date',
                'reason' => 'nullable|string|max:255',
                'note' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return ResponseHelper::validation($validator->errors()->toArray(), 'Validation Error');
            }

            $validated = $validator->validate();
            UomQuantityGuard::assertQuantityByUomId(
                $validated['quantity'] ?? null,
                (int) $product->base_uom_id,
                'quantity'
            );

            $currentUserId = (int) $this->getCurrentUserHelper->getUserId();
            $oldSnapshot = $this->auditLoggerService->snapshotModel($movement);

            $updated = DB::transaction(function () use ($product, $movement, $validated, $currentUserId) {
                /** @var ProductMovement $lockedScrap */
                $lockedScrap = ProductMovement::query()
                    ->whereKey($movement->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                /** @var ProductMovement|null $oldSourceLot */
                $oldSourceLot = ProductMovement::query()
                    ->whereKey((int) $lockedScrap->source_movement_id)
                    ->where('product_id', (int) $product->id)
                    ->where('direction', StockDirectionEnum::IN->value)
                    ->lockForUpdate()
                    ->first();

                if ($oldSourceLot) {
                    $restored = round((float) $oldSourceLot->remaining_quantity + (float) $lockedScrap->quantity, 4);
                    $oldSourceLot->update([
                        'remaining_quantity' => min((float) $oldSourceLot->quantity, $restored),
                        'last_updated_by' => $currentUserId,
                    ]);
                }

                $newSourceId = (int) $validated['source_movement_id'];
                $newQty = round((float) $validated['quantity'], 4);

                /** @var ProductMovement|null $newSourceLot */
                $newSourceLot = ProductMovement::query()
                    ->whereKey($newSourceId)
                    ->where('product_id', (int) $product->id)
                    ->where('direction', StockDirectionEnum::IN->value)
                    ->lockForUpdate()
                    ->first();

                if (!$newSourceLot) {
                    throw ValidationException::withMessages([
                        'source_movement_id' => ['Selected stock batch was not found for this product.'],
                    ]);
                }

                $this->productStockLotService->assertLotCanBeConsumed($newSourceLot, $newQty);

                $nextRemaining = max(0, round((float) $newSourceLot->remaining_quantity - $newQty, 4));
                $newSourceLot->update([
                    'remaining_quantity' => $nextRemaining,
                    'is_sold' => true,
                    'last_updated_by' => $currentUserId,
                ]);

                $noteParts = ['SCRAP'];
                $reason = trim((string) ($validated['reason'] ?? ''));
                if ($reason !== '') {
                    $noteParts[] = "REASON:{$reason}";
                }
                $note = trim((string) ($validated['note'] ?? ''));
                if ($note !== '') {
                    $noteParts[] = $note;
                }

                $lockedScrap->update([
                    'source_movement_id' => (int) $newSourceLot->id,
                    'quantity' => $newQty,
                    'remaining_quantity' => 0,
                    'movement_date' => $validated['movement_date'] ?? now()->toDateTimeString(),
                    'expiry_date' => $newSourceLot->expiry_date,
                    'purchase_unit_price_in_usd' => (float) ($newSourceLot->purchase_unit_price_in_usd ?? 0),
                    'purchase_total_price_in_usd' => round((float) ($newSourceLot->purchase_unit_price_in_usd ?? 0) * $newQty, 4),
                    'purchase_unit_price_in_riel' => (float) ($newSourceLot->purchase_unit_price_in_riel ?? 0),
                    'purchase_total_price_in_riel' => round((float) ($newSourceLot->purchase_unit_price_in_riel ?? 0) * $newQty, 4),
                    'exchange_rate_from_usd_to_riel' => (float) ($newSourceLot->exchange_rate_from_usd_to_riel ?? 0),
                    'exchange_rate_from_riel_to_usd' => (float) ($newSourceLot->exchange_rate_from_riel_to_usd ?? 0),
                    'selling_unit_price_in_usd' => (float) ($newSourceLot->selling_unit_price_in_usd ?? 0),
                    'selling_unit_price_in_riel' => (float) ($newSourceLot->selling_unit_price_in_riel ?? 0),
                    'selling_exchange_rate_from_usd_to_riel' => (float) ($newSourceLot->selling_exchange_rate_from_usd_to_riel ?? 0),
                    'selling_exchange_rate_from_riel_to_usd' => (float) ($newSourceLot->selling_exchange_rate_from_riel_to_usd ?? 0),
                    'last_updated_by' => $currentUserId,
                    'note' => implode(' | ', $noteParts),
                ]);

                return [
                    'scrap_movement' => $lockedScrap->fresh(),
                    'source_lot' => $newSourceLot->fresh(),
                    'stock_lot_summary' => $this->productStockLotService->getStockLotSummary($product),
                ];
            });

            $this->auditLoggerService->logDiff(
                'product.scrap.update',
                Product::class,
                (int) $product->id,
                $oldSnapshot,
                $this->auditLoggerService->snapshotModel($updated['scrap_movement'] ?? null),
                $currentUserId,
                ['context' => 'product_scrap_service']
            );

            return ResponseHelper::success($updated, 'Product scrap updated successfully', 200);
        } catch (ValidationException $e) {
            return ResponseHelper::validation($e->errors(), 'Validation Error');
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    public function getScrapDetail(int $productId, int $movementId)
    {
        try {
            $product = Product::query()->findOrFail($productId);

            $movement = ProductMovement::query()
                ->with(['sourceMovement'])
                ->findOrFail($movementId);

            if ((int) $movement->product_id !== (int) $product->id) {
                return ResponseHelper::error('Movement does not belong to this product.', 404);
            }

            if ($this->movementType($movement) !== ProductStockMovementTypeEnum::SCRAP->value) {
                return ResponseHelper::error('Movement is not a scrap movement.', 404);
            }

            return ResponseHelper::success([
                'product' => $product,
                'movement' => $movement,
                'source_lot' => $movement->sourceMovement,
            ], 'Scrap movement retrieved successfully');
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    private function movementType(ProductMovement $movement): string
    {
        return $movement->movement_type instanceof \BackedEnum
            ? $movement->movement_type->value
            : (string) $movement->movement_type;
    }
}
