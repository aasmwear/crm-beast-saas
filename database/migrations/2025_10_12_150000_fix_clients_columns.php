<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('clients', 'account_manager_id')) {
            // Postgres-safe: only drop if present
            DB::statement('ALTER TABLE clients DROP CONSTRAINT IF EXISTS clients_account_manager_id_foreign');
            DB::statement('DROP INDEX IF EXISTS clients_account_manager_id_index');
            DB::statement('ALTER TABLE clients DROP COLUMN IF EXISTS account_manager_id');
        }
    }

    public function down(): void
    {
        // no-op (we intentionally removed the wrong column)
    }
};
