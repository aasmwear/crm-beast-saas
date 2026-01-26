<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('tasks', 'submission_note')) {
                $table->text('submission_note')->nullable();
            }
            if (! Schema::hasColumn('tasks', 'submission_files')) {
                $table->json('submission_files')->nullable();
            }
            if (! Schema::hasColumn('tasks', 'review_status')) {
                $table->string('review_status')->nullable()->index();
            }
            if (! Schema::hasColumn('tasks', 'reviewed_by_id')) {
                $table->unsignedBigInteger('reviewed_by_id')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            foreach (['submission_note', 'submission_files', 'review_status', 'reviewed_by_id'] as $col) {
                if (Schema::hasColumn('tasks', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
