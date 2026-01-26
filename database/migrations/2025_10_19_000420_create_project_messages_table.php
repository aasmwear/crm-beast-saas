<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_messages')) {
            Schema::create('project_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('project_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('parent_id')->nullable()->index();
                $table->text('body')->nullable();
                $table->json('attachments')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_messages');
    }
};
