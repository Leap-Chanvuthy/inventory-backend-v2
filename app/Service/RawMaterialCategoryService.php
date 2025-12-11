<?php 

namespace App\Service;

use App\Helpers\QueryBuilderHelper;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;


class RawMaterialCategoryService {

    public function rawMaterialBuilder(){
        return QueryBuilderHelper::build(
            model: \App\Models\RawMatrialCategory::class,
            joins: [],
            selects: [
                'raw_material_categories.id',
                'raw_material_categories.category_name',
                'raw_material_categories.label_color',
                'raw_material_categories.description',
                'raw_material_categories.created_at',
                'raw_material_categories.updated_at',
            ],
            allowedFilters: [
                AllowedFilter::exact('id'),
                AllowedFilter::exact('label_color'),

                AllowedFilter::callback('search', function (Builder $query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('raw_material_categories.category_name', 'LIKE', "%{$value}%");
                    });
                }),
            ],
            allowedSorts: [
                'created_at',
                'updated_at',
                'category_name',
            ],
        );
    }
    


}