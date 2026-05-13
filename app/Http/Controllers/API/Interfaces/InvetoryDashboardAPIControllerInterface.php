<?php

namespace App\Http\Controllers\API\Interfaces;

use App\Http\Requests\InventoryDashboardSummaryRequest;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *   name="Inventory Dashboard",
 *   description="Inventory summary dashboard KPIs with period comparison, trend metrics, charts, and top tables."
 * )
 *
 * @OA\Schema(
 *   schema="InventoryDashboardTrendMetric",
 *   type="object",
 *   required={"current","previous","change","percentage_change","percentage_change_display","direction"},
 *   @OA\Property(property="current", type="number", example=120),
 *   @OA\Property(property="previous", type="number", example=100),
 *   @OA\Property(property="change", type="number", example=20),
 *   @OA\Property(property="percentage_change", type="number", nullable=true, example=20),
 *   @OA\Property(property="percentage_change_display", type="string", nullable=true, example="+20.00%"),
 *   @OA\Property(property="direction", type="string", enum={"up","down","neutral"}, example="up")
 * )
 *
 * @OA\Schema(
 *   schema="InventoryDashboardUnavailableMetric",
 *   type="object",
 *   required={"metric","reason"},
 *   @OA\Property(property="metric", type="string", example="top_10_suppliers_by_revenue"),
 *   @OA\Property(property="reason", type="string", example="Purchase order or supplier invoice table was not found.")
 * )
 *
 * @OA\Schema(
 *   schema="InventoryDashboardGlobalUnavailableMetric",
 *   type="object",
 *   required={"module","metric","reason"},
 *   @OA\Property(property="module", type="string", example="suppliers"),
 *   @OA\Property(property="metric", type="string", example="top_10_suppliers_by_revenue"),
 *   @OA\Property(property="reason", type="string", example="Purchase order or supplier invoice table was not found.")
 * )
 *
 * @OA\Schema(
 *   schema="InventoryDashboardChartPoint",
 *   type="object",
 *   @OA\Property(property="date", type="string", example="2026-02-01"),
 *   @OA\Property(property="label", type="string", example="2026-02-01"),
 *   @OA\Property(property="value", type="number", example=15)
 * )
 *
 * @OA\Schema(
 *   schema="InventoryDashboardKPIModule",
 *   type="object",
 *   required={"metrics","charts","tables","unavailable_metrics"},
 *   @OA\Property(property="metrics", type="object"),
 *   @OA\Property(property="charts", type="object"),
 *   @OA\Property(property="tables", type="object"),
 *   @OA\Property(
 *     property="unavailable_metrics",
 *     type="array",
 *     @OA\Items(ref="#/components/schemas/InventoryDashboardUnavailableMetric")
 *   )
 * )
 *
 * @OA\Schema(
 *   schema="InventoryDashboardSummaryResponse",
 *   type="object",
 *   required={"success","message","data"},
 *   @OA\Property(property="success", type="boolean", example=true),
 *   @OA\Property(property="message", type="string", example="Inventory dashboard summary retrieved successfully."),
 *   @OA\Property(
 *     property="data",
 *     type="object",
 *     required={"filters","comparison_period","summary","unavailable_metrics"},
 *     @OA\Property(
 *       property="filters",
 *       type="object",
 *       @OA\Property(property="start_date", type="string", format="date", example="2026-02-01"),
 *       @OA\Property(property="end_date", type="string", format="date", example="2026-02-28"),
 *       @OA\Property(property="warehouse_id", type="integer", nullable=true, example=1),
 *       @OA\Property(property="supplier_id", type="integer", nullable=true, example=null),
 *       @OA\Property(property="customer_id", type="integer", nullable=true, example=null),
 *       @OA\Property(property="status", type="string", nullable=true, example="COMPLETED"),
 *       @OA\Property(property="user_id", type="integer", nullable=true, example=null)
 *     ),
 *     @OA\Property(
 *       property="comparison_period",
 *       type="object",
 *       @OA\Property(property="previous_start_date", type="string", format="date", example="2026-01-04"),
 *       @OA\Property(property="previous_end_date", type="string", format="date", example="2026-01-31")
 *     ),
 *     @OA\Property(
 *       property="summary",
 *       type="object",
 *       @OA\Property(property="users", ref="#/components/schemas/InventoryDashboardKPIModule"),
 *       @OA\Property(property="audit_logs", ref="#/components/schemas/InventoryDashboardKPIModule"),
 *       @OA\Property(property="suppliers", ref="#/components/schemas/InventoryDashboardKPIModule"),
 *       @OA\Property(property="raw_materials", ref="#/components/schemas/InventoryDashboardKPIModule"),
 *       @OA\Property(property="products", ref="#/components/schemas/InventoryDashboardKPIModule"),
 *       @OA\Property(property="warehouses", ref="#/components/schemas/InventoryDashboardKPIModule"),
 *       @OA\Property(property="uoms", ref="#/components/schemas/InventoryDashboardKPIModule"),
 *       @OA\Property(property="customers", ref="#/components/schemas/InventoryDashboardKPIModule"),
 *       @OA\Property(property="categories", ref="#/components/schemas/InventoryDashboardKPIModule"),
 *       @OA\Property(property="sale_orders", ref="#/components/schemas/InventoryDashboardKPIModule")
 *     ),
 *     @OA\Property(
 *       property="unavailable_metrics",
 *       type="array",
 *       @OA\Items(ref="#/components/schemas/InventoryDashboardGlobalUnavailableMetric")
 *     )
 *   )
 * )
 */
