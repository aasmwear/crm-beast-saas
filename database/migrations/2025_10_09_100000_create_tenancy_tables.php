<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('plan')->default('trial');
            $t->json('settings')->nullable();
            $t->timestamps();
        });

        Schema::create('organization_domains', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->string('domain')->unique();
            $t->timestamps();
        });

        Schema::create('organization_user', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->boolean('is_owner')->default(false);
            $t->timestamps();
            $t->unique(['organization_id', 'user_id']);
        });

        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('active_organization_id')->nullable()->after('id')
                ->constrained('organizations')->nullOnDelete();
            $t->string('designation')->nullable();
            $t->string('timezone')->nullable();
            $t->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('departments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $t->string('name');
            $t->string('code')->index(); // e.g. CST, SALES, CREATIVE
            $t->timestamps();
            $t->unique(['organization_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('active_organization_id');
            $t->dropConstrainedForeignId('manager_id');
            $t->dropColumn(['designation', 'timezone']);
        });
        Schema::dropIfExists('organization_user');
        Schema::dropIfExists('organization_domains');
        Schema::dropIfExists('organizations');
    }
};
