<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('clients', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $t->string('company_name');
            $t->string('industry')->nullable();
            $t->string('niche')->nullable();
            $t->string('primary_contact_name')->nullable();
            $t->string('primary_contact_email')->nullable();
            $t->string('primary_contact_phone')->nullable();
            $t->string('website')->nullable();
            $t->text('address')->nullable();
            $t->json('tags')->nullable();
            $t->json('fronter')->nullable(); // [user_id,...]
            $t->json('closer')->nullable();  // [user_id,...]
            $t->foreignId('assigned_account_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('google_business_profile_status')->default('Not Created');
            $t->string('google_business_profile_access_status')->default('No Access');
            $t->string('client_activation_status')->default('Inactive');
            $t->text('notes_by_cst')->nullable();
            $t->text('notes_by_sales')->nullable();
            $t->text('notes_by_tech')->nullable();
            $t->string('status')->default('active');
            $t->timestamps();
            $t->softDeletes();
            $t->index(['organization_id', 'assigned_account_manager_id']);
        });

        Schema::create('projects', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $t->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $t->string('title');
            $t->string('project_code')->nullable(); // unique per org
            $t->text('description')->nullable();
            $t->foreignId('project_manager_id')->constrained('users');
            $t->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->string('status')->default('active');
            $t->decimal('budget', 12, 2)->nullable();
            $t->decimal('price', 12, 2)->nullable();
            $t->boolean('billable')->default(true);
            $t->string('google_business_profile_status')->nullable();
            $t->string('google_business_profile_access_status')->nullable();
            $t->string('client_activation_status')->nullable();
            $t->text('notes_by_cst')->nullable();
            $t->text('notes_by_sales')->nullable();
            $t->text('notes_by_tech')->nullable();
            $t->json('attachments')->nullable();
            $t->json('custom_fields')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['organization_id', 'client_id']);
            $t->unique(['organization_id', 'project_code']);
        });

        Schema::create('tasks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $t->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $t->string('title');
            $t->text('description')->nullable();
            $t->json('assignees')->nullable(); // [user_id,...]
            $t->date('due_date')->nullable();
            $t->string('priority')->default('normal');
            $t->string('status')->default('open');
            $t->decimal('estimated_hours', 8, 2)->nullable();
            $t->decimal('logged_hours', 8, 2)->default(0);
            $t->json('subtasks')->nullable();
            $t->json('attachments')->nullable();
            $t->json('comments')->nullable();
            $t->json('submission')->nullable(); // {file_id,note}
            $t->string('review_status')->default('pending');
            $t->timestamps();
            $t->softDeletes();
            $t->index(['organization_id', 'project_id']);
        });

        Schema::create('announcements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $t->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $t->string('scope')->default('company'); // company|department|project
            $t->json('targets')->nullable();        // [department_id|project_id]
            $t->string('title');
            $t->text('body');
            $t->boolean('pinned')->default(false);
            $t->timestamps();
            $t->index(['organization_id', 'scope']);
        });

        Schema::create('project_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $t->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $t->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('parent_id')->nullable()->constrained('project_messages')->nullOnDelete();
            $t->text('body');
            $t->json('attachments')->nullable();
            $t->timestamps();
            $t->index(['organization_id', 'project_id']);
        });

        Schema::create('notifications_center', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $t->string('type');
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->json('recipient_ids')->nullable(); // [user_id,...]
            $t->string('entity')->nullable();      // clients|projects|tasks
            $t->unsignedBigInteger('entity_id')->nullable();
            $t->json('payload')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->index(['organization_id', 'type']);
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('action');
            $t->string('entity');
            $t->unsignedBigInteger('entity_id');
            $t->json('changes')->nullable();
            $t->timestamps();
            $t->index(['organization_id', 'entity', 'entity_id']);
        });

        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $t->string('key');
            $t->json('value')->nullable();
            $t->timestamps();
            $t->unique(['organization_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('notifications_center');
        Schema::dropIfExists('project_messages');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('clients');
    }
};
