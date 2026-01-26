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
        /**
         * If our custom org-level notifications table exists, rename it out of the way
         * so we can create Laravel's per-user notifications table with the canonical name.
         */
        if (Schema::hasTable('notifications') && ! Schema::hasTable('notification_events')) {
            // Heuristic: our custom table includes organization_id (Laravel's does not).
            if (Schema::hasColumn('notifications', 'organization_id')) {
                Schema::rename('notifications', 'notification_events');
            }
        }

        /**
         * Create Laravel's per-user notifications table (DatabaseNotification).
         * We use jsonb on Postgres so we can filter by org (data->>'organization_id').
         */
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');

                if (DB::getDriverName() === 'pgsql') {
                    $table->jsonb('data');
                } else {
                    $table->json('data');
                }

                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->index(['notifiable_id', 'notifiable_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');

        // Dev-friendly rollback: only rename back if the canonical name isn't taken.
        if (Schema::hasTable('notification_events') && ! Schema::hasTable('notifications')) {
            Schema::rename('notification_events', 'notifications');
        }
    }
};
