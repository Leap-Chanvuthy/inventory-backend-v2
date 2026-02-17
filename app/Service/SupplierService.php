<?php

namespace App\Service;


use App\Helpers\FileUploadHelper;
use App\Helpers\ResponseHelper;
use App\Imports\SuppliersImport;
use App\Enums\SupplierCategoryEnum;
use App\Models\Supplier;
use App\Models\RMStockMovement;
use App\Models\RawMaterial;
use App\Enums\RawMaterialStockMovementTypeEnum;
use App\Models\SupplierImportHistory;
use App\QueryBuilders\SupplierImportQuery;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\QueryBuilders\SupplierQueryBuilder;
use App\Validations\SupplierBankValidation;
use App\Validations\SupplierValidation;
use Carbon\Carbon;
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

            // Gather raw material IDs owned by this supplier
                $rawMaterialIds = $supplier->raw_materials()->withTrashed()->pluck('id')->toArray();

            // Prepare default statistics
            $statistics = [
                'total_raw_materials' => count($rawMaterialIds),
                'financials' => [
                    'total_spend_usd' => 0.0,
                    'total_spend_khr' => 0.0,
                    'average_unit_price_usd' => 0.0,
                ],
                'counts' => [
                    'purchase_count' => 0,
                    'reorder_count' => 0,
                ],
                'top_raw_materials' => [],
            ];

            if (!empty($rawMaterialIds)) {
                // Base query for purchase + reorder movements for this supplier
                $movementTypes = [
                    RawMaterialStockMovementTypeEnum::PURCHASE->value,
                    RawMaterialStockMovementTypeEnum::RE_ORDER->value,
                ];

                // Totals
                $totalSpendUsd = (float) RMStockMovement::whereIn('raw_material_id', $rawMaterialIds)
                    ->whereIn('movement_type', $movementTypes)
                    ->sum('total_value_in_usd');

                $totalSpendKhr = (float) RMStockMovement::whereIn('raw_material_id', $rawMaterialIds)
                    ->whereIn('movement_type', $movementTypes)
                    ->sum('total_value_in_riel');

                $avgExchangeRate = (float) RMStockMovement::whereIn('raw_material_id', $rawMaterialIds)
                    ->whereIn('movement_type', $movementTypes)
                    ->avg('exchange_rate_from_usd_to_riel');

                $avgUnitPriceUsd = (float) RMStockMovement::whereIn('raw_material_id', $rawMaterialIds)
                    ->whereIn('movement_type', $movementTypes)
                    ->avg('unit_price_in_usd') ?? 0.0;

                // Counts
                $purchaseCount = (int) RMStockMovement::whereIn('raw_material_id', $rawMaterialIds)
                    ->where('movement_type', RawMaterialStockMovementTypeEnum::PURCHASE->value)
                    ->count();

                $reorderCount = (int) RMStockMovement::whereIn('raw_material_id', $rawMaterialIds)
                    ->where('movement_type', RawMaterialStockMovementTypeEnum::RE_ORDER->value)
                    ->count();

                // Supply trend: compare this month vs last month for spend and quantity
                $now = Carbon::now();
                $startOfThisMonth = $now->copy()->startOfMonth();
                $endOfThisMonth = $now->copy()->endOfMonth();
                $startOfLastMonth = $now->copy()->subMonthNoOverflow()->startOfMonth();
                $endOfLastMonth = $now->copy()->subMonthNoOverflow()->endOfMonth();

                $thisMonthSpend = (float) RMStockMovement::whereIn('raw_material_id', $rawMaterialIds)
                    ->whereIn('movement_type', $movementTypes)
                    ->whereBetween('movement_date', [$startOfThisMonth, $endOfThisMonth])
                    ->sum('total_value_in_usd');

                $lastMonthSpend = (float) RMStockMovement::whereIn('raw_material_id', $rawMaterialIds)
                    ->whereIn('movement_type', $movementTypes)
                    ->whereBetween('movement_date', [$startOfLastMonth, $endOfLastMonth])
                    ->sum('total_value_in_usd');

                $thisMonthQty = (float) RMStockMovement::whereIn('raw_material_id', $rawMaterialIds)
                    ->whereIn('movement_type', $movementTypes)
                    ->whereBetween('movement_date', [$startOfThisMonth, $endOfThisMonth])
                    ->sum('quantity');

                $lastMonthQty = (float) RMStockMovement::whereIn('raw_material_id', $rawMaterialIds)
                    ->whereIn('movement_type', $movementTypes)
                    ->whereBetween('movement_date', [$startOfLastMonth, $endOfLastMonth])
                    ->sum('quantity');

                $spendDelta = $thisMonthSpend - $lastMonthSpend;
                $qtyDelta = $thisMonthQty - $lastMonthQty;

                $spendPercent = $lastMonthSpend == 0 ? ($thisMonthSpend > 0 ? 100.0 : 0.0) : round(($spendDelta / $lastMonthSpend) * 100, 2);
                $qtyPercent = $lastMonthQty == 0 ? ($thisMonthQty > 0 ? 100.0 : 0.0) : round(($qtyDelta / $lastMonthQty) * 100, 2);

                $spendDirection = $spendDelta > 0 ? 'up' : ($spendDelta < 0 ? 'down' : 'flat');
                $qtyDirection = $qtyDelta > 0 ? 'up' : ($qtyDelta < 0 ? 'down' : 'flat');

                $statistics['supply_trend'] = [
                    'period' => 'month',
                    'this_period' => [
                        'start' => $startOfThisMonth->toDateString(),
                        'end' => $endOfThisMonth->toDateString(),
                        'total_spend_usd' => $thisMonthSpend,
                        'total_quantity' => $thisMonthQty,
                    ],
                    'previous_period' => [
                        'start' => $startOfLastMonth->toDateString(),
                        'end' => $endOfLastMonth->toDateString(),
                        'total_spend_usd' => $lastMonthSpend,
                        'total_quantity' => $lastMonthQty,
                    ],
                    'delta' => [
                        'spend_usd' => $spendDelta,
                        'quantity' => $qtyDelta,
                    ],
                    'percent' => [
                        'spend_usd' => $spendPercent,
                        'quantity' => $qtyPercent,
                    ],
                    'direction' => [
                        'spend_usd' => $spendDirection,
                        'quantity' => $qtyDirection,
                    ],
                ];

                // Top 5 raw materials by spend (USD), grouped at raw_material level
                $topBySpend = RMStockMovement::selectRaw('raw_material_id, SUM(COALESCE(total_value_in_usd,0)) as spend_usd, SUM(COALESCE(total_value_in_riel,0)) as spend_khr, SUM(COALESCE(quantity,0)) as total_qty')
                    ->whereIn('raw_material_id', $rawMaterialIds)
                    ->whereIn('movement_type', $movementTypes)
                    ->groupBy('raw_material_id')
                    ->orderByDesc('spend_usd')
                    ->limit(5)
                    ->get();

                $rawIds = $topBySpend->pluck('raw_material_id')->all();
                    $rawMap = RawMaterial::whereIn('id', $rawIds)->withTrashed()->with('uom')->get()->keyBy('id');

                $topRawMaterials = $topBySpend->map(function ($row) use ($rawMap) {
                    $rm = $rawMap[$row->raw_material_id] ?? null;
                    return [
                        'raw_material_id' => $row->raw_material_id,
                        'material_name' => $rm ? $rm->material_name : null,
                        'material_sku_code' => $rm ? $rm->material_sku_code : null,
                        'spend_usd' => (float) $row->spend_usd,
                        'spend_khr' => (float) $row->spend_khr,
                            'total_quantity' => (float) $row->total_qty,
                        'uom' => $rm && $rm->uom ? $rm->uom->toArray() : null,
                    ];
                })->values();

                $statistics['financials'] = [
                    'total_spend_usd' => $totalSpendUsd,
                    'total_spend_khr' => $totalSpendKhr,
                    'avarage_exchange_rate_usd_to_khr' => round($avgExchangeRate, 4),
                    'average_unit_price_usd' => round($avgUnitPriceUsd, 4),
                ];

                $statistics['counts'] = [
                    'purchase_count' => $purchaseCount,
                    'reorder_count' => $reorderCount,
                ];

                $statistics['top_raw_materials'] = $topRawMaterials;

                // Supply trend: compare this month vs last month (USD and KHR)
                $now = Carbon::now();
                $startOfThisMonth = $now->copy()->startOfMonth();
                $endOfThisMonth = $now->copy()->endOfMonth();
                $startOfLastMonth = $now->copy()->subMonthNoOverflow()->startOfMonth();
                $endOfLastMonth = $now->copy()->subMonthNoOverflow()->endOfMonth();

                $currentUsd = (float) RMStockMovement::whereIn('raw_material_id', $rawMaterialIds)
                    ->whereIn('movement_type', $movementTypes)
                    ->whereBetween('movement_date', [$startOfThisMonth, $endOfThisMonth])
                    ->sum('total_value_in_usd');

                $previousUsd = (float) RMStockMovement::whereIn('raw_material_id', $rawMaterialIds)
                    ->whereIn('movement_type', $movementTypes)
                    ->whereBetween('movement_date', [$startOfLastMonth, $endOfLastMonth])
                    ->sum('total_value_in_usd');

                $deltaUsd = $currentUsd - $previousUsd;
                $percentUsd = $previousUsd == 0 ? ($currentUsd > 0 ? 100.0 : 0.0) : round(($deltaUsd / $previousUsd) * 100, 2);
                $directionUsd = $deltaUsd > 0 ? 'up' : ($deltaUsd < 0 ? 'down' : 'flat');

                $currentKhr = (float) RMStockMovement::whereIn('raw_material_id', $rawMaterialIds)
                    ->whereIn('movement_type', $movementTypes)
                    ->whereBetween('movement_date', [$startOfThisMonth, $endOfThisMonth])
                    ->sum('total_value_in_riel');

                $previousKhr = (float) RMStockMovement::whereIn('raw_material_id', $rawMaterialIds)
                    ->whereIn('movement_type', $movementTypes)
                    ->whereBetween('movement_date', [$startOfLastMonth, $endOfLastMonth])
                    ->sum('total_value_in_riel');

                $deltaKhr = $currentKhr - $previousKhr;
                $percentKhr = $previousKhr == 0 ? ($currentKhr > 0 ? 100.0 : 0.0) : round(($deltaKhr / $previousKhr) * 100, 2);
                $directionKhr = $deltaKhr > 0 ? 'up' : ($deltaKhr < 0 ? 'down' : 'flat');

                // Distinct raw material counts for current and previous period
                $currentRawCount = (int) RMStockMovement::whereIn('raw_material_id', $rawMaterialIds)
                    ->whereIn('movement_type', $movementTypes)
                    ->whereBetween('movement_date', [$startOfThisMonth, $endOfThisMonth])
                    ->distinct()
                    ->count('raw_material_id');

                $previousRawCount = (int) RMStockMovement::whereIn('raw_material_id', $rawMaterialIds)
                    ->whereIn('movement_type', $movementTypes)
                    ->whereBetween('movement_date', [$startOfLastMonth, $endOfLastMonth])
                    ->distinct()
                    ->count('raw_material_id');

                $deltaRaw = $currentRawCount - $previousRawCount;
                $percentRaw = $previousRawCount == 0 ? ($currentRawCount > 0 ? 100.0 : 0.0) : round(($deltaRaw / $previousRawCount) * 100, 2);
                $directionRaw = $deltaRaw > 0 ? 'up' : ($deltaRaw < 0 ? 'down' : 'flat');

                $statistics['supply_trend'] = [
                    'period' => [
                        'this_month_start' => $startOfThisMonth->toDateString(),
                        'this_month_end' => $endOfThisMonth->toDateString(),
                        'last_month_start' => $startOfLastMonth->toDateString(),
                        'last_month_end' => $endOfLastMonth->toDateString(),
                    ],
                    'usd' => [
                        'current' => $currentUsd,
                        'previous' => $previousUsd,
                        'delta' => $deltaUsd,
                        'percent' => $percentUsd,
                        'direction' => $directionUsd,
                    ],
                    'khr' => [
                        'current' => $currentKhr,
                        'previous' => $previousKhr,
                        'delta' => $deltaKhr,
                        'percent' => $percentKhr,
                        'direction' => $directionKhr,
                    ],
                    'raw_materials' => [
                        'current' => $currentRawCount,
                        'previous' => $previousRawCount,
                        'delta' => $deltaRaw,
                        'percent' => $percentRaw,
                        'direction' => $directionRaw,
                    ],
                ];
            }

                // Do not include full raw_materials list here as requested; include banks only
                $result = [
                    'supplier' => $supplier->load('banks'),
                    'statistics' => $statistics,
                ];

            return ResponseHelper::success($result, "Supplier retrieved successfully", 200);
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

    public function deleteSupplier ($id){
        try {
            $supplier = Supplier::findOrFail($id);
            if (!$supplier) {
                return ResponseHelper::error("Supplier not found", 404, null);
            }

            $supplier->delete();

            return ResponseHelper::success(null, "Supplier deleted successfully", 200);
        } catch (Exception $e) {
            return ResponseHelper::error("Failed deleting supplier", 500, $e->getMessage());
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


    public function getSupplierStatistics()
    {
        try {
            $now = Carbon::now();

            // Baseline: total suppliers as of end of last month
            $endOfLastMonth = $now->copy()->subMonthNoOverflow()->endOfMonth();
            $totalSuppliers = Supplier::count();
            $totalSuppliersAsOfEndLastMonth = Supplier::where('created_at', '<=', $endOfLastMonth)->count();

            $deltaTotal = $totalSuppliers - $totalSuppliersAsOfEndLastMonth;
            $trendPercent = $totalSuppliersAsOfEndLastMonth === 0
                ? ($totalSuppliers > 0 ? 100.0 : 0.0)
                : round(($deltaTotal / $totalSuppliersAsOfEndLastMonth) * 100, 2);
            $trendDirection = $deltaTotal > 0 ? 'up' : ($deltaTotal < 0 ? 'down' : 'flat');

            // New suppliers: this month vs last month (more intuitive month-to-month KPI)
            $startOfThisMonth = $now->copy()->startOfMonth();
            $startOfLastMonth = $now->copy()->subMonthNoOverflow()->startOfMonth();
            $endOfLastMonthForNew = $now->copy()->subMonthNoOverflow()->endOfMonth();

            $newThisMonth = Supplier::where('created_at', '>=', $startOfThisMonth)->count();
            $newLastMonth = Supplier::whereBetween('created_at', [$startOfLastMonth, $endOfLastMonthForNew])->count();
            $deltaNew = $newThisMonth - $newLastMonth;
            $newTrendPercent = $newLastMonth === 0
                ? ($newThisMonth > 0 ? 100.0 : 0.0)
                : round(($deltaNew / $newLastMonth) * 100, 2);
            $newTrendDirection = $deltaNew > 0 ? 'up' : ($deltaNew < 0 ? 'down' : 'flat');

            // Totals by category (ensure all enum categories exist in output even if 0)
            $categoryCountsRaw = Supplier::query()
                ->selectRaw('supplier_category, COUNT(*) as total')
                ->groupBy('supplier_category')
                ->pluck('total', 'supplier_category')
                ->toArray();

            $totalByCategory = [];
            foreach (SupplierCategoryEnum::cases() as $case) {
                $key = (string) ($case->value ?? $case->name);
                $totalByCategory[$case->name] = (int) ($categoryCountsRaw[$key] ?? 0);
            }

            // Import statistics
            $totalImportHistories = SupplierImportHistory::count();
            $totalImportedFilesSizeBytes = (int) (SupplierImportHistory::sum('size') ?? 0);
            $totalImportedRows = (int) (SupplierImportHistory::sum('total_uploaded') ?? 0);
            $averageImportFileSizeBytes = $totalImportHistories > 0
                ? (int) round($totalImportedFilesSizeBytes / $totalImportHistories)
                : 0;
            $largestImportFileSizeBytes = (int) (SupplierImportHistory::max('size') ?? 0);

            // Charts (DB-agnostic grouping in PHP)
            $monthsBack = 12;
            $startWindow = $now->copy()->startOfMonth()->subMonthsNoOverflow($monthsBack - 1);
            $monthKeys = [];
            for ($i = 0; $i < $monthsBack; $i++) {
                $monthKeys[] = $startWindow->copy()->addMonthsNoOverflow($i)->format('Y-m');
            }

            $supplierCreatedRows = Supplier::query()
                ->where('created_at', '>=', $startWindow)
                ->get(['created_at']);

            $supplierCreatedByMonthMap = array_fill_keys($monthKeys, 0);
            foreach ($supplierCreatedRows as $row) {
                $key = Carbon::parse($row->created_at)->format('Y-m');
                if (array_key_exists($key, $supplierCreatedByMonthMap)) {
                    $supplierCreatedByMonthMap[$key] += 1;
                }
            }

            $suppliersCreatedByMonth = [];
            foreach ($supplierCreatedByMonthMap as $month => $total) {
                $suppliersCreatedByMonth[] = ['month' => $month, 'total' => $total];
            }

            $importRows = SupplierImportHistory::query()
                ->where('uploaded_at', '>=', $startWindow)
                ->get(['uploaded_at', 'size', 'total_uploaded']);

            $importsByMonthMap = array_fill_keys($monthKeys, [
                'total_imports' => 0,
                'total_uploaded' => 0,
                'total_size_bytes' => 0,
            ]);

            foreach ($importRows as $row) {
                $key = Carbon::parse($row->uploaded_at)->format('Y-m');
                if (!array_key_exists($key, $importsByMonthMap)) {
                    continue;
                }
                $importsByMonthMap[$key]['total_imports'] += 1;
                $importsByMonthMap[$key]['total_uploaded'] += (int) ($row->total_uploaded ?? 0);
                $importsByMonthMap[$key]['total_size_bytes'] += (int) ($row->size ?? 0);
            }

            $importsByMonth = [];
            foreach ($importsByMonthMap as $month => $data) {
                $importsByMonth[] = array_merge(['month' => $month], $data);
            }

            // Extra stats for charts: suppliers by province (top 10)
            $topProvinces = Supplier::query()
                ->selectRaw('province, COUNT(*) as total')
                ->whereNotNull('province')
                ->where('province', '!=', '')
                ->groupBy('province')
                ->orderByDesc('total')
                ->limit(10)
                ->get()
                ->map(fn ($r) => ['province' => $r->province, 'total' => (int) $r->total])
                ->values();

            $recentImports = SupplierImportHistory::query()
                ->orderByDesc('uploaded_at')
                ->limit(5)
                ->get(['id', 'filename', 'size', 'total_uploaded', 'uploaded_at']);

            $statistics = [
                'total_suppliers' => $totalSuppliers,
                'total_suppliers_as_of_end_last_month' => $totalSuppliersAsOfEndLastMonth,
                'total_suppliers_trend' => [
                    'delta' => $deltaTotal,
                    'percent' => $trendPercent,
                    'direction' => $trendDirection,
                ],
                'new_suppliers' => [
                    'this_month' => $newThisMonth,
                    'last_month' => $newLastMonth,
                    'trend' => [
                        'delta' => $deltaNew,
                        'percent' => $newTrendPercent,
                        'direction' => $newTrendDirection,
                    ],
                ],
                'total_by_category' => $totalByCategory,
                'imports' => [
                    'total_histories' => $totalImportHistories,
                    'total_uploaded_rows' => $totalImportedRows,
                    'total_files_size_bytes' => $totalImportedFilesSizeBytes,
                    'average_file_size_bytes' => $averageImportFileSizeBytes,
                    'largest_file_size_bytes' => $largestImportFileSizeBytes,
                    'recent' => $recentImports,
                ],
                'charts' => [
                    'suppliers_created_by_month' => $suppliersCreatedByMonth,
                    'imports_by_month' => $importsByMonth,
                    'top_provinces' => $topProvinces,
                ],
            ];

            return ResponseHelper::success($statistics, 'Supplier statistics retrieved successfully', 200);
        } catch (Exception $e) {
            return ResponseHelper::error('Error fetching supplier statistics', 500, $e->getMessage());
        }
    }

    
}
