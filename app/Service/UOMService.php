<?php

namespace App\Service;

use App\Exceptions\UomConversionException;
use App\Helpers\ResponseHelper;
use App\Models\UnitOfMeasurement;
use App\QueryBuilders\UOMQueryBuilder;
use App\Validations\UOMValidation;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UOMService
{
    protected UOMValidation      $UOMValidation;
    protected UOMQueryBuilder    $UOMQueryBuilder;
    protected UomConversionService $conversionService;

    public function __construct(
        UOMValidation        $UOMValidation,
        UOMQueryBuilder      $UOMQueryBuilder,
        UomConversionService $conversionService
    ) {
        $this->UOMValidation     = $UOMValidation;
        $this->UOMQueryBuilder   = $UOMQueryBuilder;
        $this->conversionService = $conversionService;
    }

    // -------------------------------------------------------------------------
    // Read
    // -------------------------------------------------------------------------

    public function getAllUOM(Request $request)
    {
        try {
            return $this->UOMQueryBuilder->UOMBuilder($request);
        } catch (Exception $e) {
            return ResponseHelper::error('Failed to fetch UOM: ', 500, $e->getMessage());
        }
    }

    public function getUOMById(int $id)
    {
        try {
            $uom = UnitOfMeasurement::with(['category', 'baseUom', 'children'])->findOrFail($id);
            return ResponseHelper::success($uom, 'UOM fetched successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error('UOM not found', 404, $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // Write
    // -------------------------------------------------------------------------

    public function createUOM(Request $request)
    {
        try {
            $validated = $this->UOMValidation->CreateValidationFields($request);

            $uom = UnitOfMeasurement::create($validated);

            return ResponseHelper::success(
                $uom->load(['category', 'baseUom']),
                'UOM created successfully',
                201
            );
        } catch (ValidationException $ve) {
            return ResponseHelper::validation($ve->errors());
        } catch (Exception $e) {
            return ResponseHelper::error('Failed to create UOM', 500, $e->getMessage());
        }
    }

    public function updateUOM(Request $request, int $id)
    {
        try {
            $validated = $this->UOMValidation->UpdateValidationFields($request, $id);
            $uom       = UnitOfMeasurement::findOrFail($id);
            $uom->update($validated);

            return ResponseHelper::success(
                $uom->fresh(['category', 'baseUom']),
                'UOM updated successfully',
                200
            );
        } catch (ValidationException $ve) {
            return ResponseHelper::validation($ve->errors());
        } catch (Exception $e) {
            return ResponseHelper::error('Failed to update UOM', 500, $e->getMessage());
        }
    }

    public function deleteUOM(int $id)
    {
        try {
            $uom = UnitOfMeasurement::findOrFail($id);
            $uom->delete(); // SoftDeletes: sets deleted_at

            return ResponseHelper::success(null, 'UOM archived successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error('Failed to archive UOM', 500, $e->getMessage());
        }
    }

    /**
     * Restore a previously soft-deleted UOM record.
     */
    public function restoreUOM(int $id)
    {
        try {
            $uom = UnitOfMeasurement::onlyTrashed()->findOrFail($id);
            $uom->restore();

            return ResponseHelper::success(
                $uom->fresh(['category', 'baseUom']),
                'UOM restored successfully',
                200
            );
        } catch (Exception $e) {
            return ResponseHelper::error('Failed to restore UOM', 500, $e->getMessage());
        }
    }

    /**
     * Return paginated soft-deleted UOMs, optionally filtered by category.
     * GET /uoms/trashed?filter[category_id]=1
     */
    public function getTrashedUOMs(Request $request)
    {
        try {
            $query = UnitOfMeasurement::onlyTrashed()->with(['category', 'baseUom']);

            if ($request->filled('filter.category_id')) {
                $query->where('category_id', $request->input('filter.category_id'));
            }

            $perPage = (int) $request->input('per_page', 15);
            $result  = $query->orderBy('name')->paginate($perPage);

            return ResponseHelper::success($result, 'Trashed UOMs fetched successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error('Failed to fetch trashed UOMs', 500, $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // Conversion endpoints
    // -------------------------------------------------------------------------

    /**
     * Convert a quantity between two UOMs and return the result.
     *
     * Expected request payload:
     *   { "quantity": 5, "from_uom_id": 4, "to_uom_id": 1 }
     */
    public function convertQuantity(Request $request)
    {
        try {
            $data = $request->validate([
                'quantity'    => 'required|numeric|min:0',
                'from_uom_id' => 'required|integer|exists:unit_of_measurements,id',
                'to_uom_id'   => 'required|integer|exists:unit_of_measurements,id',
            ]);

            $result = $this->conversionService->convert(
                $data['quantity'],
                (int) $data['from_uom_id'],
                (int) $data['to_uom_id']
            );

            $fromUom = UnitOfMeasurement::find($data['from_uom_id']);
            $toUom   = UnitOfMeasurement::find($data['to_uom_id']);

            return ResponseHelper::success([
                'original_quantity' => $data['quantity'],
                'from_uom'          => ['id' => $fromUom->id, 'name' => $fromUom->name, 'symbol' => $fromUom->symbol],
                'to_uom'            => ['id' => $toUom->id,   'name' => $toUom->name,   'symbol' => $toUom->symbol],
                'converted_quantity' => $result,
            ], 'Conversion successful', 200);

        } catch (ValidationException $ve) {
            return ResponseHelper::validation($ve->errors(), 422);
        } catch (UomConversionException $ce) {
            return ResponseHelper::error($ce->getMessage(), 422);
        } catch (Exception $e) {
            return ResponseHelper::error('Conversion failed', 500, $e->getMessage());
        }
    }
}

