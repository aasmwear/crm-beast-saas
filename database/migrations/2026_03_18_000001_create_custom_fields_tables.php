<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_fields', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $t->string('entity', 64); // client, project, ...
            $t->string('label');
            $t->string('slug', 100); // unique per org+entity
            $t->string('type', 32); // text, number, date, select, multiselect, checkbox
            $t->json('options')->nullable(); // for select/multiselect
            $t->boolean('is_required')->default(false);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
            $t->unique(['organization_id', 'entity', 'slug']);
            $t->index(['organization_id', 'entity']);
        });

        Schema::create('custom_field_values', function (Blueprint $t) {
            $t->id();
            $t->foreignId('custom_field_id')->constrained('custom_fields')->cascadeOnDelete();
            $t->string('entity_type', 64); // App\Models\Client, ...
            $t->unsignedBigInteger('entity_id');
            $t->text('value_text')->nullable();
            $t->decimal('value_number', 18, 4)->nullable();
            $t->date('value_date')->nullable();
            $t->json('value_json')->nullable(); // for multiselect, etc.
            $t->timestamps();
            $t->unique(['custom_field_id', 'entity_type', 'entity_id']);
            $t->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_values');
        Schema::dropIfExists('custom_fields');
    }
};
