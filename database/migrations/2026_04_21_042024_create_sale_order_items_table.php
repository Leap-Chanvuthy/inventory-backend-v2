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
        Schema::create('sale_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_order_id')->constrained('sale_orders')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete()->cascadeOnUpdate();
            $table->decimal('quantity', 15, 2)->default(0);
            $table->decimal('returned_quantity', 15, 2)->default(0);
            $table->decimal('refund_quantity', 15, 2)->nullable();
            $table->decimal('unit_price_in_usd', 15, 2)->default(0);
            $table->decimal('unit_price_in_riel', 15, 2)->default(0);
            $table->decimal('total_price_in_usd', 15, 2)->default(0);
            $table->decimal('total_price_in_riel', 15, 2)->default(0);
            $table->double('exchange_rate_from_usd_to_riel', 10, 4)->min(0);
            $table->double('exchange_rate_from_riel_to_usd', 10, 4)->min(0);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('sale_order_items');
    }
};
