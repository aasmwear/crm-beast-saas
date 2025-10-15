<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Core identity
            if (! Schema::hasColumn('clients', 'company_name')) {
                $table->string('company_name')->nullable()->after('id');
            }
            if (! Schema::hasColumn('clients', 'industry')) {
                $table->string('industry')->nullable();
            }
            if (! Schema::hasColumn('clients', 'niche')) {
                $table->string('niche')->nullable();
            }

            // Primary contact
            if (! Schema::hasColumn('clients', 'primary_contact_name')) {
                $table->string('primary_contact_name')->nullable();
            }
            if (! Schema::hasColumn('clients', 'primary_contact_email')) {
                $table->string('primary_contact_email')->nullable()->index();
            }
            if (! Schema::hasColumn('clients', 'primary_contact_phone')) {
                $table->string('primary_contact_phone', 40)->nullable();
            }

            // Misc
            if (! Schema::hasColumn('clients', 'website')) {
                $table->string('website')->nullable();
            }
            if (! Schema::hasColumn('clients', 'address')) {
                $table->string('address')->nullable();
            }
            if (! Schema::hasColumn('clients', 'tags')) {
                $table->json('tags')->nullable();
            }

            // Relationships / arrays
            if (! Schema::hasColumn('clients', 'fronter')) {
                $table->json('fronter')->nullable();
            } // [user_ids]
            if (! Schema::hasColumn('clients', 'closer')) {
                $table->json('closer')->nullable();
            }   // [user_ids]
            if (! Schema::hasColumn('clients', 'assigned_account_manager_id')) {
                $table->foreignId('assigned_account_manager_id')->nullable()->constrained('users')->nullOnDelete();
            }

            // Status enums (store as strings; validated in requests)
            if (! Schema::hasColumn('clients', 'google_business_profile_status')) {
                $table->string('google_business_profile_status')->default('Not Created');
            }
            if (! Schema::hasColumn('clients', 'google_business_profile_access_status')) {
                $table->string('google_business_profile_access_status')->default('No Access');
            }
            if (! Schema::hasColumn('clients', 'client_activation_status')) {
                $table->string('client_activation_status')->default('Inactive');
            }

            // Notes (rich text)
            if (! Schema::hasColumn('clients', 'notes_by_cst')) {
                $table->longText('notes_by_cst')->nullable();
            }
            if (! Schema::hasColumn('clients', 'notes_by_sales')) {
                $table->longText('notes_by_sales')->nullable();
            }
            if (! Schema::hasColumn('clients', 'notes_by_tech')) {
                $table->longText('notes_by_tech')->nullable();
            }

            if (! Schema::hasColumn('clients', 'status')) {
                $table->string('status')->nullable()->index();
            }

            // Helpful indexes
            if (! Schema::hasColumn('clients', 'organization_id')) {
                $table->foreignId('organization_id')->nullable()->constrained('organizations')->cascadeOnDelete();
            }
            $table->index(['organization_id', 'company_name']);
        });
    }

    public function down(): void
    {
        // Non-destructive: keep columns (old code may still depend on them)
    }
};
