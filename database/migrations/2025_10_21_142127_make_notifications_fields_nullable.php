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
        Schema::table('notifications', function (Blueprint $table) {
            // FIX: Make the two columns nullable to prevent the NOT NULL constraint error.
            // We use `unsignedBigInteger` and `jsonb` as educated guesses for a better schema
            // if the original type was just `integer` or `text`.

            // Assuming actor_id is an integer (or BigInteger) foreign key
            $table->unsignedBigInteger('actor_id')->nullable()->change();

            // Assuming recipient_ids was a string/JSON/array column
            // We use `jsonb` or `text` here, `text` is safer if you don't know the original type.
            $table->text('recipient_ids')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // Note: Reverting NOT NULL constraints is risky without data cleanup.
            // For now, leave this empty or be prepared to manually adjust.
        });
    }
};