interface InvetoryDashboardAPIControllerInterface
{
    /**
     * Get inventory dashboard KPI summary with current/previous period trend comparison.
     *
     * @OA\Get(
     *   path="/api/v2/inventory-dashboard/summary",
     *   tags={"Inventory Dashboard"},
     *   security={{"Bearer":{}}},
     *   summary="Inventory Dashboard Summary",
     *   description="Returns KPI summary modules with trend metrics, time-series charts, top 10 tables, and unavailable metric reasons.",
     *   @OA\Parameter(
     *     name="start_date",
     *     in="query",
     *     required=false,
     *     description="Filter start date (YYYY-MM-DD).",
     *     @OA\Schema(type="string", format="date", example="2026-01-01")
     *   ),
     *   @OA\Parameter(
     *     name="end_date",
     *     in="query",
     *     required=false,
     *     description="Filter end date (YYYY-MM-DD). Must be >= start_date.",
     *     @OA\Schema(type="string", format="date", example="2026-12-31")
     *   ),
     *   @OA\Parameter(
     *     name="warehouse_id",
     *     in="query",
     *     required=false,
     *     @OA\Schema(type="integer")
     *   ),
     *   @OA\Parameter(
     *     name="supplier_id",
     *     in="query",
     *     required=false,
     *     @OA\Schema(type="integer")
     *   ),
     *   @OA\Parameter(
     *     name="customer_id",
     *     in="query",
     *     required=false,
     *     @OA\Schema(type="integer")
     *   ),
     *   @OA\Parameter(
     *     name="status",
     *     in="query",
     *     required=false,
     *     description="Optional status filter mapped per module where applicable.",
     *     @OA\Schema(type="string")
     *   ),
     *   @OA\Parameter(
     *     name="user_id",
     *     in="query",
     *     required=false,
     *     @OA\Schema(type="integer")
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Dashboard summary retrieved successfully",
     *     @OA\JsonContent(ref="#/components/schemas/InventoryDashboardSummaryResponse")
     *   ),
     *   @OA\Response(
     *     response=401,
     *     description="Unauthenticated",
     *     @OA\JsonContent(
     *       @OA\Property(property="status", type="boolean", example=false),
     *       @OA\Property(property="message", type="string", example="Unauthenticated.")
     *     )
     *   ),
     *   @OA\Response(
     *     response=403,
     *     description="Forbidden",
     *     @OA\JsonContent(
     *       @OA\Property(property="status", type="boolean", example=false),
     *       @OA\Property(property="message", type="string", example="This action is unauthorized.")
     *     )
     *   ),
     *   @OA\Response(
     *     response=422,
     *     description="Validation error or invalid date range",
     *     @OA\JsonContent(
     *       @OA\Property(property="status", type="boolean", example=false),
     *       @OA\Property(property="message", type="string", example="The end_date must be after or equal to start_date."),
     *       @OA\Property(
     *         property="errors",
     *         type="object",
     *         @OA\Property(
     *           property="date_range",
     *           type="array",
     *           @OA\Items(type="string", example="The end_date must be after or equal to start_date.")
     *         )
     *       )
     *     )
     *   )
     * )
     */
    public function summary(InventoryDashboardSummaryRequest $request): JsonResponse;
}
