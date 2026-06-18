<?php

use App\Enums\CustomerStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_code', 50)->unique();
            $table->string('image')->nullable();
            $table->string('fullname', 50);
            $table->string('email_address', 50)->nullable();
            $table->string('phone_number', 50)->index();
            $table->string('social_media', 100)->nullable();
            $table->string('customer_address', 255)->nullable();
            $table->string('google_map_link', 100)->nullable();

            $table->enum('customer_status', [
                CustomerStatusEnum::ACTIVE->value,
                CustomerStatusEnum::INACTIVE->value,
                CustomerStatusEnum::BLACKLISTED->value,
            ])->default(CustomerStatusEnum::ACTIVE->value);

            $table->foreignId('customer_category_id')
                ->constrained('customer_categories')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->text('customer_note')->nullable();
            $table->json('extra_data')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['customer_category_id', 'customer_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
