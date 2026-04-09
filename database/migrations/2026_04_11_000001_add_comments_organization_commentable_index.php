<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table): void {
            $table->index(
                ['organization_id', 'commentable_type', 'commentable_id'],
                'comments_org_commentable_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table): void {
            $table->dropIndex('comments_org_commentable_idx');
        });
    }
};
