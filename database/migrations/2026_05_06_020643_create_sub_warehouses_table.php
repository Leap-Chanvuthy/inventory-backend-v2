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
        Schema::create('sub_warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('warehouse_name');
            $table->string('warehouse_manager')->nullable();
            $table->string('warehouse_manager_contact')->nullable();
            $table->string('warehouse_manager_email')->nullable();
            $table->text('warehouse_address');
            $table->string('latitude')->nullable();
            $table->string('longitude')->nullable();
            $table->text('warehouse_description')->nullable();

            // relationship with warehouses table
            $table->unsignedBigInteger('warehouse_id');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->restrictOnDelete();


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sub_warehouses');
    }
};
