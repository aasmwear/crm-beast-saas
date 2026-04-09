<?php

declare(strict_types=1);

namespace Tests\Feature\Lifecycle;

use App\Models\Attendance;
use App\Models\Client;
use App\Models\Comment;
use App\Models\CustomField;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class LifecyclePurgeSoftDeletedTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_purge_soft_deleted_dry_run_deletes_nothing(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-15 12:00:00'));

        $org = Organization::factory()->create();
        $client = Client::factory()->create(['organization_id' => $org->id]);
        $client->delete(); // soft
        $client->forceFill(['deleted_at' => Carbon::parse('2026-05-01 10:00:00')])->saveQuietly();

        $this->artisan('lifecycle:purge-soft-deleted', ['--table' => 'clients'])
            ->assertSuccessful()
            ->expectsOutputToContain('DRY-RUN');

        $this->assertNotNull(Client::withTrashed()->find($client->id));
    }

    public function test_purge_clients_execute_removes_eligible_trashed_client(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-15 12:00:00'));

        $org = Organization::factory()->create();
        $client = Client::factory()->create(['organization_id' => $org->id]);
        $client->delete();
        $client->forceFill(['deleted_at' => Carbon::parse('2026-05-01 10:00:00')])->saveQuietly();

        $this->artisan('lifecycle:purge-soft-deleted', [
            '--table' => 'clients',
            '--execute' => true,
        ])->assertSuccessful();

        $this->assertNull(Client::withTrashed()->find($client->id));
    }

    public function test_purge_clients_skips_when_projects_exist(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-15 12:00:00'));

        $org = Organization::factory()->create();
        $manager = User::factory()->create();
        $org->users()->attach($manager->id, ['is_owner' => false]);

        $client = Client::factory()->create(['organization_id' => $org->id]);
        Project::factory()->create([
            'organization_id' => $org->id,
            'client_id' => $client->id,
            'project_manager_id' => $manager->id,
        ]);

        $client->delete();
        $client->forceFill(['deleted_at' => Carbon::parse('2026-05-01 10:00:00')])->saveQuietly();

        $this->artisan('lifecycle:purge-soft-deleted', [
            '--table' => 'clients',
            '--execute' => true,
        ])->assertSuccessful()
            ->expectsOutputToContain('Blocked');

        $this->assertNotNull(Client::withTrashed()->find($client->id));
    }

    public function test_purge_clients_skips_when_invoices_exist(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-15 12:00:00'));

        $org = Organization::factory()->create();
        $client = Client::factory()->create(['organization_id' => $org->id]);

        DB::table('invoices')->insert([
            'organization_id' => $org->id,
            'client_id' => $client->id,
            'project_id' => null,
            'number' => 'INV-PURGE-TEST-1',
            'issue_date' => '2026-01-01',
            'due_date' => '2026-02-01',
            'status' => 'Draft',
            'total_cents' => 0,
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $client->delete();
        $client->forceFill(['deleted_at' => Carbon::parse('2026-05-01 10:00:00')])->saveQuietly();

        $this->artisan('lifecycle:purge-soft-deleted', [
            '--table' => 'clients',
            '--execute' => true,
        ])->assertSuccessful()
            ->expectsOutputToContain('Blocked');

        $this->assertNotNull(Client::withTrashed()->find($client->id));
    }

    public function test_purge_clients_removes_custom_field_values_first(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-15 12:00:00'));

        $org = Organization::factory()->create();
        $client = Client::factory()->create(['organization_id' => $org->id]);

        $field = CustomField::query()->create([
            'organization_id' => $org->id,
            'entity' => CustomField::ENTITY_CLIENT,
            'label' => 'Tier',
            'slug' => 'tier-' . uniqid(),
            'type' => CustomField::TYPE_TEXT,
            'is_required' => false,
            'sort_order' => 0,
        ]);

        DB::table('custom_field_values')->insert([
            'custom_field_id' => $field->id,
            'entity_type' => CustomField::ENTITY_CLIENT,
            'entity_id' => $client->id,
            'value_text' => 'Gold',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $client->delete();
        $client->forceFill(['deleted_at' => Carbon::parse('2026-05-01 10:00:00')])->saveQuietly();

        $this->artisan('lifecycle:purge-soft-deleted', [
            '--table' => 'clients',
            '--execute' => true,
        ])->assertSuccessful();

        $this->assertNull(Client::withTrashed()->find($client->id));
        $this->assertSame(0, DB::table('custom_field_values')->where('entity_id', $client->id)->count());
    }

    public function test_purge_clients_respects_organization_scope(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-15 12:00:00'));

        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $clientA = Client::factory()->create(['organization_id' => $orgA->id]);
        $clientB = Client::factory()->create(['organization_id' => $orgB->id]);

        $clientA->delete();
        $clientA->forceFill(['deleted_at' => Carbon::parse('2026-05-01 10:00:00')])->saveQuietly();
        $clientB->delete();
        $clientB->forceFill(['deleted_at' => Carbon::parse('2026-05-01 10:00:00')])->saveQuietly();

        $this->artisan('lifecycle:purge-soft-deleted', [
            '--table' => 'clients',
            '--execute' => true,
            '--organization' => (string) $orgA->id,
        ])->assertSuccessful();

        $this->assertNull(Client::withTrashed()->find($clientA->id));
        $this->assertNotNull(Client::withTrashed()->find($clientB->id));
    }

    public function test_purge_clients_preserves_recent_soft_delete(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-15 12:00:00'));

        $org = Organization::factory()->create();
        $client = Client::factory()->create(['organization_id' => $org->id]);
        $client->delete();
        $client->forceFill(['deleted_at' => Carbon::parse('2026-06-10 10:00:00')])->saveQuietly();

        $this->artisan('lifecycle:purge-soft-deleted', [
            '--table' => 'clients',
            '--execute' => true,
        ])->assertSuccessful();

        $this->assertNotNull(Client::withTrashed()->find($client->id));
    }

    public function test_purge_tasks_execute_removes_trashed_task_and_morph_rows(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-15 12:00:00'));

        $org = Organization::factory()->create();
        $manager = User::factory()->create();
        $org->users()->attach($manager->id, ['is_owner' => false]);

        $client = Client::factory()->create(['organization_id' => $org->id]);
        $project = Project::factory()->create([
            'organization_id' => $org->id,
            'client_id' => $client->id,
            'project_manager_id' => $manager->id,
        ]);

        $task = Task::factory()->create([
            'organization_id' => $org->id,
            'project_id' => $project->id,
        ]);

        $user = User::factory()->create();
        $org->users()->attach($user->id, ['is_owner' => false]);

        $comment = Comment::query()->create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'body' => 'c',
            'commentable_type' => $task->getMorphClass(),
            'commentable_id' => $task->id,
        ]);

        $task->delete();
        $task->forceFill(['deleted_at' => Carbon::parse('2026-05-01 10:00:00')])->saveQuietly();

        $this->artisan('lifecycle:purge-soft-deleted', [
            '--table' => 'tasks',
            '--execute' => true,
        ])->assertSuccessful();

        $this->assertNull(Task::withTrashed()->find($task->id));
        $this->assertNull(Comment::query()->find($comment->id));
    }

    public function test_purge_tasks_preserves_active_task(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-15 12:00:00'));

        $org = Organization::factory()->create();
        $manager = User::factory()->create();
        $org->users()->attach($manager->id, ['is_owner' => false]);

        $client = Client::factory()->create(['organization_id' => $org->id]);
        $project = Project::factory()->create([
            'organization_id' => $org->id,
            'client_id' => $client->id,
            'project_manager_id' => $manager->id,
        ]);

        $task = Task::factory()->create([
            'organization_id' => $org->id,
            'project_id' => $project->id,
        ]);

        $this->artisan('lifecycle:purge-soft-deleted', [
            '--table' => 'tasks',
            '--execute' => true,
        ])->assertSuccessful();

        $this->assertNotNull(Task::query()->find($task->id));
    }

    public function test_purge_attendance_execute_removes_old_trashed_row(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-15 12:00:00'));

        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $org->users()->attach($user->id, ['is_owner' => false]);

        $row = Attendance::factory()->create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
        ]);
        $row->delete();
        $row->forceFill(['deleted_at' => Carbon::parse('2026-05-01 10:00:00')])->saveQuietly();

        $this->artisan('lifecycle:purge-soft-deleted', [
            '--table' => 'attendance',
            '--execute' => true,
        ])->assertSuccessful();

        $this->assertNull(Attendance::withTrashed()->find($row->id));
    }

    public function test_purge_rejects_invalid_table(): void
    {
        $this->artisan('lifecycle:purge-soft-deleted', ['--table' => 'users'])
            ->assertFailed();
    }
}
