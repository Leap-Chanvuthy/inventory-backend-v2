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

            $table->foreignId('raw_material_category_id')
                ->constrained('raw_material_categories')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('supplier_id')
                ->constrained('suppliers')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            // UOM columns — stock always stored in base_uom unit
            $table->foreignId('base_uom_id')
                ->comment('Base unit used for storing stock quantities.')
                ->constrained('unit_of_measurements')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->unsignedBigInteger('purchase_uom_id')
                ->nullable()
                ->comment('Unit used on purchase orders. Falls back to base_uom if null.');

            $table->foreign('purchase_uom_id', 'fk_raw_materials_purchase_uom_id')
                ->references('id')
                ->on('unit_of_measurements')
                ->nullOnDelete()
                ->cascadeOnUpdate();

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
