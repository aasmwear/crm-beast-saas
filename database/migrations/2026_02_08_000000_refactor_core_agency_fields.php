<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Phase 2: Core Data Refactor (Agency Logic)
     * - Refine Clients table with Agency-specific fields
     * - Refine Projects table with budget/price in cents and GBP sync
     * - Add is_pm_capable flag to Departments
     */
    public function up(): void
    {
        // ==========================================
        // 1. CLIENTS TABLE REFACTOR
        // ==========================================
        Schema::table('clients', function (Blueprint $table) {
            // Drop old string-based GBP status columns
            $table->dropColumn('google_business_profile_status');
            $table->dropColumn('google_business_profile_access_status');
        });

        // Add new enum-based GBP columns
        DB::statement("
            ALTER TABLE clients
            ADD COLUMN gbp_status VARCHAR(20) DEFAULT 'not_created'
            CHECK (gbp_status IN ('not_created', 'created', 'pending', 'verified', 'suspended'))
        ");

        DB::statement("
            ALTER TABLE clients
            ADD COLUMN gbp_access VARCHAR(20) DEFAULT 'no_access'
            CHECK (gbp_access IN ('no_access', 'access_granted', 'access_pending'))
        ");

        // Rename notes columns to match spec (notes_sales, notes_cst, notes_tech)
        Schema::table('clients', function (Blueprint $table) {
            $table->renameColumn('notes_by_sales', 'notes_sales');
            $table->renameColumn('notes_by_cst', 'notes_cst');
            $table->renameColumn('notes_by_tech', 'notes_tech');
        });

        // ==========================================
        // 2. PROJECTS TABLE REFACTOR
        // ==========================================
        
        // Add cents-based budget and price columns
        Schema::table('projects', function (Blueprint $table) {
            $table->bigInteger('budget_cents')->nullable()->after('status');
            $table->bigInteger('price_cents')->nullable()->after('budget_cents');
        });

        // Migrate existing decimal values to cents (multiply by 100)
        DB::statement('UPDATE projects SET budget_cents = ROUND(budget * 100) WHERE budget IS NOT NULL');
        DB::statement('UPDATE projects SET price_cents = ROUND(price * 100) WHERE price IS NOT NULL');

        // Drop old decimal columns
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['budget', 'price']);
            
            // Drop old string-based GBP columns
            $table->dropColumn('google_business_profile_status');
            $table->dropColumn('google_business_profile_access_status');
        });

        // Add new enum-based GBP status for projects
        DB::statement("
            ALTER TABLE projects
            ADD COLUMN gbp_status VARCHAR(20) NULL
            CHECK (gbp_status IN ('not_created', 'created', 'pending', 'verified', 'suspended'))
        ");

        // Rename notes columns to match spec
        Schema::table('projects', function (Blueprint $table) {
            $table->renameColumn('notes_by_sales', 'notes_sales');
            $table->renameColumn('notes_by_cst', 'notes_cst');
            $table->renameColumn('notes_by_tech', 'notes_tech');
        });

        // Make project_manager_id nullable (may not be assigned immediately)
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('project_manager_id')->nullable()->change();
        });

        // ==========================================
        // 3. DEPARTMENTS TABLE ENHANCEMENT
        // ==========================================
        Schema::table('departments', function (Blueprint $table) {
            $table->boolean('is_pm_capable')->default(false)->after('code');
        });

        // Add index on is_pm_capable for efficient PM dropdown queries
        Schema::table('departments', function (Blueprint $table) {
            $table->index('is_pm_capable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // ==========================================
        // REVERSE: DEPARTMENTS
        // ==========================================
        Schema::table('departments', function (Blueprint $table) {
            $table->dropIndex(['is_pm_capable']);
            $table->dropColumn('is_pm_capable');
        });

        // ==========================================
        // REVERSE: PROJECTS
        // ==========================================
        
        // Restore old decimal columns
        Schema::table('projects', function (Blueprint $table) {
            $table->decimal('budget', 12, 2)->nullable()->after('status');
            $table->decimal('price', 12, 2)->nullable()->after('budget');
        });

        // Migrate cents back to decimal (divide by 100)
        DB::statement('UPDATE projects SET budget = budget_cents / 100.0 WHERE budget_cents IS NOT NULL');
        DB::statement('UPDATE projects SET price = price_cents / 100.0 WHERE price_cents IS NOT NULL');

        // Drop cents columns
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['budget_cents', 'price_cents']);
        });

        // Drop enum-based GBP status
        DB::statement('ALTER TABLE projects DROP COLUMN IF EXISTS gbp_status');

        // Restore old string-based columns
        Schema::table('projects', function (Blueprint $table) {
            $table->string('google_business_profile_status')->nullable();
            $table->string('google_business_profile_access_status')->nullable();
        });

        // Restore old notes column names
        Schema::table('projects', function (Blueprint $table) {
            $table->renameColumn('notes_sales', 'notes_by_sales');
            $table->renameColumn('notes_cst', 'notes_by_cst');
            $table->renameColumn('notes_tech', 'notes_by_tech');
        });

        // Revert project_manager_id to required
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('project_manager_id')->nullable(false)->change();
        });

        // ==========================================
        // REVERSE: CLIENTS
        // ==========================================
        
        // Drop enum-based GBP columns
        DB::statement('ALTER TABLE clients DROP COLUMN IF EXISTS gbp_status');
        DB::statement('ALTER TABLE clients DROP COLUMN IF EXISTS gbp_access');

        // Restore old string-based columns
        Schema::table('clients', function (Blueprint $table) {
            $table->string('google_business_profile_status')->default('Not Created');
            $table->string('google_business_profile_access_status')->default('No Access');
        });

        // Restore old notes column names
        Schema::table('clients', function (Blueprint $table) {
            $table->renameColumn('notes_sales', 'notes_by_sales');
            $table->renameColumn('notes_cst', 'notes_by_cst');
            $table->renameColumn('notes_tech', 'notes_by_tech');
        });
    }
};
