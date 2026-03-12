<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Prevents double clock-in: at most one open session (clock_out_at IS NULL)
     * per user per organization.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX attendance_one_open_session_per_user_org ' .
                'ON attendance (organization_id, user_id) WHERE clock_out_at IS NULL'
            );
        } else {
            // MySQL/MariaDB: unique index with expression not supported for partial.
            // Fallback: unique on (organization_id, user_id, clock_out_at) would break
            // multiple closed records. Use application-level protection only.
            // SQLite: partial indexes supported since 3.8
            if ($driver === 'sqlite') {
                DB::statement(
                    'CREATE UNIQUE INDEX attendance_one_open_session_per_user_org ' .
                    'ON attendance (organization_id, user_id) WHERE clock_out_at IS NULL'
                );
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS attendance_one_open_session_per_user_org');
    }
};
