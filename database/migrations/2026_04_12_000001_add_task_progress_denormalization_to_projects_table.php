<?php

declare(strict_types=1);

use App\Models\Project;
use App\Services\ProjectTaskProgressService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->unsignedInteger('tasks_count')->default(0);
            $table->unsignedInteger('open_tasks_count')->default(0);
            $table->unsignedInteger('completed_tasks_count')->default(0);
            $table->unsignedTinyInteger('progress_percent')->default(0);
        });

        Project::query()->orderBy('id')->chunkById(100, function ($projects): void {
            foreach ($projects as $project) {
                ProjectTaskProgressService::recalculateForProjectId((int) $project->id);
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn([
                'tasks_count',
                'open_tasks_count',
                'completed_tasks_count',
                'progress_percent',
            ]);
        });
    }
};
