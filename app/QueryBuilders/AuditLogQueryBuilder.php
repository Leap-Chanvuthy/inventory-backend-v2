<?php

namespace App\QueryBuilders;


use App\Helpers\QueryBuilderHelper;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Illuminate\Database\Eloquent\Builder;
use OwenIt\Auditing\Models\Audit;

class AuditLogQueryBuilder
{

    // implement audt log query builder here, similar to other query builders. This will be used in the controller to fetch audit logs with filtering/sorting/pagination.
    public function auditBuilder(Request $request)
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        return QueryBuilderHelper::build(
            model: Audit::class,

            joins: [
                ['users', 'audits.user_id', '=', 'users.id'],
            ],

            selects: [
                'audits.*',
            ],

            allowedFilters: [
                AllowedFilter::exact('id'),
                AllowedFilter::exact('user_id'),
                AllowedFilter::exact('event'),
                AllowedFilter::exact('auditable_type'),
                AllowedFilter::exact('auditable_id'),

                // Frontend action filter maps to event text in audits table
                AllowedFilter::callback('action', function (Builder $query, $value) {
                    if (!$value) {
                        return;
                    }

                    $query->whereRaw('LOWER(audits.event) LIKE ?', ['%' . strtolower((string) $value) . '%']);
                }),

                AllowedFilter::callback('date_from', function (Builder $query, $value) {
                    if (!$value) {
                        return;
                    }

                    $query->whereDate('audits.created_at', '>=', $value);
                }),

                AllowedFilter::callback('date_to', function (Builder $query, $value) {
                    if (!$value) {
                        return;
                    }

                    $query->whereDate('audits.created_at', '<=', $value);
                }),

                // Search by event / auditable type / auditable id / user name / user email
                AllowedFilter::callback('search', function (Builder $query, $value) {
                    $value = trim((string) $value);
                    if ($value === '') {
                        return;
                    }

                    $query->where(function ($q) use ($value) {
                        $q->where('audits.event', 'LIKE', "%{$value}%")
                          ->orWhere('audits.auditable_type', 'LIKE', "%{$value}%")
                          ->orWhereRaw('CAST(audits.auditable_id AS CHAR) LIKE ?', ["%{$value}%"])
                          ->orWhere('users.name', 'LIKE', "%{$value}%")
                          ->orWhere('users.email', 'LIKE', "%{$value}%");
                    });
                }),

                // Optional: dedicated filter e.g. ?filter[category_name]=Retail
                // AllowedFilter::callback('category_name', function (Builder $query, $value) {

                // }),
            ],

            allowedSorts: [
                'created_at',
                'updated_at',
                'event',
                'auditable_type',
                'auditable_id',
            ],

            defaultSort: '-created_at',
            withRelations: [
                'user',
            ],
            
        )
        ->paginate($perPage)
        ->appends($request->query());
    }
}
