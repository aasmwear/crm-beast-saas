<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Phase 2.5: Field-Level Security Infrastructure
     * - Add field_permissions JSONB column to roles table
     * - Stores field-level visibility rules per entity
     * 
     * Schema Example:
     * {
     *   "projects": {
     *     "budget_cents": "hidden",
     *     "price_cents": "readonly"
     *   },
     *   "clients": {
     *     "notes_sales": "hidden",
     *     "phone": "read_write"
     *   }
     * }
     */
    public function up(): void
    {
        if (Schema::hasTable('roles') && ! Schema::hasColumn('roles', 'field_permissions')) {
            Schema::table('roles', function (Blueprint $table) {
                // Add field_permissions column after guard_name
                $table->json('field_permissions')->nullable()->after('guard_name');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('roles') && Schema::hasColumn('roles', 'field_permissions')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropColumn('field_permissions');
            });
        }
    }
};
