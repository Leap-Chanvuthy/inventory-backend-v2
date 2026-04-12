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
                // Enable searching/sorting by category name
            ],

            selects: [
                'audits.*',
            ],

            allowedFilters: [
                AllowedFilter::exact('id'),
                AllowedFilter::exact('auditable_type'),
                AllowedFilter::exact('auditable_id'),

                // Search by event / auditable type / auditable id
                AllowedFilter::callback('search', function (Builder $query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('audits.event', 'LIKE', "%{$value}%")
                          ->orWhere('audits.auditable_type', 'LIKE', "%{$value}%")
                          ->orWhere('audits.auditable_id', 'LIKE', "%{$value}%");
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