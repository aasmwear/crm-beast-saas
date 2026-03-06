<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add-on entitlements per org (e.g. extra storage_gb, api_rpm).
     */
    public function up(): void
    {
        Schema::create('organization_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->string('addon_key')->index();
            $table->unsignedInteger('quantity')->default(1);
            $table->integer('value_int')->nullable(); // for numeric entitlements (e.g. +50 storage_gb)
            $table->boolean('active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'addon_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_addons');
    }
};
