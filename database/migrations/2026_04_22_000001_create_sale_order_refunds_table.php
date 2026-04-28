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
        Schema::create('sale_order_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_order_id')->constrained('sale_orders')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('refund_no', 50)->unique();
            $table->enum('refund_type', [
                'CASH_REFUND',
                'PARTIAL_REFUND',
                'DISCOUNT_COMPENSATION',
            ])->default('CASH_REFUND');
            $table->enum('refund_method', [
                'CASH',
                'BANK_TRANSFER',
                'STORE_CREDIT',
                'DISCOUNT_COMPENSATION',
            ])->default('CASH');
            $table->enum('reason_type', [
                'PRODUCT_ISSUE',
                'CUSTOMER_SATISFACTION',
                'COMPENSATION',
                'OTHER',
            ])->default('OTHER');
            $table->decimal('total_refund_amount_in_usd', 15, 2)->default(0);
            $table->decimal('total_refund_amount_in_riel', 15, 2)->default(0);
            $table->text('reason');
            $table->text('note')->nullable();
            $table->dateTime('processed_at');
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('sale_order_refunds');
    }
};

