<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'client_id')) {
                $table->foreignId('client_id')->nullable()->after('active_organization_id')->constrained('clients')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'client_id')) {
                $table->dropForeign(['client_id']);
            }
        });
    }
};
