<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (! Schema::hasColumn('clients', 'organization_id')) {
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->index('organization_id');
            }
            if (! Schema::hasColumn('clients', 'account_manager_id')) {
                $table->foreignId('account_manager_id')->nullable()->constrained('users')->nullOnDelete();
                $table->index('account_manager_id');
            }
        });

        // Recreate the tsvector column with proper indexes
        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'search_vector')) {
                $table->dropColumn('search_vector');
            }
            $table->text('search_vector')->nullable();
            $table->index(['search_vector']);
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'search_vector')) {
                $table->dropColumn('search_vector');
            }
            $table->dropIndex(['project_activation_status']);
            if (Schema::hasColumn('clients', 'account_manager_id')) {
                $table->dropConstrainedForeignId('account_manager_id');
            }
            if (Schema::hasColumn('clients', 'organization_id')) {
                $table->dropConstrainedForeignId('organization_id');
            }
        });
    }
};
