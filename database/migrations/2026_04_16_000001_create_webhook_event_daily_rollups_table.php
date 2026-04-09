<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Read-model: per-calendar-day aggregates (app timezone) over stripe_webhook_events.
     */
    public function up(): void
    {
        Schema::create('webhook_event_daily_rollups', function (Blueprint $table): void {
            $table->id();
            $table->string('organization_scope', 64);
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 32)->default('stripe');
            $table->string('event_type');
            $table->date('event_date');
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failure_count')->default(0);
            $table->timestamp('last_received_at')->nullable();
            $table->timestamp('last_processed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['provider', 'organization_scope', 'event_type', 'event_date'],
                'webhook_event_daily_rollups_provider_scope_type_date_unique'
            );
            $table->index(['provider', 'event_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_event_daily_rollups');
    }
};
