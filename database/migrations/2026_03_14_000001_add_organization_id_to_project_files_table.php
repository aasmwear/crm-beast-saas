<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('project_files', function (Blueprint $table): void {
            $table->foreignId('organization_id')->nullable()->after('id');
        });

        DB::table('project_files')
            ->whereNull('organization_id')
            ->update([
                'organization_id' => DB::raw('(select organization_id from projects where projects.id = project_files.project_id)'),
            ]);

        $remainingNulls = DB::table('project_files')->whereNull('organization_id')->count();
        if ($remainingNulls > 0) {
            throw new RuntimeException('Backfill failed: some project_files rows still have NULL organization_id.');
        }

        Schema::table('project_files', function (Blueprint $table): void {
            $table->foreignId('organization_id')->nullable(false)->change();
            $table->index('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_files', function (Blueprint $table): void {
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['organization_id']);
            $table->dropColumn('organization_id');
        });
    }
};
