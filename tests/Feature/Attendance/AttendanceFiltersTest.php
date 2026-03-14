<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AttendanceFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        config()->set('features.attendance', true);
    }

    private function createUserWithRole(Organization $org, string $roleName, array $permissions): User
    {
        $user = User::factory()->create([
            'active_organization_id' => $org->id,
        ]);
        $org->users()->attach($user->id);

        $role = Role::create([
            'name' => $roleName,
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);
        $role->syncPermissions($permissions);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);

        return $user;
    }

    public function test_date_range_filter_works(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = $this->createUserWithRole($org, 'viewer', ['attendance.view']);

        // Create records across different dates
        Attendance::query()->create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'clock_in_at' => now()->subDays(10),
            'clock_out_at' => now()->subDays(10)->addHour(),
            'minutes' => 60,
            'status' => 'closed',
        ]);
        Attendance::query()->create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'clock_in_at' => now()->subDays(5),
            'clock_out_at' => now()->subDays(5)->addHour(),
            'minutes' => 60,
            'status' => 'closed',
        ]);
        Attendance::query()->create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'clock_in_at' => now()->subDays(2),
            'clock_out_at' => now()->subDays(2)->addHour(),
            'minutes' => 60,
            'status' => 'closed',
        ]);

        $from = now()->subDays(6)->toDateString();
        $to = now()->subDays(4)->toDateString();

        $response = $this->actingAs($user)
            ->get(route('attendance.index', [
                'organization' => $org->slug,
                'date_from' => $from,
                'date_to' => $to,
            ]));

        $response->assertOk();
        $data = $response->viewData('page')['props']['attendance']['data'] ?? [];
        $this->assertCount(1, $data, 'Should return only the record within the date range');
        $this->assertSame($from, $response->viewData('page')['props']['filters']['date_from']);
        $this->assertSame($to, $response->viewData('page')['props']['filters']['date_to']);
    }

    public function test_status_filter_works(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = $this->createUserWithRole($org, 'viewer', ['attendance.view']);

        Attendance::query()->create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'clock_in_at' => now()->subHour(),
            'clock_out_at' => null,
            'status' => 'open',
        ]);
        Attendance::query()->create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'clock_in_at' => now()->subHours(3),
            'clock_out_at' => now()->subHours(2),
            'minutes' => 60,
            'status' => 'closed',
        ]);

        $response = $this->actingAs($user)
            ->get(route('attendance.index', [
                'organization' => $org->slug,
                'status' => 'closed',
            ]));

        $response->assertOk();
        $data = $response->viewData('page')['props']['attendance']['data'] ?? [];
        $this->assertCount(1, $data);
        $this->assertSame('closed', $data[0]['status']);
    }

    public function test_approved_filter_works(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = $this->createUserWithRole($org, 'viewer', ['attendance.view']);

        $approved = Attendance::query()->create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'clock_in_at' => now()->subDays(2),
            'clock_out_at' => now()->subDays(2)->addHour(),
            'minutes' => 60,
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);
        $notApproved = Attendance::query()->create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'clock_in_at' => now()->subDays(1),
            'clock_out_at' => now()->subDays(1)->addHour(),
            'minutes' => 60,
            'status' => 'closed',
        ]);

        $response = $this->actingAs($user)
            ->get(route('attendance.index', [
                'organization' => $org->slug,
                'approved' => 'yes',
            ]));

        $response->assertOk();
        $data = $response->viewData('page')['props']['attendance']['data'] ?? [];
        $this->assertCount(1, $data);
        $this->assertSame($approved->id, $data[0]['id']);

        $responseNo = $this->actingAs($user)
            ->get(route('attendance.index', [
                'organization' => $org->slug,
                'approved' => 'no',
            ]));

        $responseNo->assertOk();
        $dataNo = $responseNo->viewData('page')['props']['attendance']['data'] ?? [];
        $this->assertCount(1, $dataNo);
        $this->assertSame($notApproved->id, $dataNo[0]['id']);
    }

    public function test_user_filter_only_works_for_can_view_all_users(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $hrUser = $this->createUserWithRole($org, 'hr', ['attendance.view']);
        $employee = User::factory()->create(['active_organization_id' => $org->id]);
        $org->users()->attach($employee->id);

        Attendance::query()->create([
            'organization_id' => $org->id,
            'user_id' => $employee->id,
            'clock_in_at' => now()->subHour(),
            'clock_out_at' => now(),
            'minutes' => 60,
            'status' => 'closed',
        ]);

        $response = $this->actingAs($hrUser)
            ->get(route('attendance.index', [
                'organization' => $org->slug,
                'user_id' => $employee->id,
            ]));

        $response->assertOk();
        $data = $response->viewData('page')['props']['attendance']['data'] ?? [];
        $this->assertCount(1, $data);
        $this->assertSame($employee->id, $data[0]['user_id']);
    }

    public function test_view_own_user_cannot_force_another_user_via_query_string(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $viewOwnUser = $this->createUserWithRole($org, 'employee', ['attendance.view-own']);
        $otherUser = User::factory()->create(['active_organization_id' => $org->id]);
        $org->users()->attach($otherUser->id);

        // Record for other user
        Attendance::query()->create([
            'organization_id' => $org->id,
            'user_id' => $otherUser->id,
            'clock_in_at' => now()->subHour(),
            'clock_out_at' => now(),
            'minutes' => 60,
            'status' => 'closed',
        ]);
        // Record for view-own user
        Attendance::query()->create([
            'organization_id' => $org->id,
            'user_id' => $viewOwnUser->id,
            'clock_in_at' => now()->subHours(3),
            'clock_out_at' => now()->subHours(2),
            'minutes' => 60,
            'status' => 'closed',
        ]);

        $response = $this->actingAs($viewOwnUser)
            ->get(route('attendance.index', [
                'organization' => $org->slug,
                'user_id' => $otherUser->id,
            ]));

        $response->assertStatus(302);
        $response->assertSessionHasErrors('user_id');
    }

    public function test_query_string_persists_across_pagination(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = $this->createUserWithRole($org, 'viewer', ['attendance.view']);

        for ($i = 0; $i < 35; $i++) {
            Attendance::query()->create([
                'organization_id' => $org->id,
                'user_id' => $user->id,
                'clock_in_at' => now()->subDays($i)->subHour(),
                'clock_out_at' => now()->subDays($i),
                'minutes' => 60,
                'status' => 'closed',
            ]);
        }

        $response = $this->actingAs($user)
            ->get(route('attendance.index', [
                'organization' => $org->slug,
                'status' => 'closed',
                'page' => 2,
            ]));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Attendance/Index')
            ->has('attendance.data')
            ->has('attendance.links')
            ->where('filters.status', 'closed'));

        $pageData = $response->viewData('page') ?? [];
        $links = $pageData['props']['attendance']['links'] ?? [];
        $hasStatusInLinks = collect($links)->contains(fn ($l) => ! empty($l['url']) && str_contains((string) $l['url'], 'status=closed'));
        $this->assertTrue($hasStatusInLinks, 'Pagination links should preserve status=closed in query string');
    }

    public function test_invalid_status_returns_validation_error(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = $this->createUserWithRole($org, 'viewer', ['attendance.view']);

        $response = $this->actingAs($user)
            ->get(route('attendance.index', [
                'organization' => $org->slug,
                'status' => 'invalid-status',
            ]));

        $response->assertStatus(302);
        $response->assertSessionHasErrors('status');
    }

    public function test_invalid_approved_value_returns_validation_error(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme']);
        $user = $this->createUserWithRole($org, 'viewer', ['attendance.view']);

        $response = $this->actingAs($user)
            ->get(route('attendance.index', [
                'organization' => $org->slug,
                'approved' => 'maybe',
            ]));

        $response->assertStatus(302);
        $response->assertSessionHasErrors('approved');
    }
}
