<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Phase 4: Add Geo-Proofing fields to attendance table.
     * - lat/lng for clock in/out
     * - proof_image_path for selfie verification
     * - business_date for midnight split logic
     */
    public function up(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            // Geo-proof fields for clock in
            $table->decimal('clock_in_lat', 10, 7)->nullable()->after('clock_in_ip');
            $table->decimal('clock_in_lng', 10, 7)->nullable()->after('clock_in_lat');
            
            // Geo-proof fields for clock out
            $table->decimal('clock_out_lat', 10, 7)->nullable()->after('clock_out_ip');
            $table->decimal('clock_out_lng', 10, 7)->nullable()->after('clock_out_lat');
            
            // Visual proof (selfie)
            $table->string('proof_image_path')->nullable()->after('clock_out_lng');
            
            // Business date for reporting (handles midnight splits)
            $table->date('business_date')->nullable()->after('user_id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropColumn([
                'clock_in_lat',
                'clock_in_lng',
                'clock_out_lat',
                'clock_out_lng',
                'proof_image_path',
                'business_date',
            ]);
        });
    }
};
