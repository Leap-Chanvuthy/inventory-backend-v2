<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_import_histories', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->integer('size'); // in bytes
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->integer('total_uploaded')->default(0);
            $table->timestamp('uploaded_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_import_histories');
    }
};
