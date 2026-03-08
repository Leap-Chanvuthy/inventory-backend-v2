<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unit of Measurements Migration
 *
 * Hierarchical UOM with direct base-unit conversion for O(1) conversions.
 *
 * Design rules:
 *  - Each category has exactly ONE base unit (is_base_unit = true).
 *  - Base unit: conversion_factor = 1, base_uom_id = null.
 *  - All other units store how many base units equal 1 of this unit.
 *    e.g.  Very Extra Small Pack: factor=1  (IS the base)
 *          Extra Small Pack:      factor=10  (1 ESP  = 10 VESP)
 *          Small Pack:            factor=100 (1 SP   = 100 VESP)
 *          Big Pack:              factor=1000 (1 BP  = 1000 VESP)
 *
 * Formula:
 *  base_qty        = quantity × from_uom.conversion_factor
 *  converted_qty   = base_qty  ÷ to_uom.conversion_factor
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_of_measurements', function (Blueprint $table) {
            $table->id();

            // Human-readable identifiers
            $table->string('uom_code', 20)->unique()->comment('Auto-generated code e.g. UOM00001');
            $table->string('name', 100)->comment('Full name e.g. Big Pack');
            $table->string('symbol', 20)->nullable()->comment('Short symbol e.g. BP');

            // Category grouping — prevents cross-category conversions
            $table->foreignId('category_id')
                ->constrained('uom_categories')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            // Hierarchical conversion
            // NULL means this IS the base unit for its category.
            $table->unsignedBigInteger('base_uom_id')->nullable()
                ->comment('Points to the base unit of this category. NULL if this unit is the base.');

            // How many base units equal 1 of this unit.
            // Base unit always has conversion_factor = 1.
            $table->decimal('conversion_factor', 20, 6)->default(1.000000)
                ->comment('Number of base units contained in 1 of this unit.');

            // Flags
            $table->boolean('is_base_unit')->default(false)
                ->comment('True for the single base unit per category.');
            $table->boolean('is_active')->default(true);

            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // --- Unique constraint: name must be unique within the same category ---
            $table->unique(['category_id', 'name'], 'uom_category_name_unique');

            // --- Performance indexes ---
            $table->index('uom_code',     'idx_uom_code');
            $table->index('category_id',  'idx_uom_category_id');
            $table->index('base_uom_id',  'idx_uom_base_uom_id');
        });

        // Self-referencing FK added after table creation to avoid forward-reference issues
        Schema::table('unit_of_measurements', function (Blueprint $table) {
            $table->foreign('base_uom_id', 'fk_uom_base_uom_id')
                ->references('id')
                ->on('unit_of_measurements')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('unit_of_measurements', function (Blueprint $table) {
            $table->dropForeign('fk_uom_base_uom_id');
        });

        Schema::dropIfExists('unit_of_measurements');
    }
};
