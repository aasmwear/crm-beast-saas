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
        Schema::table('comments', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable()->after('id');
        });

        // Backfill from commentable: Project and Task have organization_id
        $firstOrgId = DB::table('organizations')->orderBy('id')->value('id') ?? 1;
        $comments = DB::table('comments')->get();
        foreach ($comments as $c) {
            $orgId = $this->resolveOrganizationId($c->commentable_type, $c->commentable_id) ?? $firstOrgId;
            if ($orgId !== null) {
                DB::table('comments')->where('id', $c->id)->update(['organization_id' => $orgId]);
            }
        }

        Schema::table('comments', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_id')->nullable(false)->change();
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->index(['organization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['organization_id', 'created_at']);
            $table->dropColumn('organization_id');
        });
    }

    private function resolveOrganizationId(?string $commentableType, $commentableId): ?int
    {
        if (! $commentableType || ! $commentableId) {
            return null;
        }

        $table = match ($commentableType) {
            'App\Models\Project' => 'projects',
            'App\Models\Task' => 'tasks',
            default => null,
        };

        if ($table === null) {
            return null;
        }

        $row = DB::table($table)->where('id', $commentableId)->value('organization_id');

        return $row !== null ? (int) $row : null;
    }
};
