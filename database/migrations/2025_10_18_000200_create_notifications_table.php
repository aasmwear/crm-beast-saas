<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $t->string('type'); // mention, task_assigned, task_submitted, attendance_edited, announcement_posted, client_updated, project_assigned, new_message, new_activity
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->json('recipient_ids'); // array of user ids
            $t->string('entity'); // client | project | task | announcement | message
            $t->unsignedBigInteger('entity_id');
            $t->json('payload')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->index(['organization_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
