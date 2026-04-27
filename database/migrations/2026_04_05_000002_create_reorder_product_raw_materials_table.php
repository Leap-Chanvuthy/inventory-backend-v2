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
        if (Schema::hasTable('reorder_product_raw_materials')) {
            return;
        }

        Schema::create('reorder_product_raw_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_reorder_id')->constrained('product_reorders')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('raw_material_id')->constrained('raw_materials')->restrictOnDelete()->cascadeOnUpdate();
            $table->decimal('quantity_per_unit', 15, 4)->default(0);
            $table->decimal('scrap_percentage', 5, 2)->default(0);
            // Legacy compatibility for code paths still reading/writing `quantity`.
            $table->decimal('quantity', 15, 4)->default(0);
            $table->timestamps();

            $table->index(['product_reorder_id', 'raw_material_id'], 'idx_rprm_reorder_raw');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reorder_product_raw_materials');
    }
};
