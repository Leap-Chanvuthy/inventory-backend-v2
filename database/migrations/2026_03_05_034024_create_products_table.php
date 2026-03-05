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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('product_name', 255);
            $table->string('product_sku_code', 255)->unique();
            $table->string('barcode')->nullable();
            $table->text('product_description')->nullable();

            // Relationships
            $table -> foreignId('product_category_id') ->constrained('product_categories')  -> restrictOnDelete() -> cascadeOnUpdate();
            $table -> foreignId('uom_id') -> constrained('unit_of_measurements') -> restrictOnDelete() -> cascadeOnUpdate();
            $table -> foreignId('supplier_id') -> constrained('suppliers') -> restrictOnDelete() -> cascadeOnUpdate();
            $table -> foreignId('warehouse_id') -> constrained('warehouses') -> restrictOnDelete() -> cascadeOnUpdate();
            
            // timestamps
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
