<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_daily_metrics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->date('metric_date');

            $table->unsignedInteger('clients_count')->default(0);
            $table->unsignedInteger('projects_count')->default(0);
            $table->unsignedInteger('tasks_count')->default(0);
            $table->unsignedInteger('open_tasks_count')->default(0);
            $table->unsignedInteger('attendance_count')->default(0);
            $table->unsignedInteger('activities_count')->default(0);
            $table->unsignedInteger('invoices_count')->default(0);
            $table->unsignedBigInteger('revenue_cents')->default(0);
            $table->unsignedBigInteger('outstanding_cents')->default(0);
            $table->unsignedInteger('users_count')->default(0);

            $table->timestamps();

            $table->unique(['organization_id', 'metric_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_daily_metrics');
    }
};
