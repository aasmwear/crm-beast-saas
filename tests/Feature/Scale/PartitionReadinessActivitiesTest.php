<?php

declare(strict_types=1);

namespace Tests\Feature\Scale;

use App\Models\Activity;
use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class PartitionReadinessActivitiesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_project_show_excludes_activities_with_mismatched_organization_id(): void
    {
        $orgA = Organization::factory()->create(['slug' => 'org-a']);
        $orgB = Organization::factory()->create(['slug' => 'org-b']);
        $user = User::factory()->create(['active_organization_id' => $orgA->id]);
        $orgA->users()->attach($user->id);

        $role = Role::create([
            'name' => 'partition-project-viewer',
            'guard_name' => 'web',
            'team_id' => $orgA->id,
        ]);
        $role->givePermissionTo('projects.view');
        app(PermissionRegistrar::class)->setPermissionsTeamId($orgA->id);
        $user->assignRole($role);

        $clientA = Client::factory()->create(['organization_id' => $orgA->id]);
        $project = Project::factory()->create([
            'organization_id' => $orgA->id,
            'client_id' => $clientA->id,
            'title' => 'Scoped Project',
        ]);

        $good = Activity::query()->create([
            'organization_id' => $orgA->id,
            'user_id' => $user->id,
            'description' => 'legitimate activity',
            'subject_type' => Project::class,
            'subject_id' => $project->id,
            'properties' => null,
        ]);

        DB::table('activities')->insert([
            'organization_id' => $orgB->id,
            'user_id' => null,
            'description' => 'wrong organization_id same morph keys',
            'subject_type' => Project::class,
            'subject_id' => $project->id,
            'properties' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->get(route('projects.show', [
                'organization' => $orgA->slug,
                'project' => $project->id,
            ]));

        $response->assertOk();
        $activities = $response->viewData('page')['props']['activities'];
        $this->assertCount(1, $activities);
        $this->assertSame('legitimate activity', $activities[0]['description']);
        $this->assertSame($good->id, $activities[0]['id']);
    }
}
