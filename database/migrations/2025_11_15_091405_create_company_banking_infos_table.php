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
        Schema::create('company_banking_infos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_information_id')->constrained('company_information')->onDelete('cascade');
            $table->string('bank_name');
            $table->string('bank_account_holder_name');
            $table->string('bank_account_number');
            $table->string('payment_link');
            $table->string('khqr_code');
            $table->string('payment_method_label');
            $table->boolean('set_as_default')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_banking_infos');
    }
};
