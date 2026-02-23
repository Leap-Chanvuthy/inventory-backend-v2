<?php

use App\Enums\RawMaterialStockMovementTypeEnum;
use App\Enums\StockDirectionEnum;
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
        Schema::create('rm_stock_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('raw_material_id')->constrained('raw_materials')->restrictOnDelete()->cascadeOnUpdate();

            $table->decimal('quantity', 15, 4);

            $table->enum('direction', [StockDirectionEnum::IN -> value, StockDirectionEnum::OUT -> value]);

            $table->enum('movement_type', [
                RawMaterialStockMovementTypeEnum::PURCHASE->value,
                RawMaterialStockMovementTypeEnum::RE_ORDER->value,
                RawMaterialStockMovementTypeEnum::PRODUCTION_SCRAP->value,
                RawMaterialStockMovementTypeEnum::PRODUCTION_RECEIPT->value,
                RawMaterialStockMovementTypeEnum::ADJUSTMENT_IN->value,
                RawMaterialStockMovementTypeEnum::ADJUSTMENT_OUT->value,
            ]);

            $table->boolean('in_used')->default(false);


            $table->double('unit_price_in_usd')->min(0);
            $table->double('total_value_in_usd')->min(0);
            $table->double('exchange_rate_from_usd_to_riel')->min(0);
            $table->double('unit_price_in_riel')->min(0);
            $table->double('total_value_in_riel')->min(0);
            $table->double('exchange_rate_from_riel_to_usd')->min(0);
            $table -> foreignId('created_by') -> constrained('users') -> restrictOnDelete() -> cascadeOnUpdate();
            $table -> foreignId('last_updated_by') -> constrained('users') -> restrictOnDelete() -> cascadeOnUpdate();

            $table->timestamp('movement_date');
            $table->text('note')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rm_stock_movements');
    }
};
