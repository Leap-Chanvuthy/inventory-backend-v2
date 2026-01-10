<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierImportHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'filename',
        'size',
        'uploaded_by',
        'total_uploaded',
        'uploaded_at',
    ];

    protected $dates = ['uploaded_at'];

    public function user()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

