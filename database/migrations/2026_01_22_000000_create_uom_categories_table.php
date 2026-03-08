<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UOM Categories Migration
 *
 * Groups units into logical categories (Quantity, Weight, Volume, etc.)
 * to prevent invalid cross-category conversions (e.g. Kg → Litre).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uom_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uom_categories');
    }
};
