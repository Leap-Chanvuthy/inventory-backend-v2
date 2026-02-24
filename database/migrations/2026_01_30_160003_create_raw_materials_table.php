<?php

use App\Enums\ProductionMethodEnum;
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
        Schema::create('raw_materials', function (Blueprint $table) {
            $table->id();

            $table -> string('material_name' , 50);
            $table -> string('material_sku_code' , 255) -> unique();
            $table->  string('barcode')->nullable();
            $table -> double('minimum_stock_level');

            // $table -> date('expiry_date');
            $table -> text('description') -> nullable();
            $table -> string('production_method')->default(ProductionMethodEnum::FIFO -> value);

            $table -> foreignId('raw_material_category_id') ->constrained('raw_material_categories')  -> restrictOnDelete() -> cascadeOnUpdate();
            $table -> foreignId('uom_id') -> constrained('unit_of_measurements') -> restrictOnDelete() -> cascadeOnUpdate();
            $table -> foreignId('supplier_id') -> constrained('suppliers') -> restrictOnDelete() -> cascadeOnUpdate();
            $table -> foreignId('warehouse_id') -> constrained('warehouses') -> restrictOnDelete() -> cascadeOnUpdate();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('raw_materials');
    }
};
