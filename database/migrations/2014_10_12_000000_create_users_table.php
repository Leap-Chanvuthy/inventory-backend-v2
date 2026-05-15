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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table -> string('phone_number' , 255) -> nullable();
            $table -> string('profile_picture') -> nullable();
            $table->unsignedBigInteger('role_id')->nullable()->index();
            $table->string('email')->unique();
            $table->string('email_verification_token')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->integer('otp')->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->string('google_id') -> nullable();
            $table->string('ip_address')->nullable();
            $table->string('device')->nullable();
            $table->timestamp('last_activity')->nullable();
            $table->rememberToken();
            $table->boolean('two_factor_enabled')->default(false);
            $table->text('two_factor_secret')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('users');
    }
};
