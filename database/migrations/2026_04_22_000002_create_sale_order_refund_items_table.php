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
        Schema::create('sale_order_refund_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_order_refund_id')->constrained('sale_order_refunds')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('sale_order_item_id')->constrained('sale_order_items')->restrictOnDelete()->cascadeOnUpdate();
            $table->decimal('quantity', 15, 4)->default(0);
            $table->boolean('process_return')->default(true);
            $table->boolean('process_refund')->default(true);
            $table->boolean('is_resellable')->nullable();
            $table->enum('return_action', [
                'RETURN_TO_STOCK',
                'SCRAP',
                'NO_RETURN',
            ])->default('RETURN_TO_STOCK');
            $table->decimal('refund_percentage', 10, 2)->default(100);
            $table->decimal('refund_amount_in_usd', 15, 2)->default(0);
            $table->decimal('refund_amount_in_riel', 15, 2)->default(0);
            $table->text('reason')->nullable();
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
        Schema::dropIfExists('sale_order_refund_items');
    }
};

