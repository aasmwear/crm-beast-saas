<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('tier', 32)->default('small')->after('plan');
            $table->index('tier');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropIndex(['tier']);
            $table->dropColumn('tier');
        });
    }
};
