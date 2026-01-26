<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // FIX: Add conditional check to prevent "Duplicate table" error
        if (! Schema::hasTable('announcements')) {
            Schema::create('announcements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id');
                $table->string('title');
                $table->text('body')->nullable();
                $table->boolean('pinned')->default(false);
                $table->timestamps();

                $table->index('organization_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
