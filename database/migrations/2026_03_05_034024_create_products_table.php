<?php

use App\Enums\SaleMethodEnum;
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
            $table -> enum('product_type', ['EXTERNAL_PURCHASED', 'INTERNAL_PRODUCED'])
                ->comment('Defines whether the product is externally purchased or internally produced.');

            $table->enum('sale_method', [
                SaleMethodEnum::FIFO->value,
                SaleMethodEnum::LIFO->value,
            ]) -> default(SaleMethodEnum::FIFO->value)
                ->comment('Defines the sale method for the product, either FIFO or LIFO. This determines how stock is allocated when fulfilling orders.');

            // Relationships
            $table->foreignId('product_category_id')
                ->constrained('product_categories')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('supplier_id')->nullable()
                ->constrained('suppliers')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            // UOM columns — stock always stored in base_uom unit
            $table->foreignId('base_uom_id')
                ->comment('Base (smallest) unit used for storing stock quantities.')
                ->constrained('unit_of_measurements')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            
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
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('products');
    }
};
