<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cold archive for activities (Phase 3). Same logical columns as hot table plus archived_at.
 * Foreign keys omitted on archive for operational safety on historical rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities_archive', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('description');
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->json('properties')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('archived_at');

            $table->index(['organization_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('archived_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities_archive');
    }
};
