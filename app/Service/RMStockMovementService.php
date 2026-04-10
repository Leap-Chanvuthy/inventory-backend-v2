<?php

namespace App\Service;

use App\Enums\RawMaterialStockMovementTypeEnum;
use App\Helpers\CurrencyPricingHelper;
use App\Helpers\GetCurrentUserHelper;
use App\Helpers\ResponseHelper;
use App\Models\RMStockMovement;
use App\Models\RawMaterial;
use App\Validations\RMValidation;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RMStockMovementService
{
    public function __construct(
        protected RMValidation $rmValidation,
        protected GetCurrentUserHelper $getCurrentUserHelper,
        protected AuditLoggerService $auditLoggerService
    ) {
    }

    public function createForRawMaterial(Request $request, int $rawMaterialId)
    {
        try {
            $rawMaterial = RawMaterial::query()->findOrFail($rawMaterialId);

            // Defaults
            $request->merge([
                'raw_material_id' => $rawMaterialId,
                'movement_date' => $request->input('movement_date', now()->toDateTimeString()),
            ]);

            if (!$request->filled('movement_type')) {
                $request->merge(['movement_type' => RawMaterialStockMovementTypeEnum::RE_ORDER->value]);
            }

            $currentUserId = $this->getCurrentUserHelper->getUserId();
            $request->merge([
                'created_by' => $currentUserId,
                'last_updated_by' => $currentUserId,
            ]);

            // If PURCHASE, enforce only one purchase per raw material.
            if ($request->input('movement_type') === RawMaterialStockMovementTypeEnum::PURCHASE->value) {
                $purchaseExists = RMStockMovement::query()
                    ->where('raw_material_id', $rawMaterialId)
                    ->where('movement_type', RawMaterialStockMovementTypeEnum::PURCHASE->value)
                    ->exists();

                if ($purchaseExists) {
                    return ResponseHelper::validation([
                        'movement_type' => ['PURCHASE movement already exists for this raw material.'],
                    ], 'Validation Error');
                }

                // For purchase we default direction to IN.
                $request->merge(['direction' => $request->input('direction', 'IN')]);
            }

            $zeroPriceTypes = [
                RawMaterialStockMovementTypeEnum::PRODUCTION_SCRAP->value,
                RawMaterialStockMovementTypeEnum::PRODUCTION_RECEIPT->value,
                RawMaterialStockMovementTypeEnum::ADJUSTMENT_OUT->value,
            ];

            if (in_array($request->input('movement_type'), $zeroPriceTypes, true)) {
                // Force all pricing/rates to 0 for these movement types.
                $request->merge([
                    'unit_price_in_usd' => 0,
                    'total_value_in_usd' => 0,
                    'exchange_rate_from_usd_to_riel' => 0,
                    'unit_price_in_riel' => 0,
                    'total_value_in_riel' => 0,
                    'exchange_rate_from_riel_to_usd' => 0,
                ]);
            } else {
                // Compute totals and currency conversions.
                CurrencyPricingHelper::fillRMPurchasingCurrencyFields($request);
            }

            $rules = $this->rmValidation->CreateRMStockMovementValidation($request);
            $validated = Validator::make($request->all(), $rules)->validate();

            $movement = DB::transaction(function () use ($validated) {
                return RMStockMovement::create([
                    'raw_material_id' => $validated['raw_material_id'],
                    'quantity' => $validated['quantity'],
                    'direction' => $validated['direction'],
                    'movement_type' => $validated['movement_type'],
                    'movement_date' => $validated['movement_date'],
                    'unit_price_in_usd' => $validated['unit_price_in_usd'],
                    'total_value_in_usd' => $validated['total_value_in_usd'],
                    'exchange_rate_from_usd_to_riel' => $validated['exchange_rate_from_usd_to_riel'],
                    'unit_price_in_riel' => $validated['unit_price_in_riel'],
                    'total_value_in_riel' => $validated['total_value_in_riel'],
                    'exchange_rate_from_riel_to_usd' => $validated['exchange_rate_from_riel_to_usd'],
                    'note' => $validated['note'] ?? null,
                ]);
            });

            $this->auditLoggerService->logChange(
                'raw_material.stock_movement.create',
                RawMaterial::class,
                (int) $rawMaterial->id,
                [],
                ['movement' => $this->auditLoggerService->snapshotModel($movement)],
                $currentUserId !== null ? (int) $currentUserId : null,
                ['context' => 'rm_stock_movement_service']
            );

            return ResponseHelper::success($movement, 'RM Stock movement created successfully', 201);
        } catch (ValidationException $e) {
            return ResponseHelper::validation($e->errors(), 'Validation Error');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}
