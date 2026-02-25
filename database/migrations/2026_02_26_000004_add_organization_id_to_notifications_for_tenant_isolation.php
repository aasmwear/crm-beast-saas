<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        Schema::table('notifications', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable()->after('notifiable_id');
        });

        // Backfill from data->>'organization_id' (JSON)
        if ($driver === 'pgsql') {
            DB::statement("
                UPDATE notifications
                SET organization_id = (data->>'organization_id')::bigint
                WHERE data->>'organization_id' IS NOT NULL
                  AND (data->>'organization_id') ~ '^[0-9]+$'
            ");
        } else {
            $rows = DB::table('notifications')->get();
            foreach ($rows as $row) {
                $data = is_string($row->data ?? null) ? json_decode($row->data, true) : (array) ($row->data ?? []);
                $orgId = isset($data['organization_id']) ? (int) $data['organization_id'] : null;
                if ($orgId > 0) {
                    DB::table('notifications')->where('id', $row->id)->update(['organization_id' => $orgId]);
                }
            }
        }

        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['organization_id', 'read_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'read_at', 'created_at']);
            $table->dropColumn('organization_id');
        });
    }
};
