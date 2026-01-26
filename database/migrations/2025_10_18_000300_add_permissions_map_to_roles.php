<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('roles') && ! Schema::hasColumn('roles', 'permissions_map')) {
            Schema::table('roles', function (Blueprint $t) {
                $t->json('permissions_map')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('roles') && Schema::hasColumn('roles', 'permissions_map')) {
            Schema::table('roles', function (Blueprint $t) {
                $t->dropColumn('permissions_map');
            });
        }
    }
};
