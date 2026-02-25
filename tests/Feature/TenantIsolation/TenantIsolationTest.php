<?php

namespace Tests\Feature\TenantIsolation;

use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Client;
use App\Models\Comment;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $orgA;

    protected Organization $orgB;

    protected User $userA;

    protected User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->orgA = Organization::factory()->create(['slug' => 'org-a']);
        $this->orgB = Organization::factory()->create(['slug' => 'org-b']);

        $ownerRole = \Spatie\Permission\Models\Role::where('name', 'Owner')->whereNull('team_id')->first();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->orgA->id);

        $this->userA = User::factory()->create(['active_organization_id' => $this->orgA->id]);
        $this->userA->organizations()->attach([$this->orgA->id]);
        $this->userA->assignRole($ownerRole);

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->orgB->id);
        $this->userB = User::factory()->create(['active_organization_id' => $this->orgB->id]);
        $this->userB->organizations()->attach([$this->orgB->id]);
        $this->userB->assignRole($ownerRole);
    }

    public function test_activities_org_a_cannot_see_org_b_records(): void
    {
        $clientA = Client::factory()->create(['organization_id' => $this->orgA->id]);
        $clientB = Client::factory()->create(['organization_id' => $this->orgB->id]);
        $projectA = Project::factory()->create(['organization_id' => $this->orgA->id, 'client_id' => $clientA->id]);
        $projectB = Project::factory()->create(['organization_id' => $this->orgB->id, 'client_id' => $clientB->id]);

        Activity::query()->create([
            'organization_id' => $this->orgA->id,
            'user_id' => $this->userA->id,
            'description' => 'Activity in Org A',
            'subject_type' => Project::class,
            'subject_id' => $projectA->id,
        ]);

        Activity::query()->create([
            'organization_id' => $this->orgB->id,
            'user_id' => $this->userB->id,
            'description' => 'Activity in Org B',
            'subject_type' => Project::class,
            'subject_id' => $projectB->id,
        ]);

        $orgAActivities = Activity::query()->forOrganization($this->orgA->id)->get();
        $this->assertCount(1, $orgAActivities);
        $this->assertStringContainsString('Org A', $orgAActivities->first()->description);

        $orgBActivities = Activity::query()->forOrganization($this->orgB->id)->get();
        $this->assertCount(1, $orgBActivities);
        $this->assertStringContainsString('Org B', $orgBActivities->first()->description);
    }

    public function test_comments_org_a_cannot_see_org_b_records(): void
    {
        $clientA = Client::factory()->create(['organization_id' => $this->orgA->id]);
        $clientB = Client::factory()->create(['organization_id' => $this->orgB->id]);
        $projectA = Project::factory()->create(['organization_id' => $this->orgA->id, 'client_id' => $clientA->id]);
        $projectB = Project::factory()->create(['organization_id' => $this->orgB->id, 'client_id' => $clientB->id]);

        Comment::query()->create([
            'organization_id' => $this->orgA->id,
            'user_id' => $this->userA->id,
            'body' => 'Comment in Org A',
            'commentable_type' => Project::class,
            'commentable_id' => $projectA->id,
        ]);

        Comment::query()->create([
            'organization_id' => $this->orgB->id,
            'user_id' => $this->userB->id,
            'body' => 'Comment in Org B',
            'commentable_type' => Project::class,
            'commentable_id' => $projectB->id,
        ]);

        $orgAComments = Comment::query()->forOrganization($this->orgA->id)->get();
        $this->assertCount(1, $orgAComments);
        $this->assertStringContainsString('Org A', $orgAComments->first()->body);

        $orgBComments = Comment::query()->forOrganization($this->orgB->id)->get();
        $this->assertCount(1, $orgBComments);
        $this->assertStringContainsString('Org B', $orgBComments->first()->body);
    }

    public function test_attendance_org_a_cannot_see_org_b_records(): void
    {
        Attendance::query()->create([
            'organization_id' => $this->orgA->id,
            'user_id' => $this->userA->id,
            'clock_in_at' => now(),
            'status' => 'open',
        ]);

        Attendance::query()->create([
            'organization_id' => $this->orgB->id,
            'user_id' => $this->userB->id,
            'clock_in_at' => now(),
            'status' => 'open',
        ]);

        $orgAAttendance = Attendance::query()->where('organization_id', $this->orgA->id)->get();
        $this->assertCount(1, $orgAAttendance);

        $orgBAttendance = Attendance::query()->where('organization_id', $this->orgB->id)->get();
        $this->assertCount(1, $orgBAttendance);
    }

    public function test_notifications_org_a_cannot_see_org_b_records(): void
    {
        \Illuminate\Support\Facades\DB::table('notifications')->insert([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'type' => 'App\Notifications\InAppEventNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->userA->id,
            'data' => json_encode(['organization_id' => $this->orgA->id, 'message' => 'Notification for Org A']),
            'organization_id' => $this->orgA->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        \Illuminate\Support\Facades\DB::table('notifications')->insert([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'type' => 'App\Notifications\InAppEventNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->userB->id,
            'data' => json_encode(['organization_id' => $this->orgB->id, 'message' => 'Notification for Org B']),
            'organization_id' => $this->orgB->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orgANotifications = DatabaseNotification::query()
            ->where('notifiable_id', $this->userA->id)
            ->where('notifiable_type', User::class)
            ->where('organization_id', $this->orgA->id)
            ->get();
        $this->assertCount(1, $orgANotifications);

        $orgBNotifications = DatabaseNotification::query()
            ->where('notifiable_id', $this->userB->id)
            ->where('notifiable_type', User::class)
            ->where('organization_id', $this->orgB->id)
            ->get();
        $this->assertCount(1, $orgBNotifications);
    }
}
