<?php

namespace App\QueryBuilders;

use Spatie\QueryBuilder\AllowedFilter;
use Illuminate\Database\Eloquent\Builder;
use App\Helpers\QueryBuilderHelper;
use App\Models\SupplierImportHistory;
use Illuminate\Http\Request;

class SupplierImportQuery
{
    public function supplierImportBuilder(Request $request)
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        return QueryBuilderHelper::build(
            model: SupplierImportHistory::class,

            joins: [],
            selects: [
                'supplier_import_histories.id',
                'supplier_import_histories.filename',
                'supplier_import_histories.size',
                'supplier_import_histories.uploaded_by',
                'supplier_import_histories.total_uploaded',
                'supplier_import_histories.uploaded_at',
                'supplier_import_histories.created_at',
                'supplier_import_histories.updated_at',
            ],

            allowedFilters: [
                AllowedFilter::exact('id'),
                AllowedFilter::callback('search', function (Builder $query, $value) {
                    $value = trim((string) $value);

                    $query->where(function (Builder $q) use ($value) {
                        // search filename too (optional but useful)
                        $q->where('supplier_import_histories.filename', 'LIKE', "%{$value}%");

                        // search uploader by name (and email if you want)
                        $q->orWhereHas('user', function (Builder $uq) use ($value) {
                            $uq->where('name', 'LIKE', "%{$value}%")
                               ->orWhere('email', 'LIKE', "%{$value}%");
                        });

                        // keep id search (optional)
                        if (is_numeric($value)) {
                            $q->orWhere('supplier_import_histories.uploaded_by', (int) $value);
                        }
                    });
                }),
            ],

            allowedSorts: [
                'uploaded_by',
                'created_at',
                'updated_at',
            ],

            defaultSort: '-created_at',
            withRelations: ['user'],
            withCounts: ['user'],
        )
        ->paginate($perPage)
        ->appends($request->query());
        ;
    }
}