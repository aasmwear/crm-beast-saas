<?php

declare(strict_types=1);

namespace Tests\Feature\Projects;

use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class ProjectFileTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Storage::fake('public');
    }

    public function test_store_sets_organization_id_on_project_file(): void
    {
        [$org, $user, $project] = $this->tenantProjectContext('acme');

        $response = $this->actingAs($user)->post(
            route('projects.files.store', ['organization' => $org->slug, 'project' => $project->id]),
            ['file' => UploadedFile::fake()->create('tenant-safe.pdf', 128)]
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('project_files', [
            'organization_id' => $org->id,
            'project_id' => $project->id,
            'filename' => 'tenant-safe.pdf',
        ]);
    }

    public function test_cross_tenant_mismatched_project_file_is_blocked(): void
    {
        [$orgA, $userA, $projectA] = $this->tenantProjectContext('acme');
        [$orgB] = $this->tenantProjectContext('beta');

        $path = "project_files/{$projectA->id}/cross-tenant.bin";
        Storage::disk('public')->put($path, 'cross-tenant');

        $fileId = DB::table('project_files')->insertGetId([
            'organization_id' => $orgB->id,
            'project_id' => $projectA->id,
            'user_id' => $userA->id,
            'filename' => 'cross-tenant.bin',
            'path' => $path,
            'mime_type' => 'application/octet-stream',
            'size' => 12,
            'is_visible_to_client' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($userA)->get(
            route('projects.files.download', [
                'organization' => $orgA->slug,
                'project' => $projectA->id,
                'projectFile' => $fileId,
            ])
        );

        $response->assertStatus(404);
    }

    public function test_same_tenant_file_actions_still_work(): void
    {
        [$org, $user, $project] = $this->tenantProjectContext('acme');

        $path = "project_files/{$project->id}/same-tenant.txt";
        Storage::disk('public')->put($path, 'same-tenant');

        $fileId = DB::table('project_files')->insertGetId([
            'organization_id' => $org->id,
            'project_id' => $project->id,
            'user_id' => $user->id,
            'filename' => 'same-tenant.txt',
            'path' => $path,
            'mime_type' => 'text/plain',
            'size' => 11,
            'is_visible_to_client' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $download = $this->actingAs($user)->get(
            route('projects.files.download', [
                'organization' => $org->slug,
                'project' => $project->id,
                'projectFile' => $fileId,
            ])
        );
        $download->assertOk();

        $toggle = $this->actingAs($user)->patch(
            route('projects.files.toggleVisibility', [
                'organization' => $org->slug,
                'project' => $project->id,
                'projectFile' => $fileId,
            ])
        );
        $toggle->assertRedirect();
        $this->assertDatabaseHas('project_files', [
            'id' => $fileId,
            'organization_id' => $org->id,
            'is_visible_to_client' => true,
        ]);

        $delete = $this->actingAs($user)->delete(
            route('projects.files.destroy', [
                'organization' => $org->slug,
                'project' => $project->id,
                'projectFile' => $fileId,
            ])
        );
        $delete->assertRedirect();
        $this->assertDatabaseMissing('project_files', ['id' => $fileId]);
    }

    /**
     * @return array{Organization, User, Project}
     */
    private function tenantProjectContext(string $slug): array
    {
        $org = Organization::factory()->create(['slug' => $slug]);
        $user = User::factory()->create([
            'active_organization_id' => $org->id,
        ]);
        $org->users()->attach($user->id);

        $role = Role::create([
            'name' => "project-file-admin-{$slug}",
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);
        $role->givePermissionTo(['projects.view', 'projects.edit']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        $client = Client::factory()->create([
            'organization_id' => $org->id,
        ]);
        $project = Project::factory()->create([
            'organization_id' => $org->id,
            'client_id' => $client->id,
            'project_manager_id' => $user->id,
        ]);

        return [$org, $user, $project];
    }
}
