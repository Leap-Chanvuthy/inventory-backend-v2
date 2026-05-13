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
        Schema::create('product_movement_allocations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sale_movement_id')
                ->comment('The OUT movement created by a sale order.')
                ->constrained('product_movements')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('source_movement_id')
                ->comment('The stock-IN movement consumed by the sale.')
                ->constrained('product_movements')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->decimal('allocated_quantity', 15, 4)
                ->comment('Quantity consumed from the source movement.');

            $table->decimal('selling_unit_price_in_usd', 15, 4)->default(0);
            $table->decimal('selling_unit_price_in_riel', 15, 4)->default(0);

            $table->decimal('cost_unit_price_in_usd', 15, 4)->default(0);
            $table->decimal('cost_unit_price_in_riel', 15, 4)->default(0);

            $table->decimal('selling_exchange_rate_from_usd_to_riel', 15, 4)->default(0);
            $table->decimal('selling_exchange_rate_from_riel_to_usd', 15, 8)->default(0);

            $table->decimal('cost_exchange_rate_from_usd_to_riel', 15, 4)->default(0);
            $table->decimal('cost_exchange_rate_from_riel_to_usd', 15, 8)->default(0);

            $table->timestamp('allocated_at')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();

            $table->timestamps();

            $table->index(['sale_movement_id']);
            $table->index(['source_movement_id']);
            $table->index(['source_movement_id', 'allocated_quantity'], 'pma_src_alloc_qty_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_movement_allocations');
    }
};
