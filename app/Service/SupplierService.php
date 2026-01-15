<?php

namespace App\Service;


use App\Helpers\FileUploadHelper;
use App\Helpers\ResponseHelper;
use App\Imports\SuppliersImport;
use App\Models\Supplier;
use App\Models\SupplierImportHistory;
use App\QueryBuilders\SupplierImportQuery;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\QueryBuilders\SupplierQueryBuilder;
use App\Validations\SupplierBankValidation;
use App\Validations\SupplierValidation;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class SupplierService
{

    protected $supplierValidation;
    protected $supplierBankValidation;
    protected $supplierQueryBuilder;
    protected $supplierImportQueryBuilder;
    protected $supplierBankService;

    public function __construct(
        SupplierValidation $supplierValidation,
        SupplierBankValidation $supplierBankValidation,
        SupplierBankService $supplierBankService,
        SupplierQueryBuilder $supplierQueryBuilder,
        SupplierImportQuery $supplierImportQueryBuilder
    ) {
        $this->supplierValidation = $supplierValidation;
        $this->supplierBankValidation = $supplierBankValidation;
        $this->supplierBankService = $supplierBankService;
        $this->supplierQueryBuilder = $supplierQueryBuilder;
        $this->supplierImportQueryBuilder = $supplierImportQueryBuilder;
    }


    public function getAllSuppliers(Request $request)
    {
        try {
            $suppliers = $this->supplierQueryBuilder->supplierBuilder($request);
            return ResponseHelper::success($suppliers, "Suppliers retrieved successfully", 200);
        } catch (Exception $e) {
            return ResponseHelper::error('Error fetching suppliers', 500, $e->getMessage());
        }
    }

    public function getSupplierById($id)
    {
        try {
            $supplier = Supplier::with('banks')->find($id);
            if (!$supplier) {
                return ResponseHelper::error("Supplier not found", 404, null);
            }
            return ResponseHelper::success($supplier, "Supplier retrieved successfully", 200);
        } catch (Exception $e) {
            return ResponseHelper::error("Failed getting supplier", 500, $e->getMessage());
        }
    }


    public function createSupplier(Request $request)
    {
        DB::beginTransaction();

        try {
            $validatedSupplier = $this->supplierValidation->validationFields($request);
            $validatedBanks = $this->supplierBankValidation->validate($request);

            if ($request->hasFile('image')) {
                $validatedSupplier['image'] = FileUploadHelper::uploadSingle(
                    $request->file('image'),
                    'suppliers'
                );
            }

            $banks = $validatedBanks['banks'] ?? [];
            unset($validatedSupplier['banks']);

            $supplier = Supplier::create($validatedSupplier);

            // max:4 + distinct + enum already enforced by SupplierBankValidation
            if (!empty($banks)) {
                $this->supplierBankService->createMany($supplier, $banks);
            }

            DB::commit();

            return ResponseHelper::success(
                $supplier->load('banks'),
                "Supplier created successfully",
                201
            );
        } catch (ValidationException $ve) {
            DB::rollBack();
            return ResponseHelper::validation($ve->errors(), "Validation Error");
        } catch (Exception $e) {
            DB::rollBack();
            return ResponseHelper::error("Failed creating supplier", 500, $e->getMessage());
        }
    }



    public function updateSupplier(Request $request, int $id)
    {
        DB::beginTransaction();

        try {
            $supplier = Supplier::with('banks')->find($id);
            if (!$supplier) {
                return ResponseHelper::error("Supplier not found", 404, null);
            }

            $validatedSupplier = $this->supplierValidation->updateValidationFields($request, $id);
            $validatedBanks = $this->supplierBankValidation->validate($request);

            if ($request->hasFile('image')) {
                $validatedSupplier['image'] = FileUploadHelper::uploadSingle(
                    $request->file('image'),
                    'suppliers',
                    $supplier->image
                );
            }

            $incomingBanks = $validatedBanks['banks'] ?? [];

            // Enforce "max 4 total" when adding new bank_name(s)
            if (!empty($incomingBanks)) {
                $existingNames = $supplier->banks->pluck('bank_name')->all();
                $incomingNames = array_values(array_unique(array_map(
                    fn ($b) => $b['bank_name'] ?? null,
                    $incomingBanks
                )));
                $incomingNames = array_values(array_filter($incomingNames)); // remove nulls

                $newNames = array_values(array_diff($incomingNames, $existingNames));

                if (($supplier->banks->count() + count($newNames)) > 4) {
                    throw ValidationException::withMessages([
                        'banks' => ['A supplier can have at most 4 payment methods.'],
                    ]);
                }
            }

            $supplier->update($validatedSupplier);

            if (!empty($incomingBanks)) {
                // update existing by bank_name, create if not exists
                $this->supplierBankService->upsertByBankName($supplier, $incomingBanks);
            }

            DB::commit();

            return ResponseHelper::success(
                $supplier->load('banks'),
                "Supplier updated successfully",
                200
            );
        } catch (ValidationException $ve) {
            DB::rollBack();
            return ResponseHelper::validation($ve->errors(), "Validation Error");
        } catch (Exception $e) {
            DB::rollBack();
            return ResponseHelper::error("Failed updating supplier", 500, $e->getMessage());
        }
    }

public function importSupplier(Request $request)
{
    DB::beginTransaction();

    try {
        $validated = $request->validate([
            'supplier_file' => 'required|file|mimes:xlsx,csv|max:5120',
        ]);

        $import = new SuppliersImport();

        Excel::import($import, $validated['supplier_file']);

        SupplierImportHistory::create([
            'filename'       => $validated['supplier_file']->getClientOriginalName(),
            'size'           => $validated['supplier_file']->getSize(),
            'uploaded_by'    => Auth::id(),
            'total_uploaded' => $import->getImportedCount(),
            'uploaded_at'    => now(),
        ]);

        DB::commit();

        return ResponseHelper::success([
            'total' => $import->getImportedCount(),
            'failures' => collect($import->failures())->map(function ($f) {
                return [
                    'row' => $f->row(),
                    'attribute' => $f->attribute(),
                    'errors' => $f->errors(),
                    'values' => $f->values(),
                ];
            })->values(),
        ], 'Supplier imported successfully', 201);
    } catch (ValidationException $e) {
        DB::rollBack();
        return ResponseHelper::validation($e->errors(), 'Import validation failed');
    } catch (Throwable $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Import failed',
            'error'   => $e->getMessage(),
        ], 500);
    }
}


    public function getImportHistories(Request $request)
    {
        try {
            $histories = $this->supplierImportQueryBuilder->supplierImportBuilder($request);
            return ResponseHelper::success($histories, 'Import histories retrieved successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error('Error fetching import histories', 500, $e->getMessage());
        }
    }

    
}
