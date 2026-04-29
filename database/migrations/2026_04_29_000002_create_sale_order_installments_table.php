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
        Schema::create('sale_order_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_order_id')->constrained('sale_orders')->cascadeOnDelete()->cascadeOnUpdate();
            $table->decimal('percentage', 10, 2)->default(0);
            $table->decimal('cumulative_percentage', 10, 2)->default(0);
            $table->decimal('amount_usd', 15, 2)->default(0);
            $table->decimal('amount_riel', 15, 2)->default(0);
            $table->dateTime('paid_at');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['sale_order_id', 'paid_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('sale_order_installments');
    }
};
