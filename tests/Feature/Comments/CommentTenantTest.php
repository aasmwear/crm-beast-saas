<?php

declare(strict_types=1);

namespace Tests\Feature\Comments;

use App\Models\Client;
use App\Models\Comment;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class CommentTenantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function assignProjectView(User $user, Organization $org): void
    {
        $role = Role::create([
            'name' => 'comment-tester-' . uniqid(),
            'guard_name' => 'web',
            'team_id' => $org->id,
        ]);
        $role->givePermissionTo('projects.view');
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);
    }

    public function test_storing_project_comment_sets_organization_id(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme-comments']);
        $user = User::factory()->create(['active_organization_id' => $org->id]);
        $org->users()->attach($user->id);
        $this->assignProjectView($user, $org);

        $client = Client::factory()->create(['organization_id' => $org->id]);
        $project = Project::factory()->create([
            'organization_id' => $org->id,
            'client_id' => $client->id,
            'project_manager_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->post(
            route('projects.comments.store', ['organization' => $org->slug, 'project' => $project->id]),
            ['body' => 'Hello tenant']
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('comments', [
            'organization_id' => $org->id,
            'commentable_id' => $project->id,
            'body' => 'Hello tenant',
        ]);
    }

    public function test_destroy_comment_returns_404_for_wrong_organization_slug(): void
    {
        $orgA = Organization::factory()->create(['slug' => 'org-a-comments']);
        $orgB = Organization::factory()->create(['slug' => 'org-b-comments']);

        $userA = User::factory()->create(['active_organization_id' => $orgA->id]);
        $orgA->users()->attach($userA->id);
        $this->assignProjectView($userA, $orgA);

        $clientA = Client::factory()->create(['organization_id' => $orgA->id]);
        $projectA = Project::factory()->create([
            'organization_id' => $orgA->id,
            'client_id' => $clientA->id,
            'project_manager_id' => $userA->id,
        ]);

        $comment = Comment::query()->create([
            'organization_id' => $orgA->id,
            'user_id' => $userA->id,
            'body' => 'Secret',
            'commentable_type' => $projectA->getMorphClass(),
            'commentable_id' => $projectA->id,
        ]);

        $userB = User::factory()->create(['active_organization_id' => $orgB->id]);
        $orgB->users()->attach($userB->id);
        $this->assignProjectView($userB, $orgB);

        $response = $this->actingAs($userB)->delete(
            route('comments.destroy', ['organization' => $orgB->slug, 'comment' => $comment->id])
        );

        $response->assertNotFound();
        $this->assertNotNull(Comment::query()->find($comment->id));
    }

    public function test_project_show_comments_prop_excludes_rows_not_in_route_organization(): void
    {
        $org = Organization::factory()->create(['slug' => 'acme-show-comments']);
        $user = User::factory()->create(['active_organization_id' => $org->id]);
        $org->users()->attach($user->id);
        $this->assignProjectView($user, $org);

        $client = Client::factory()->create(['organization_id' => $org->id]);
        $project = Project::factory()->create([
            'organization_id' => $org->id,
            'client_id' => $client->id,
            'project_manager_id' => $user->id,
        ]);

        $orgOther = Organization::factory()->create();

        $visible = Comment::query()->create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'body' => 'Visible',
            'commentable_type' => $project->getMorphClass(),
            'commentable_id' => $project->id,
        ]);

        $hidden = Comment::query()->create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'body' => 'Drifted org column',
            'commentable_type' => $project->getMorphClass(),
            'commentable_id' => $project->id,
        ]);

        DB::table('comments')->where('id', $hidden->id)->update(['organization_id' => $orgOther->id]);

        $response = $this->actingAs($user)->get(
            route('projects.show', ['organization' => $org->slug, 'project' => $project->id])
        );

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Show')
            ->has('comments', 1)
            ->where('comments.0.id', $visible->id)
            ->where('comments.0.body', 'Visible'));
    }

    public function test_project_comments_relation_excludes_mismatched_organization_id(): void
    {
        $org = Organization::factory()->create();
        $orgOther = Organization::factory()->create();
        $client = Client::factory()->create(['organization_id' => $org->id]);
        $project = Project::factory()->create([
            'organization_id' => $org->id,
            'client_id' => $client->id,
        ]);

        $keep = Comment::query()->create([
            'organization_id' => $org->id,
            'user_id' => User::factory()->create()->id,
            'body' => 'OK',
            'commentable_type' => $project->getMorphClass(),
            'commentable_id' => $project->id,
        ]);

        Comment::query()->create([
            'organization_id' => $orgOther->id,
            'user_id' => User::factory()->create()->id,
            'body' => 'Wrong org',
            'commentable_type' => $project->getMorphClass(),
            'commentable_id' => $project->id,
        ]);

        $project->unsetRelation('comments');
        $bodies = $project->comments()->pluck('body')->all();

        $this->assertSame(['OK'], $bodies);
        $this->assertSame($keep->id, $project->comments()->first()->id);
    }
}
