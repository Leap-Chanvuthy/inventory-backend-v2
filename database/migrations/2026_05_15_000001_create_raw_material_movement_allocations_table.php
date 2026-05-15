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
        Schema::create('raw_material_movement_allocations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('consumer_movement_id')
                ->comment('The OUT raw material movement created for production/scrap/adjustment.')
                ->constrained('rm_stock_movements')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('source_movement_id')
                ->comment('The stock-IN raw material lot consumed.')
                ->constrained('rm_stock_movements')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('product_id')
                ->nullable()
                ->constrained('products')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('product_movement_id')
                ->nullable()
                ->comment('Finished product production movement related to this raw material consumption.')
                ->constrained('product_movements')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->decimal('allocated_quantity', 15, 4);
            $table->decimal('unit_cost_usd', 15, 4)->default(0);
            $table->decimal('unit_cost_riel', 15, 4)->default(0);
            $table->decimal('line_cost_usd', 15, 4)->default(0);
            $table->decimal('line_cost_riel', 15, 4)->default(0);

            $table->timestamp('allocated_at')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();

            $table->timestamps();

            $table->index(['consumer_movement_id']);
            $table->index(['source_movement_id']);
            $table->index(['product_id']);
            $table->index(['product_movement_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('raw_material_movement_allocations');
    }
};
