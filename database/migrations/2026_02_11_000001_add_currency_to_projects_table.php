<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('projects', 'currency')) {
            return;
        }

        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'price_cents')) {
                $table->string('currency', 3)->default('USD')->after('price_cents');
            } elseif (Schema::hasColumn('projects', 'price')) {
                $table->string('currency', 3)->default('USD')->after('price');
            } else {
                $table->string('currency', 3)->default('USD');
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'currency')) {
                $table->dropColumn('currency');
            }
        });
    }
};
