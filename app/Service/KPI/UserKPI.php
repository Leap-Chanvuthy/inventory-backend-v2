<?php

namespace App\Service\KPI;

use App\Models\User;

class UserKPI extends BaseKPI
{
    public function summary(array $filters): array
    {
        $payload = $this->basePayload();
        $period = $this->resolvePeriod($filters);
        $table = (new User())->getTable();

        $baseQuery = User::query();
        $this->applyExactFilterIfColumnExists($baseQuery, $table, $filters, 'user_id', 'id');
        $this->applyStatusFilterIfColumnExists($baseQuery, $table, $filters);

        $currentTotal = (clone $baseQuery)->where($table . '.created_at', '<=', $period['current_end'])->count($table . '.id');
        $previousTotal = (clone $baseQuery)->where($table . '.created_at', '<=', $period['previous_end'])->count($table . '.id');

        $currentNewQuery = clone $baseQuery;
        $this->applyCurrentPeriod($currentNewQuery, $filters, $table . '.created_at');
        $currentNew = $currentNewQuery->count($table . '.id');

        $previousNewQuery = clone $baseQuery;
        $this->applyPreviousPeriod($previousNewQuery, $filters, $table . '.created_at');
        $previousNew = $previousNewQuery->count($table . '.id');

        $payload['metrics']['total_users'] = $this->buildTrendMetric($currentTotal, $previousTotal);
        $payload['metrics']['new_users_in_period'] = $this->buildTrendMetric($currentNew, $previousNew);

        if ($this->hasColumn($table, 'is_active') || $this->hasColumn($table, 'status')) {
            if ($this->hasColumn($table, 'is_active')) {
                $currentActive = (clone $baseQuery)
                    ->where($table . '.created_at', '<=', $period['current_end'])
                    ->where($table . '.is_active', true)
                    ->count($table . '.id');
                $previousActive = (clone $baseQuery)
                    ->where($table . '.created_at', '<=', $period['previous_end'])
                    ->where($table . '.is_active', true)
                    ->count($table . '.id');
            } else {
                $currentActive = (clone $baseQuery)
                    ->where($table . '.created_at', '<=', $period['current_end'])
                    ->where($table . '.status', 'active')
                    ->count($table . '.id');
                $previousActive = (clone $baseQuery)
                    ->where($table . '.created_at', '<=', $period['previous_end'])
                    ->where($table . '.status', 'active')
                    ->count($table . '.id');
            }

            $payload['metrics']['active_users'] = $this->buildTrendMetric($currentActive, $previousActive);
        } else {
            $this->addUnavailableMetric($payload, 'active_users', 'No user status/is_active column found.');
        }

        if ($this->hasColumn($table, 'role')) {
            $currentRolesQuery = clone $baseQuery;
            $this->applyCurrentPeriod($currentRolesQuery, $filters, $table . '.created_at');
            $currentRoles = $currentRolesQuery
                ->selectRaw($table . '.role as label, COUNT(*) as total')
                ->groupBy($table . '.role')
                ->get();

            $previousRolesQuery = clone $baseQuery;
            $this->applyPreviousPeriod($previousRolesQuery, $filters, $table . '.created_at');
            $previousRoles = $previousRolesQuery
                ->selectRaw($table . '.role as label, COUNT(*) as total')
                ->groupBy($table . '.role')
                ->get()
                ->keyBy('label');

            $payload['tables']['users_by_role'] = $currentRoles->map(function ($row) use ($previousRoles) {
                $previous = (int) ($previousRoles[$row->label]->total ?? 0);

                return [
                    'role' => (string) $row->label,
                    'trend' => $this->buildTrendMetric((int) $row->total, $previous),
                ];
            })->values()->all();
        }

        $payload['charts']['users_trend_overtime'] = $this->buildTimeSeries(
            query: User::query(),
            dateColumn: $table . '.created_at',
            aggregateType: 'count',
            filters: $filters,
            valueKey: 'users_count',
        );

        return $payload;
    }
}
