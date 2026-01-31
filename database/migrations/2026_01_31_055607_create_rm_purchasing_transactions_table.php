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
        Schema::create('rm_purchasing_transactions', function (Blueprint $table) {
            $table->id();
            $table -> foreignId('raw_material_id') -> constrained('raw_materials') -> restrictOnDelete() -> cascadeOnUpdate();
            $table -> double('unit_price_in_usd');
            $table -> double('total_value_in_usd'); 
            $table -> double('exchange_rate_from_usd_to_riel');
            $table -> double('unit_price_in_riel');
            $table -> double('total_value_in_riel');
            $table -> double('exchange_rate_from_riel_to_usd');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rm_purchasing_transactions');
    }
};


