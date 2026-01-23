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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            // general information
            $table->string('image')->nullable();
            $table->string('official_name');
            $table->string('supplier_code')->unique();
            $table->string('contact_person');
            $table->string('phone');
            $table->string('email')->nullable();

            // business & legal information
            $table->string('legal_business_name')->nullable();
            $table->string('tax_identification_number')->nullable();
            $table->string('business_registration_number')->nullable();
            $table->string('supplier_category');
            $table->text('business_description')->nullable();

            // geolocational information
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('village');
            $table->string('commune');
            $table->string('district');
            $table->string('city');
            $table->string('province');
            $table->string('postal_code')->nullable();
            $table->string('latitude')->nullable();
            $table->string('longitude')->nullable();

            $table->timestamps();
            $table -> softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
