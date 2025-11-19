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
        Schema::create('company_information', function (Blueprint $table) {
            $table->id();
            $table->string('company_name')->nullable();
            $table->string('company_logo')->nullable();
            $table->string('email')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('industry_type')->nullable();
            $table->string('website_url')->nullable();
            $table->date('date_established')->nullable();
            $table->string('vat_number')->nullable();
            $table->string('description')->nullable();

            $table->string('full_address')->nullable();
            $table->string('house_number')->nullable();
            $table->string('street')->nullable();
            $table->string('commune')->nullable();
            $table->string('district')->nullable();
            $table->string('city')->nullable();

            $table->string('telegram_inventory_chat_id')->nullable();
            $table->string('telegram_sale_chat_id')->nullable();
            $table->string('telegram_purchase_chat_id')->nullable();



            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('company_information');
    }
};
