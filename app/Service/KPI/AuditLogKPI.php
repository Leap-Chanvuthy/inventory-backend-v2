<?php

namespace App\Service\KPI;

use App\Models\User;
use OwenIt\Auditing\Models\Audit;

class AuditLogKPI extends BaseKPI
{
    public function summary(array $filters): array
    {
        $payload = $this->basePayload();
        $period = $this->resolvePeriod($filters);

        $auditTable = (new Audit())->getTable();
        $userTable = (new User())->getTable();

        $baseQuery = Audit::query();
        $this->applyExactFilterIfColumnExists($baseQuery, $auditTable, $filters, 'user_id', 'user_id');

        $currentTotal = (clone $baseQuery)->where($auditTable . '.created_at', '<=', $period['current_end'])->count($auditTable . '.id');
        $previousTotal = (clone $baseQuery)->where($auditTable . '.created_at', '<=', $period['previous_end'])->count($auditTable . '.id');

        $currentPeriodQuery = clone $baseQuery;
        $this->applyCurrentPeriod($currentPeriodQuery, $filters, $auditTable . '.created_at');
        $currentInPeriod = $currentPeriodQuery->count($auditTable . '.id');

        $previousPeriodQuery = clone $baseQuery;
        $this->applyPreviousPeriod($previousPeriodQuery, $filters, $auditTable . '.created_at');
        $previousInPeriod = $previousPeriodQuery->count($auditTable . '.id');

        $payload['metrics']['total_logs'] = $this->buildTrendMetric($currentTotal, $previousTotal);
        $payload['metrics']['logs_in_period'] = $this->buildTrendMetric($currentInPeriod, $previousInPeriod);

        $latestRows = Audit::query()
            ->leftJoin($userTable, $userTable . '.id', '=', $auditTable . '.user_id')
            ->selectRaw(
                $auditTable . '.id, ' .
                $auditTable . '.user_id, ' .
                $userTable . '.name as user_name, ' .
                $auditTable . '.event as activity, ' .
                $auditTable . '.auditable_type, ' .
                $auditTable . '.auditable_id, ' .
                $auditTable . '.created_at'
            )
            ->orderByDesc($auditTable . '.created_at')
            ->limit(10)
            ->get();

        $payload['tables']['latest_10_activities'] = $latestRows->map(function ($row) {
            return [
                'id' => (int) $row->id,
                'user_id' => $row->user_id !== null ? (int) $row->user_id : null,
                'user_name' => $row->user_name,
                'activity' => (string) $row->activity,
                'description' => sprintf(
                    '%s on %s#%s',
                    (string) $row->activity,
                    class_basename((string) $row->auditable_type),
                    (string) $row->auditable_id
                ),
                'created_at' => $row->created_at,
            ];
        })->all();

        $currentTopQuery = clone $baseQuery;
        $this->applyCurrentPeriod($currentTopQuery, $filters, $auditTable . '.created_at');
        $currentTopActivities = $currentTopQuery
            ->selectRaw($auditTable . '.event as activity, COUNT(*) as activity_count')
            ->groupBy($auditTable . '.event')
            ->orderByDesc('activity_count')
            ->limit(10)
            ->get();

        $previousTopQuery = clone $baseQuery;
        $this->applyPreviousPeriod($previousTopQuery, $filters, $auditTable . '.created_at');
        $previousTopActivities = $previousTopQuery
            ->selectRaw($auditTable . '.event as activity, COUNT(*) as activity_count')
            ->groupBy($auditTable . '.event')
            ->get()
            ->keyBy('activity');

        $payload['tables']['top_10_most_performed_activities'] = $currentTopActivities->map(function ($row) use ($previousTopActivities) {
            $previous = (int) ($previousTopActivities[$row->activity]->activity_count ?? 0);

            return [
                'activity' => (string) $row->activity,
                'count' => (int) $row->activity_count,
                'trend' => $this->buildTrendMetric((int) $row->activity_count, $previous),
            ];
        })->all();

        $payload['charts']['logs_trend_overtime'] = $this->buildTimeSeries(
            query: Audit::query(),
            dateColumn: $auditTable . '.created_at',
            aggregateType: 'count',
            filters: $filters,
            valueKey: 'logs_count',
        );

        return $payload;
    }
}
