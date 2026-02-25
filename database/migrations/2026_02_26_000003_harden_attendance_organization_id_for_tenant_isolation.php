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
        // Backfill: set organization_id from user's active_organization_id for rows where it's null
        $nullRows = DB::table('attendance')
            ->whereNull('organization_id')
            ->whereNotNull('user_id')
            ->select('id', 'user_id')
            ->get();

        foreach ($nullRows as $row) {
            $orgId = DB::table('users')->where('id', $row->user_id)->value('active_organization_id');
            if ($orgId !== null) {
                DB::table('attendance')->where('id', $row->id)->update(['organization_id' => $orgId]);
            }
        }

        // For any remaining nulls (user deleted or no active org), use first org
        $firstOrgId = DB::table('organizations')->orderBy('id')->value('id');
        if ($firstOrgId !== null) {
            DB::table('attendance')->whereNull('organization_id')->update(['organization_id' => $firstOrgId]);
        }

        Schema::table('attendance', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable(false)->change();
            $table->index(['organization_id', 'user_id', 'clock_in_at']);
        });
    }

    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'user_id', 'clock_in_at']);
            $table->unsignedBigInteger('organization_id')->nullable()->change();
        });
    }
};
