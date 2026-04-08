<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cold archive for audit_logs (Phase 3). Same logical columns as hot table plus archived_at.
 * Foreign keys omitted so archived rows are not cascade-deleted with live org/user churn.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs_archive', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action');
            $table->string('entity');
            $table->unsignedBigInteger('entity_id');
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('archived_at');

            $table->index(['organization_id', 'created_at']);
            $table->index(['entity', 'entity_id']);
            $table->index('archived_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs_archive');
    }
};
