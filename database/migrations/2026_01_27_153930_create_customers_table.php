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
        Schema::create('customers', function (Blueprint $table) {
            $table -> id();
            $table -> string('customer_code' , 50) -> unique();
            $table -> string('image') -> nullable();
            $table -> string('fullname', 50);
            $table -> string('email_address' , 50) -> nullable();
            $table -> string('phone_number' , 50);
            $table -> string('social_media' , 100)-> nullable();
            $table -> string('customer_address' , 255);
            $table ->string('google_map_link', 100)->nullable();
            $table -> string('customer_status' , 255);
            $table -> foreignId('customer_category_id') -> constrained('customer_categories') -> restrictOnDelete() -> cascadeOnUpdate();
            $table -> text('customer_note')->nullable();
            $table ->timestamps();
            $table ->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
