<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'organization_id')) {
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete()->index();
            }
            if (!Schema::hasColumn('clients', 'account_manager_id')) {
                $table->foreignId('account_manager_id')->nullable()->constrained('users')->nullOnDelete()->index();
            }
            if (Schema::hasColumn('clients', 'project_activation_status')) {
                // keep existing enum values; ensure index for filters
                $table->index('project_activation_status');
            }
            // fast search
            if (!Schema::hasColumn('clients', 'search_vector')) {
                $table->text('search_vector')->nullable()->index(); // simple text index (PG trigram optional)
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'search_vector')) $table->dropColumn('search_vector');
            $table->dropIndex(['project_activation_status']);
            if (Schema::hasColumn('clients', 'account_manager_id')) $table->dropConstrainedForeignId('account_manager_id');
            if (Schema::hasColumn('clients', 'organization_id')) $table->dropConstrainedForeignId('organization_id');
        });
    }
};
