<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ensure canonical column exists on clients
        if (! Schema::hasColumn('clients', 'client_activation_status')) {
            Schema::table('clients', function (Blueprint $t) {
                $t->string('client_activation_status')->default('Inactive')->index();
            });
        }

        // Backfill from legacy columns on clients, if they exist
        if (Schema::hasColumn('clients', 'project_activation_status')) {
            DB::statement('
                UPDATE clients
                SET client_activation_status = COALESCE(project_activation_status, client_activation_status)
            ');
        }
        if (Schema::hasColumn('clients', 'activation_status')) {
            DB::statement('
                UPDATE clients
                SET client_activation_status = COALESCE(activation_status, client_activation_status)
            ');
        }

        // Drop legacy columns on clients if present
        Schema::table('clients', function (Blueprint $t) {
            if (Schema::hasColumn('clients', 'project_activation_status')) {
                $t->dropColumn('project_activation_status');
            }
            if (Schema::hasColumn('clients', 'activation_status')) {
                $t->dropColumn('activation_status');
            }
        });

        // Correct any accidental column on projects (seen in one migration)
        if (Schema::hasColumn('projects', 'client_activation_status')) {
            // Optionally map to 'status' if empty; safest is to just drop the wrong column:
            Schema::table('projects', function (Blueprint $t) {
                $t->dropColumn('client_activation_status');
            });
        }
    }

    public function down(): void
    {
        // No-op: we keep the canonical 'client_activation_status'
    }
};
