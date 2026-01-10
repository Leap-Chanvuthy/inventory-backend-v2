<?php

namespace App\Exports;

use App\Models\AppModelsSupplier;
use Maatwebsite\Excel\Concerns\FromCollection;

class SuppliersExport implements FromCollection
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return AppModelsSupplier::all();
    }
}
