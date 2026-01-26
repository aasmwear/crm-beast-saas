<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('announcements')) {
            Schema::create('announcements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('organization_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index(); // author
                $table->string('title');
                $table->text('body')->nullable();
                $table->string('scope')->default('company');
                $table->unsignedBigInteger('department_id')->nullable()->index();
                $table->unsignedBigInteger('project_id')->nullable()->index();
                $table->boolean('pinned')->default(false);
                $table->timestampTz('published_at')->nullable()->index();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
