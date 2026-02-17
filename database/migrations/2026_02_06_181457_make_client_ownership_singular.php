<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Refactor client ownership from JSON arrays to single foreign keys.
     * This enforces strict accountability: one fronter, one closer.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Drop old JSON columns
            $table->dropColumn('fronter');
            $table->dropColumn('closer');

            // Add new foreign key columns (nullable for backward compatibility)
            $table->unsignedBigInteger('fronter_id')->nullable()->after('address');
            $table->unsignedBigInteger('closer_id')->nullable()->after('fronter_id');

            // Add foreign key constraints with cascade on delete
            $table->foreign('fronter_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('closer_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            // Add indexes for performance
            $table->index('fronter_id');
            $table->index('closer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Drop foreign key constraints first
            $table->dropForeign(['fronter_id']);
            $table->dropForeign(['closer_id']);

            // Drop the columns
            $table->dropColumn(['fronter_id', 'closer_id']);

            // Restore old JSON columns
            $table->json('fronter')->nullable();
            $table->json('closer')->nullable();
        });
    }
};
