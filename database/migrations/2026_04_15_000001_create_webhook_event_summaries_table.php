<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Read-model table: denormalized aggregates over stripe_webhook_events.
     * organization_scope is 'unscoped' when organization_id is null (portable unique key).
     */
    public function up(): void
    {
        Schema::create('webhook_event_summaries', function (Blueprint $table): void {
            $table->id();
            $table->string('organization_scope', 64);
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 32)->default('stripe');
            $table->string('event_type');
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failure_count')->default(0);
            $table->timestamp('last_received_at')->nullable();
            $table->timestamp('last_processed_at')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->text('last_error_message')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'organization_scope', 'event_type'], 'webhook_event_summaries_provider_scope_type_unique');
            $table->index(['provider', 'organization_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_event_summaries');
    }
};
