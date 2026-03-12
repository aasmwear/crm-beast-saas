<?php

namespace Tests\Feature\Forms;

use App\Models\Client;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Form alignment: CSV import, QuickCreate, Project create, status validation.
 */
final class FormAlignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function tenantWithClientsImport(Organization $org, User $user): void
    {
        $org->users()->attach($user->id);
        $user->forceFill(['active_organization_id' => $org->id])->save();
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole(Role::where('name', 'Owner')->whereNull('team_id')->first());
    }

    public function test_csv_import_works_with_file_field(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create();
        $this->tenantWithClientsImport($org, $user);

        $csv = "company_name,industry,status,primary_contact_email\nAcme Corp,SaaS,lead,jane@acme.com\n";
        $file = UploadedFile::fake()->createWithContent('clients.csv', $csv);

        $response = $this->actingAs($user)
            ->post(route('clients.import.store', ['organization' => $org->slug]), [
                'file' => $file,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('clients', [
            'organization_id' => $org->id,
            'company_name' => 'Acme Corp',
        ]);
    }

    public function test_quick_create_submits_successfully(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create();
        $this->tenantWithClientsImport($org, $user);

        $response = $this->actingAs($user)
            ->post(route('clients.store', ['organization' => $org->slug]), [
                'company_name' => 'Quick Co',
                'primary_contact_name' => 'Jane Doe',
                'primary_contact_email' => 'jane@quick.co',
                'niche' => 'Marketing',
                'status' => 'active',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('clients', [
            'organization_id' => $org->id,
            'company_name' => 'Quick Co',
            'primary_contact_name' => 'Jane Doe',
            'primary_contact_email' => 'jane@quick.co',
            'status' => 'active',
        ]);
    }

    public function test_project_create_works(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create(['active_organization_id' => $org->id]);
        $org->users()->attach($user->id);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole(Role::where('name', 'Owner')->whereNull('team_id')->first());

        $client = Client::factory()->create(['organization_id' => $org->id, 'company_name' => 'Test Client']);

        $response = $this->actingAs($user)
            ->post(route('projects.store', ['organization' => $org->slug]), [
                'title' => 'New Project',
                'description' => 'Project description',
                'client_id' => $client->id,
                'status' => 'Planned',
                'due_date' => '2026-12-31',
                'user_ids' => [$user->id],
            ]);

        $response->assertRedirect();
        $project = Project::query()->where('title', 'New Project')->where('organization_id', $org->id)->first();
        $this->assertNotNull($project);
        $this->assertSame('Planned', $project->status);
        $this->assertSame('Project description', $project->description);
    }

    public function test_invalid_project_status_rejected(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create(['active_organization_id' => $org->id]);
        $org->users()->attach($user->id);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole(Role::where('name', 'Owner')->whereNull('team_id')->first());

        $response = $this->actingAs($user)
            ->post(route('projects.store', ['organization' => $org->slug]), [
                'title' => 'Bad Status Project',
                'status' => 'InvalidStatus',
            ]);

        $response->assertSessionHasErrors('status');
    }

    public function test_invalid_client_pipeline_status_rejected(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create(['active_organization_id' => $org->id]);
        $org->users()->attach($user->id);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole(Role::where('name', 'Owner')->whereNull('team_id')->first());

        $client = Client::factory()->create([
            'organization_id' => $org->id,
            'company_name' => 'Pipeline Test',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)
            ->post(route('clients.pipeline.update', [
                'organization' => $org->slug,
                'client' => $client->id,
            ]), ['status' => 'invalid_status']);

        $response->assertSessionHasErrors('status');
    }

}
