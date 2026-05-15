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

            $table->decimal('remaining_quantity', 15, 4)
                ->default(0)
                ->comment('Remaining available quantity for stock-IN raw material lots. OUT movements should normally be 0.');

            $table->enum('direction', [StockDirectionEnum::IN -> value, StockDirectionEnum::OUT -> value]);

            $table->enum('movement_type', [
                RawMaterialStockMovementTypeEnum::PURCHASE->value,
                RawMaterialStockMovementTypeEnum::RE_ORDER->value,
                RawMaterialStockMovementTypeEnum::PRODUCTION_SCRAP->value,
                RawMaterialStockMovementTypeEnum::PRODUCTION_RECEIPT->value,
                RawMaterialStockMovementTypeEnum::MANUFACTURING->value,
                RawMaterialStockMovementTypeEnum::SCRAP->value,
                RawMaterialStockMovementTypeEnum::ADJUSTMENT_IN->value,
                RawMaterialStockMovementTypeEnum::ADJUSTMENT_OUT->value,
            ]);

            $table->foreignId('source_movement_id')
                ->nullable()
                ->comment('Parent stock-IN movement used by stock-OUT actions such as production consumption, scrap, or adjustment out.')
                ->constrained('rm_stock_movements')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

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
            $table->date('expiry_date')->nullable();
            $table->text('note')->nullable();

            // Use explicit short index names to avoid MySQL 64-char identifier limit.
            $table->index(['raw_material_id', 'direction', 'remaining_quantity'], 'rm_sm_rm_dir_rem_idx');
            $table->index(['raw_material_id', 'direction', 'movement_date'], 'rm_sm_rm_dir_mdate_idx');
            $table->index(['raw_material_id', 'direction', 'expiry_date'], 'rm_sm_rm_dir_exp_idx');
            $table->index(['raw_material_id', 'movement_type'], 'rm_sm_rm_mtype_idx');
            $table->index(['source_movement_id'], 'rm_sm_src_mv_idx');
            $table->index(['remaining_quantity'], 'rm_sm_rem_idx');
            $table->index(['expiry_date'], 'rm_sm_exp_idx');

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
