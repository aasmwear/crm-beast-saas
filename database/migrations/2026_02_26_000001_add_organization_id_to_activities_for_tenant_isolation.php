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
        Schema::table('activities', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable()->after('id');
        });

        // Backfill from subject: Project, Task, Invoice all have organization_id
        $firstOrgId = DB::table('organizations')->orderBy('id')->value('id') ?? 1;
        $activities = DB::table('activities')->get();
        foreach ($activities as $a) {
            $orgId = $this->resolveOrganizationId($a->subject_type, $a->subject_id) ?? $firstOrgId;
            if ($orgId !== null) {
                DB::table('activities')->where('id', $a->id)->update(['organization_id' => $orgId]);
            }
        }

        Schema::table('activities', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable(false)->change();
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->index(['organization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['organization_id', 'created_at']);
            $table->dropColumn('organization_id');
        });
    }

    private function resolveOrganizationId(?string $subjectType, $subjectId): ?int
    {
        if (! $subjectType || ! $subjectId) {
            return null;
        }

        $table = match ($subjectType) {
            'App\Models\Project' => 'projects',
            'App\Models\Task' => 'tasks',
            'App\Models\Invoice' => 'invoices',
            default => null,
        };

        if ($table === null) {
            return null;
        }

        $row = DB::table($table)->where('id', $subjectId)->value('organization_id');

        return $row !== null ? (int) $row : null;
    }
};
