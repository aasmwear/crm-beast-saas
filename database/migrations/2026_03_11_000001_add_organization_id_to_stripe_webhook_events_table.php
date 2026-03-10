<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds organization_id for platform-admin webhook observability (org-scoped webhook status).
     */
    public function up(): void
    {
        Schema::table('stripe_webhook_events', function (Blueprint $table): void {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stripe_webhook_events', function (Blueprint $table): void {
            $table->dropForeign(['organization_id']);
        });
    }
};
