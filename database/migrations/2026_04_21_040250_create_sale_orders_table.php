<?php

use App\Enums\DiscountTypeEnum;
use App\Enums\PaymentStatusEnum;
use App\Enums\SaleOrderStatusEnum;
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
        Schema::create('sale_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 50)->unique();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->dateTime('order_date');
            $table->unsignedInteger('return_window_days')->default(30);
            $table->dateTime('return_valid_until')->nullable();

            $table->enum('order_status', [
                SaleOrderStatusEnum::DRAFT->value,
                SaleOrderStatusEnum::PROCESSING->value,
                SaleOrderStatusEnum::ON_HOLD->value,
                SaleOrderStatusEnum::CANCELLED->value,
                SaleOrderStatusEnum::REFUNDED->value,
                SaleOrderStatusEnum::COMPLETED->value,
            ])->default(SaleOrderStatusEnum::DRAFT->value)->comment('Defines the status of the sale order.');


            $table->enum('payment_status', [PaymentStatusEnum::PAID->value, PaymentStatusEnum::UNPAID->value, PaymentStatusEnum::DEBT->value])->default(PaymentStatusEnum::UNPAID->value);

            $table->text('note')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('last_updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->decimal('tax_percentage', 15, 2)->default(0);
            $table->decimal('tax_amount_in_usd', 15, 2)->default(0);
            $table->decimal('tax_amount_in_riel', 15, 2)->default(0);
            $table->decimal('sub_total_in_usd', 15, 2)->default(0);
            $table->decimal('sub_total_in_riel', 15, 2)->default(0);
            $table->decimal('grand_total_amount_in_usd', 15, 2)->default(0);
            $table->decimal('grand_total_amount_in_riel', 15, 2)->default(0);

            $table->decimal('discount_percentage', 10, 2)->default(0)->min(0)->max(100)->comment('The percentage of discount applied to the order.');
            $table->decimal('discount_amount', 15, 2)->default(0);

            // Payment and refund snapshot fields
            $table->decimal('paid_amount_in_usd', 15, 2)->default(0);
            $table->decimal('paid_amount_in_riel', 15, 2)->default(0);
            $table->decimal('total_refunded_amount_in_usd', 15, 2)->default(0);
            $table->decimal('total_refunded_amount_in_riel', 15, 2)->default(0);
            $table->decimal('remaining_balance_in_usd', 15, 2)->default(0);
            $table->decimal('remaining_balance_in_riel', 15, 2)->default(0);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('sale_orders');
    }
};
