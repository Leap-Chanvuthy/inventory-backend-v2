<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\ProductStatusEnum;
use App\Enums\ProductStockMovementTypeEnum;
use App\Enums\ProductTypeEnum;
use App\Enums\StockDirectionEnum;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete()->cascadeOnUpdate();

            $table->string('product_type')->nullable(ProductTypeEnum::class);
            $table-> string('product_status')->nullable(ProductStatusEnum::class);
                        
            $table->decimal('quantity', 15, 4);
            $table->decimal('remaining_quantity', 15, 4)
                ->default(0)
                ->comment('Remaining available quantity for stock-IN movements. OUT movements should normally be 0.');

            $table->boolean('is_sold')->default(false);

            $table->enum('direction', [StockDirectionEnum::IN -> value, StockDirectionEnum::OUT -> value]);

            $table->enum('movement_type', [
                ProductStockMovementTypeEnum::EXTERNAL_PURCHASED->value,
                ProductStockMovementTypeEnum::INTERNAL_PRODUCED->value,
                ProductStockMovementTypeEnum::RE_ORDER->value,
                ProductStockMovementTypeEnum::RETURN_FROM_CUSTOMER->value,
                ProductStockMovementTypeEnum::SALE_ORDER->value,
                ProductStockMovementTypeEnum::SCRAP->value,
                ProductStockMovementTypeEnum::ADJUSTMENT_IN->value,
                ProductStockMovementTypeEnum::ADJUSTMENT_OUT->value,
            ]);
            
            // purchasing price
            $table->decimal('purchase_unit_price_in_usd', 15, 4)->default(0);
            $table->decimal('purchase_total_price_in_usd', 15, 4)->default(0);
            $table->decimal('purchase_unit_price_in_riel', 15, 4)->default(0);
            $table->decimal('purchase_total_price_in_riel', 15, 4)->default(0);
            $table->decimal('exchange_rate_from_usd_to_riel', 15, 4)->default(0);
            $table->decimal('exchange_rate_from_riel_to_usd', 15, 8)->default(0);

            // selling price
            $table->decimal('selling_unit_price_in_usd', 15, 4)->default(0);
            $table->decimal('selling_unit_price_in_riel', 15, 4)->default(0);
            $table->decimal('selling_exchange_rate_from_usd_to_riel', 15, 4)->default(0);
            $table->decimal('selling_exchange_rate_from_riel_to_usd', 15, 8)->default(0);

            // audit fields
            $table->timestamp('movement_date')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('last_updated_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('last_updated_by')->references('id')->on('users')->restrictOnDelete();

            $table->index(['product_id', 'direction', 'remaining_quantity']);
            $table->index(['product_id', 'direction', 'movement_date']);
            $table->index(['product_id', 'movement_type']);
            $table->index(['remaining_quantity']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_movements');
    }
};
