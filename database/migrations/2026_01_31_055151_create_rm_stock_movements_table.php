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

            $table->foreignId('raw_material_id')
                ->constrained()
                ->restrictOnDelete();

            $table->decimal('quantity', 15, 4);

            $table->enum('direction', [StockDirectionEnum::IN, StockDirectionEnum::OUT]);

            $table->enum('movement_type', [
                RawMaterialStockMovementTypeEnum::PURCHASE->value,
                RawMaterialStockMovementTypeEnum::RE_ORDER->value,
                RawMaterialStockMovementTypeEnum::SALE->value,
                RawMaterialStockMovementTypeEnum::PRODUCTION_SCRAP->value,
                RawMaterialStockMovementTypeEnum::PRODUCTION_RECEIPT->value,
                RawMaterialStockMovementTypeEnum::ADJUSTMENT_IN->value,
                RawMaterialStockMovementTypeEnum::ADJUSTMENT_OUT->value,
            ]);

            $table->timestamp('movement_date');
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
