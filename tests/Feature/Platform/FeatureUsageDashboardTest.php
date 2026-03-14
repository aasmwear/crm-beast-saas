<?php

namespace Tests\Feature\Platform;

use App\Models\Attendance;
use App\Models\Client;
use App\Models\Organization;
use App\Models\OrganizationAddon;
use App\Models\OrganizationSubscription;
use App\Models\Platform\PlatformAdmin;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FeatureUsageDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected PlatformAdmin $platformAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->platformAdmin = PlatformAdmin::factory()->superAdmin()->create([
            'email' => 'admin@platform.test',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
    }

    public function test_platform_admin_can_access_feature_usage_dashboard(): void
    {
        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.feature-usage'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Platform/FeatureUsage/Index')
            ->has('total_orgs')
            ->has('adoption')
            ->has('adoption.clients')
            ->has('adoption.projects')
            ->has('adoption.tasks')
            ->has('adoption.attendance')
            ->has('adoption.invoices')
            ->has('billing_setup')
            ->has('billing_setup.stripe_linked')
            ->has('billing_setup.has_subscription')
            ->has('billing_setup.has_active_subscription')
            ->has('billing_setup.has_active_addons')
            ->has('summary')
            ->has('summary.total_orgs')
            ->has('summary.orgs_using_any_module')
            ->has('summary.avg_modules_per_org')
            ->has('summary.modules_tracked')
        );
    }

    public function test_tenant_user_cannot_access_feature_usage_dashboard(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = User::factory()->create(['active_organization_id' => $org->id]);

        $response = $this->actingAs($user)
            ->get(route('platform.feature-usage'));

        $response->assertRedirect();
        $this->assertStringContainsString('login', $response->headers->get('Location', ''));
    }

    public function test_guest_cannot_access_feature_usage_dashboard(): void
    {
        $response = $this->get(route('platform.feature-usage'));

        $response->assertRedirect();
        $this->assertStringContainsString('login', $response->headers->get('Location', ''));
    }

    public function test_client_adoption_counts_orgs_with_clients(): void
    {
        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();
        Organization::factory()->create();

        Client::factory()->create(['organization_id' => $org1->id]);
        Client::factory()->create(['organization_id' => $org1->id]);
        Client::factory()->create(['organization_id' => $org2->id]);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.feature-usage'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('adoption.clients.orgs_with_any', 2)
            ->where('adoption.clients.total_records', 3)
            ->where('total_orgs', 3)
        );
    }

    public function test_project_adoption_counts(): void
    {
        $org = Organization::factory()->create();
        $client = Client::factory()->create(['organization_id' => $org->id]);

        Project::factory()->create(['organization_id' => $org->id, 'client_id' => $client->id]);
        Project::factory()->create(['organization_id' => $org->id, 'client_id' => $client->id]);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.feature-usage'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('adoption.projects.orgs_with_any', 1)
            ->where('adoption.projects.total_records', 2)
        );
    }

    public function test_task_adoption_counts(): void
    {
        $org = Organization::factory()->create();
        $client = Client::factory()->create(['organization_id' => $org->id]);
        $project = Project::factory()->create(['organization_id' => $org->id, 'client_id' => $client->id]);

        Task::factory()->create(['organization_id' => $org->id, 'project_id' => $project->id]);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.feature-usage'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('adoption.tasks.orgs_with_any', 1)
            ->where('adoption.tasks.total_records', 1)
        );
    }

    public function test_attendance_adoption_counts(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create(['active_organization_id' => $org->id]);
        $org->users()->attach($user->id, ['is_owner' => true]);

        Attendance::factory()->create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.feature-usage'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('adoption.attendance.orgs_with_any', 1)
            ->where('adoption.attendance.total_records', 1)
        );
    }

    public function test_soft_deleted_records_excluded(): void
    {
        $org = Organization::factory()->create();
        $client = Client::factory()->create([
            'organization_id' => $org->id,
            'deleted_at' => now(),
        ]);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.feature-usage'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('adoption.clients.orgs_with_any', 0)
            ->where('adoption.clients.total_records', 0)
        );
    }

    public function test_billing_setup_metrics(): void
    {
        $orgStripe = Organization::factory()->create(['stripe_id' => 'cus_abc123']);
        OrganizationSubscription::create([
            'organization_id' => $orgStripe->id,
            'plan_key' => 'pro',
            'status' => 'active',
            'seats_included' => 25,
        ]);

        $orgTrialing = Organization::factory()->create();
        OrganizationSubscription::create([
            'organization_id' => $orgTrialing->id,
            'plan_key' => 'starter',
            'status' => 'trialing',
            'seats_included' => 5,
        ]);

        $orgCanceled = Organization::factory()->create();
        OrganizationSubscription::create([
            'organization_id' => $orgCanceled->id,
            'plan_key' => 'pro',
            'status' => 'canceled',
            'seats_included' => 25,
        ]);

        Organization::factory()->create();

        OrganizationAddon::create([
            'organization_id' => $orgStripe->id,
            'addon_key' => 'storage_gb',
            'mode' => 'augment',
            'quantity' => 1,
            'value_int' => 10,
            'active' => true,
        ]);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.feature-usage'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('billing_setup.stripe_linked', 1)
            ->where('billing_setup.has_subscription', 3)
            ->where('billing_setup.has_active_subscription', 2)
            ->where('billing_setup.has_active_addons', 1)
        );
    }

    public function test_summary_orgs_using_any_module(): void
    {
        $org1 = Organization::factory()->create();
        $org2 = Organization::factory()->create();
        Organization::factory()->create();

        Client::factory()->create(['organization_id' => $org1->id]);

        $client2 = Client::factory()->create(['organization_id' => $org2->id]);
        Project::factory()->create(['organization_id' => $org2->id, 'client_id' => $client2->id]);

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.feature-usage'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('summary.orgs_using_any_module', 2)
            ->where('summary.total_orgs', 3)
        );
    }

    public function test_zero_state_when_no_orgs(): void
    {
        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.feature-usage'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('total_orgs', 0)
            ->where('summary.total_orgs', 0)
            ->where('summary.orgs_using_any_module', 0)
            ->where('summary.avg_modules_per_org', 0)
            ->where('adoption.clients.orgs_with_any', 0)
            ->where('adoption.clients.total_records', 0)
            ->where('adoption.projects.orgs_with_any', 0)
            ->where('adoption.tasks.orgs_with_any', 0)
            ->where('adoption.attendance.orgs_with_any', 0)
            ->where('adoption.invoices.orgs_with_any', 0)
            ->where('billing_setup.stripe_linked', 0)
            ->where('billing_setup.has_subscription', 0)
            ->where('billing_setup.has_active_subscription', 0)
            ->where('billing_setup.has_active_addons', 0)
        );
    }

    public function test_zero_state_when_orgs_exist_but_no_usage(): void
    {
        Organization::factory()->count(3)->create();

        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.feature-usage'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('total_orgs', 3)
            ->where('summary.orgs_using_any_module', 0)
            ->where('summary.avg_modules_per_org', 0)
            ->where('adoption.clients.orgs_with_any', 0)
            ->where('adoption.projects.orgs_with_any', 0)
        );
    }

    public function test_modules_tracked_count(): void
    {
        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.feature-usage'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('summary.modules_tracked', 5)
        );
    }

    public function test_adoption_includes_labels(): void
    {
        $response = $this->actingAs($this->platformAdmin, 'platform')
            ->get(route('platform.feature-usage'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('adoption.clients.label', 'Clients')
            ->where('adoption.projects.label', 'Projects')
            ->where('adoption.tasks.label', 'Tasks')
            ->where('adoption.attendance.label', 'Attendance')
            ->where('adoption.invoices.label', 'Invoices')
        );
    }
}
