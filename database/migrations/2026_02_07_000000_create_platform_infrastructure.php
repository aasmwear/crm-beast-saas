<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Platform Admins Table
        Schema::create('platform_admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('avatar_path')->nullable();
            $table->enum('role', ['super_admin', 'support'])->default('support');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
            
            $table->index('email');
            $table->index('is_active');
        });

        // Platform Password Resets Table
        Schema::create('platform_password_resets', function (Blueprint $table) {
            $table->string('email')->index();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // Organization Features Table
        Schema::create('organization_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();
            $table->jsonb('features')->default('{"attendance": true, "sms": false, "api_access": false, "storage_gb": 5}');
            $table->enum('subscription_status', ['active', 'past_due', 'cancelled'])->default('active');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();
            
            $table->unique('organization_id');
            $table->index('subscription_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_features');
        Schema::dropIfExists('platform_password_resets');
        Schema::dropIfExists('platform_admins');
    }
};
