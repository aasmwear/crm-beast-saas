<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add mode column: augment (adds to base) | set (overrides with value_int).
     */
    public function up(): void
    {
        Schema::table('organization_addons', function (Blueprint $table) {
            $table->string('mode', 16)->default('augment')->after('value_int');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organization_addons', function (Blueprint $table) {
            $table->dropColumn('mode');
        });
    }
};
