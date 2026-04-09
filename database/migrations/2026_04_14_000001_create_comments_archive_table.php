<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Archive twin for comments (warm lifecycle). Same logical columns as hot + archived_at.
 * No FKs on archive for operational safety on historical rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments_archive', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('user_id');
            $table->text('body');
            $table->string('commentable_type');
            $table->unsignedBigInteger('commentable_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('archived_at');

            $table->index(['organization_id', 'created_at']);
            $table->index(['commentable_type', 'commentable_id']);
            $table->index('archived_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments_archive');
    }
};
