<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('unit_of_measurements', function (Blueprint $table) {
            $table->id();
            $table -> string('uom_code')->unique();
            $table -> string('name')->unique();
            $table -> string('symbol')->nullable();
            $table -> string('uom_type');
            $table -> string('description')->nullable();
            $table -> boolean('is_active')->default(true);
            $table -> timestamps();
            $table -> softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_of_measurements');
    }
};
