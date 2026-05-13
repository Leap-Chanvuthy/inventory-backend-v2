<?php

namespace App\Service;

use App\Service\KPI\AuditLogKPI;
use App\Service\KPI\CategoryKPI;
use App\Service\KPI\CustomerKPI;
use App\Service\KPI\ProductKPI;
use App\Service\KPI\RawMaterialKPI;
use App\Service\KPI\SaleOrderKPI;
use App\Service\KPI\SupplierKPI;
use App\Service\KPI\UOMKPI;
use App\Service\KPI\UserKPI;
use App\Service\KPI\WarehouseKPI;
use App\Service\KPI\Support\KPIPeriodResolver;

class InventoryDashboardService
{
    public function __construct(
        protected KPIPeriodResolver $periodResolver,
        protected UserKPI $userKPI,
        protected AuditLogKPI $auditLogKPI,
        protected SupplierKPI $supplierKPI,
        protected RawMaterialKPI $rawMaterialKPI,
        protected ProductKPI $productKPI,
        protected WarehouseKPI $warehouseKPI,
        protected UOMKPI $uomKPI,
        protected CustomerKPI $customerKPI,
        protected CategoryKPI $categoryKPI,
        protected SaleOrderKPI $saleOrderKPI,
    ) {
    }

    public function summary(array $filters): array
    {
        $period = $this->periodResolver->resolve($filters);

        $normalizedFilters = array_merge($filters, [
            'start_date' => $period['start_date'],
            'end_date' => $period['end_date'],
            '__period' => $period,
        ]);

        $summary = [
            'users' => $this->userKPI->summary($normalizedFilters),
            'audit_logs' => $this->auditLogKPI->summary($normalizedFilters),
            'suppliers' => $this->supplierKPI->summary($normalizedFilters),
            'raw_materials' => $this->rawMaterialKPI->summary($normalizedFilters),
            'products' => $this->productKPI->summary($normalizedFilters),
            'warehouses' => $this->warehouseKPI->summary($normalizedFilters),
            'uoms' => $this->uomKPI->summary($normalizedFilters),
            'customers' => $this->customerKPI->summary($normalizedFilters),
            'categories' => $this->categoryKPI->summary($normalizedFilters),
            'sale_orders' => $this->saleOrderKPI->summary($normalizedFilters),
        ];

        return [
            'filters' => [
                'start_date' => $period['start_date'],
                'end_date' => $period['end_date'],
                'warehouse_id' => $filters['warehouse_id'] ?? null,
                'supplier_id' => $filters['supplier_id'] ?? null,
                'customer_id' => $filters['customer_id'] ?? null,
                'status' => $filters['status'] ?? null,
                'user_id' => $filters['user_id'] ?? null,
            ],
            'comparison_period' => [
                'previous_start_date' => $period['previous_start_date'],
                'previous_end_date' => $period['previous_end_date'],
            ],
            'summary' => $summary,
            'unavailable_metrics' => $this->collectUnavailableMetrics($summary),
        ];
    }

    protected function collectUnavailableMetrics(array $summary): array
    {
        $list = [];

        foreach ($summary as $module => $payload) {
            foreach (($payload['unavailable_metrics'] ?? []) as $metric) {
                $list[] = [
                    'module' => $module,
                    'metric' => $metric['metric'] ?? null,
                    'reason' => $metric['reason'] ?? null,
                ];
            }
        }

        return $list;
    }
}
