<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SoftDelete + Index improvements for UOM tables.
 *
 * uom_categories:
 *   - Adds `deleted_at` column (SoftDeletes)
 *   - Adds index on `name`       → faster unique-name look-ups
 *   - Adds index on `deleted_at` → faster "not trashed" default scope
 *
 * unit_of_measurements:
 *   - Adds index on `deleted_at` → faster "not trashed" default scope
 *
 * Soft-delete behaviour:
 *   • Deleting a category soft-deletes it (sets deleted_at).
 *   • Child UOM records are NOT touched — they remain in the database.
 *   • BUT their parent category is hidden, so `scopeAvailable()` on UOM
 *     will automatically hide those units from normal queries.
 *   • Restoring the category makes all its units visible again — zero extra work.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uom_categories', function (Blueprint $table) {
            $table->softDeletes()->after('description');

            $table->index('name',       'idx_uom_categories_name');
            $table->index('deleted_at', 'idx_uom_categories_deleted_at');
        });

        Schema::table('unit_of_measurements', function (Blueprint $table) {
            $table->index('deleted_at', 'idx_uom_deleted_at');
        });
    }

    public function down(): void
    {
        Schema::table('unit_of_measurements', function (Blueprint $table) {
            $table->dropIndex('idx_uom_deleted_at');
        });

        Schema::table('uom_categories', function (Blueprint $table) {
            $table->dropIndex('idx_uom_categories_name');
            $table->dropIndex('idx_uom_categories_deleted_at');
            $table->dropSoftDeletes();
        });
    }
};
