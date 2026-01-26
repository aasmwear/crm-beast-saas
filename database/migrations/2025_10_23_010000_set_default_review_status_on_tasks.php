<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tasks') && Schema::hasColumn('tasks', 'review_status')) {
            DB::statement("ALTER TABLE tasks ALTER COLUMN review_status SET DEFAULT 'Pending'");
            DB::statement("UPDATE tasks SET review_status = 'Pending' WHERE review_status IS NULL");
            DB::statement('ALTER TABLE tasks ALTER COLUMN review_status SET NOT NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tasks') && Schema::hasColumn('tasks', 'review_status')) {
            DB::statement('ALTER TABLE tasks ALTER COLUMN review_status DROP DEFAULT');
        }
    }
};
