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
        // This is the pivot table for the many-to-many relationship between products and raw materials
        Schema::create('product_raw_material', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('raw_material_id')->constrained('raw_materials')->restrictOnDelete()->cascadeOnUpdate();
            $table->decimal('quantity_per_unit', 15, 4)->default(0);
            $table->decimal('scrap_percentage', 5, 2)->default(0);
            // Legacy compatibility for code paths still reading/writing `quantity`.
            $table->decimal('quantity', 15, 4)->default(0)->min(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_raw_material');
    }
};
