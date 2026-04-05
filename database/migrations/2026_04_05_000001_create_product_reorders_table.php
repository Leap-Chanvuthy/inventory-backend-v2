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
        if (Schema::hasTable('product_reorders')) {
            return;
        }

        Schema::create('product_reorders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('product_movement_id')->constrained('product_movements')->restrictOnDelete()->cascadeOnUpdate();
            $table->decimal('quantity', 15, 4);
            $table->string('status')->default('COMPLETED');
            $table->boolean('is_finalized')->default(false);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('last_updated_by')->constrained('users')->restrictOnDelete()->cascadeOnUpdate();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'product_movement_id'], 'idx_pr_product_movement');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_reorders');
    }
};
