<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // CLIENTS
        Schema::table('clients', function (Blueprint $t) {
            if (! Schema::hasColumn('clients', 'niche')) {
                $t->string('niche')->nullable()->after('industry');
            }
            if (! Schema::hasColumn('clients', 'fronter')) {
                $t->json('fronter')->nullable()->after('tags');
            }
            if (! Schema::hasColumn('clients', 'closer')) {
                $t->json('closer')->nullable()->after('fronter');
            }
            if (! Schema::hasColumn('clients', 'assigned_account_manager_id')) {
                $t->foreignId('assigned_account_manager_id')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('clients', 'google_business_profile_status')) {
                $t->enum('google_business_profile_status', ['Not Created', 'Created', 'Pending', 'Verified', 'Suspended'])->default('Not Created');
            }
            if (! Schema::hasColumn('clients', 'google_business_profile_access_status')) {
                $t->enum('google_business_profile_access_status', ['No Access', 'Access Granted', 'Access Pending'])->default('No Access');
            }
            if (! Schema::hasColumn('clients', 'client_activation_status')) {
                $t->enum('client_activation_status', ['Inactive', 'Active', 'Paused', 'Cancelled'])->default('Inactive');
            }
            if (! Schema::hasColumn('clients', 'notes_by_cst')) {
                $t->text('notes_by_cst')->nullable();
            }
            if (! Schema::hasColumn('clients', 'notes_by_sales')) {
                $t->text('notes_by_sales')->nullable();
            }
            if (! Schema::hasColumn('clients', 'notes_by_tech')) {
                $t->text('notes_by_tech')->nullable();
            }
            if (! Schema::hasColumn('clients', 'status')) {
                $t->string('status')->default('active');
            }
        });

        // IMPORTANT: Skip index creation to avoid collisions with existing indexes across environments.

        // PROJECTS
        Schema::table('projects', function (Blueprint $t) {
            if (! Schema::hasColumn('projects', 'project_code')) {
                $t->string('project_code')->nullable();
            }
            if (! Schema::hasColumn('projects', 'project_manager_id')) {
                $t->foreignId('project_manager_id')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('projects', 'department_id')) {
                $t->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            }
            if (! Schema::hasColumn('projects', 'price')) {
                $t->decimal('price', 12, 2)->nullable();
            }
            if (! Schema::hasColumn('projects', 'billable')) {
                $t->boolean('billable')->default(true);
            }
            if (! Schema::hasColumn('projects', 'google_business_profile_status')) {
                $t->enum('google_business_profile_status', ['Not Created', 'Created', 'Pending', 'Verified', 'Suspended'])->default('Not Created');
            }
            if (! Schema::hasColumn('projects', 'google_business_profile_access_status')) {
                $t->enum('google_business_profile_access_status', ['No Access', 'Access Granted', 'Access Pending'])->default('No Access');
            }
            if (! Schema::hasColumn('projects', 'client_activation_status')) {
                $t->enum('client_activation_status', ['Inactive', 'Active', 'Paused', 'Cancelled'])->default('Inactive');
            }
            if (! Schema::hasColumn('projects', 'notes_by_cst')) {
                $t->text('notes_by_cst')->nullable();
            }
            if (! Schema::hasColumn('projects', 'notes_by_sales')) {
                $t->text('notes_by_sales')->nullable();
            }
            if (! Schema::hasColumn('projects', 'notes_by_tech')) {
                $t->text('notes_by_tech')->nullable();
            }
            if (! Schema::hasColumn('projects', 'custom_fields')) {
                $t->json('custom_fields')->nullable();
            }
        });
    }

    public function down(): void
    {
        // No destructive drops to avoid data loss.
    }
};
