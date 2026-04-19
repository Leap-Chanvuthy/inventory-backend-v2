<?php

use App\Enums\AddressTypeEnum;
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
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->enum('type', array_map(static fn (AddressTypeEnum $case) => $case->value, AddressTypeEnum::cases()));
            $table->string('address', 255);
            $table->string('google_map_link', 255)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['customer_id', 'type']);
        });

        // DEPRECATED on customers table: customer_address, google_map_link.
        // Keep them for backward compatibility until all consumers are migrated.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
