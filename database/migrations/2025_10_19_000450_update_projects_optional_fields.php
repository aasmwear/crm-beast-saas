<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (! Schema::hasColumn('projects', 'budget')) {
                $table->decimal('budget', 12, 2)->nullable();
            }
            if (! Schema::hasColumn('projects', 'price')) {
                $table->decimal('price', 12, 2)->nullable();
            }
            if (! Schema::hasColumn('projects', 'billable')) {
                $table->boolean('billable')->default(false);
            }
            if (! Schema::hasColumn('projects', 'notes_by_pm')) {
                $table->text('notes_by_pm')->nullable();
            }
            if (! Schema::hasColumn('projects', 'custom_fields')) {
                $table->json('custom_fields')->nullable();
            }
            if (! Schema::hasColumn('projects', 'attachments')) {
                $table->json('attachments')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            foreach (['budget', 'price', 'billable', 'notes_by_pm', 'custom_fields', 'attachments'] as $col) {
                if (Schema::hasColumn('projects', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
