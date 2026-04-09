<?php

declare(strict_types=1);

namespace Tests\Feature\CustomFields;

use App\Models\Client;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class CustomFieldTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function assignRoleInOrg(User $user, Organization $org, string $roleName): void
    {
        $role = Role::where('name', $roleName)->whereNull('team_id')->first();
        if ($role === null) {
            $role = Role::create(['name' => $roleName, 'guard_name' => 'web', 'team_id' => null]);
        }
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($role);
    }

    public function test_field_creation_requires_permission(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($org->id, ['is_owner' => false]);
        $user->forceFill(['active_organization_id' => $org->id])->save();
        $this->assignRoleInOrg($user, $org, 'Employee'); // no custom-fields.manage

        $response = $this->actingAs($user)->post(
            route('custom-fields.store', ['organization' => $org->slug]),
            [
                'label' => 'Contract Type',
                'type' => 'text',
            ]
        );

        $response->assertStatus(403);
        $this->assertDatabaseCount('custom_fields', 0);
    }

    public function test_field_creation_succeeds_with_permission(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($org->id, ['is_owner' => true]);
        $user->forceFill(['active_organization_id' => $org->id])->save();
        $this->assignRoleInOrg($user, $org, 'Owner');

        $response = $this->actingAs($user)->post(
            route('custom-fields.store', ['organization' => $org->slug]),
            [
                'label' => 'Contract Type',
                'type' => 'text',
                'is_required' => false,
            ]
        );

        $response->assertRedirect(route('custom-fields.index', ['organization' => $org->slug]));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('custom_fields', [
            'organization_id' => $org->id,
            'entity' => 'client',
            'label' => 'Contract Type',
            'slug' => 'contract_type',
            'type' => 'text',
        ]);
    }

    public function test_value_saving_on_client_create(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($org->id, ['is_owner' => true]);
        $user->forceFill(['active_organization_id' => $org->id])->save();
        $this->assignRoleInOrg($user, $org, 'Owner');

        $field = CustomField::query()->create([
            'organization_id' => $org->id,
            'entity' => 'client',
            'label' => 'Contract Type',
            'slug' => 'contract_type',
            'type' => 'text',
            'is_required' => false,
        ]);

        $response = $this->actingAs($user)->post(
            route('clients.store', ['organization' => $org->slug]),
            [
                'company_name' => 'Acme Corp',
                'primary_contact_name' => 'John',
                'primary_contact_email' => 'john@acme.com',
                'custom_values' => [
                    'contract_type' => 'Annual',
                ],
            ]
        );

        $response->assertRedirect();
        $client = Client::query()->where('organization_id', $org->id)->where('company_name', 'Acme Corp')->first();
        $this->assertNotNull($client);

        $value = CustomFieldValue::query()
            ->where('custom_field_id', $field->id)
            ->where('entity_type', 'client')
            ->where('entity_id', $client->id)
            ->first();
        $this->assertNotNull($value);
        $this->assertSame((int) $org->id, (int) $value->organization_id);
        $this->assertSame('Annual', $value->value_text);
    }

    public function test_org_isolation_custom_fields(): void
    {
        $orgA = Organization::factory()->create(['slug' => 'org-a']);
        $orgB = Organization::factory()->create(['slug' => 'org-b']);

        $fieldA = CustomField::query()->create([
            'organization_id' => $orgA->id,
            'entity' => 'client',
            'label' => 'Field A',
            'slug' => 'field_a',
            'type' => 'text',
        ]);

        $userB = User::factory()->create();
        $userB->organizations()->attach($orgB->id, ['is_owner' => true]);
        $userB->forceFill(['active_organization_id' => $orgB->id])->save();
        $this->assignRoleInOrg($userB, $orgB, 'Owner');

        // User from org B cannot update/delete field from org A (404 via scopeBindings)
        $response = $this->actingAs($userB)->put(
            route('custom-fields.update', ['organization' => 'org-b', 'customField' => $fieldA->id]),
            ['label' => 'Hacked', 'type' => 'text']
        );

        $response->assertStatus(404);
    }

    public function test_custom_fields_index_requires_permission(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($org->id);
        $user->forceFill(['active_organization_id' => $org->id])->save();
        $this->assignRoleInOrg($user, $org, 'Employee');

        $response = $this->actingAs($user)->get(
            route('custom-fields.index', ['organization' => $org->slug])
        );

        $response->assertStatus(403);
    }

    public function test_invalid_select_value_rejected(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($org->id, ['is_owner' => true]);
        $user->forceFill(['active_organization_id' => $org->id])->save();
        $this->assignRoleInOrg($user, $org, 'Owner');

        CustomField::query()->create([
            'organization_id' => $org->id,
            'entity' => 'client',
            'label' => 'Tier',
            'slug' => 'tier',
            'type' => 'select',
            'options' => ['Basic', 'Premium', 'Enterprise'],
            'is_required' => false,
        ]);

        $response = $this->actingAs($user)->post(
            route('clients.store', ['organization' => $org->slug]),
            [
                'company_name' => 'Acme',
                'primary_contact_name' => 'John',
                'primary_contact_email' => 'john@acme.com',
                'custom_values' => [
                    'tier' => 'InvalidOption',
                ],
            ]
        );

        $response->assertSessionHasErrors('custom_values.tier');
        $this->assertDatabaseMissing('custom_field_values', ['value_text' => 'InvalidOption']);
    }

    public function test_invalid_multiselect_value_rejected(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($org->id, ['is_owner' => true]);
        $user->forceFill(['active_organization_id' => $org->id])->save();
        $this->assignRoleInOrg($user, $org, 'Owner');

        CustomField::query()->create([
            'organization_id' => $org->id,
            'entity' => 'client',
            'label' => 'Tags',
            'slug' => 'tags',
            'type' => 'multiselect',
            'options' => ['VIP', 'Standard'],
            'is_required' => false,
        ]);

        $response = $this->actingAs($user)->post(
            route('clients.store', ['organization' => $org->slug]),
            [
                'company_name' => 'Acme',
                'primary_contact_name' => 'John',
                'primary_contact_email' => 'john@acme.com',
                'custom_values' => [
                    'tags' => ['VIP', 'HackerInjected'],
                ],
            ]
        );

        $response->assertSessionHasErrors('custom_values.tags');
        $this->assertDatabaseCount('custom_field_values', 0);
    }

    public function test_client_filtering_by_custom_field_works(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($org->id, ['is_owner' => true]);
        $user->forceFill(['active_organization_id' => $org->id])->save();
        $this->assignRoleInOrg($user, $org, 'Owner');

        $field = CustomField::query()->create([
            'organization_id' => $org->id,
            'entity' => 'client',
            'label' => 'Tier',
            'slug' => 'tier',
            'type' => 'select',
            'options' => ['Basic', 'Premium'],
            'is_required' => false,
        ]);

        $clientPremium = Client::factory()->create(['organization_id' => $org->id, 'company_name' => 'Premium Corp']);
        CustomFieldValue::query()->create([
            'organization_id' => $org->id,
            'custom_field_id' => $field->id,
            'entity_type' => 'client',
            'entity_id' => $clientPremium->id,
            'value_text' => 'Premium',
        ]);

        $clientBasic = Client::factory()->create(['organization_id' => $org->id, 'company_name' => 'Basic Corp']);
        CustomFieldValue::query()->create([
            'organization_id' => $org->id,
            'custom_field_id' => $field->id,
            'entity_type' => 'client',
            'entity_id' => $clientBasic->id,
            'value_text' => 'Basic',
        ]);

        $response = $this->actingAs($user)->get(
            route('clients.index', ['organization' => $org->slug]) . '?cf[tier]=Premium',
            ['X-Inertia' => 'true']
        );

        $response->assertOk();
        $data = $response->json('props.clients.data') ?? [];
        $this->assertCount(1, $data);
        $this->assertSame('Premium Corp', $data[0]['company_name']);
    }

    public function test_org_isolation_preserved_for_cf_filter(): void
    {
        $orgA = Organization::factory()->create(['slug' => 'org-a']);
        $orgB = Organization::factory()->create(['slug' => 'org-b']);

        $fieldA = CustomField::query()->create([
            'organization_id' => $orgA->id,
            'entity' => 'client',
            'label' => 'Tier',
            'slug' => 'tier',
            'type' => 'select',
            'options' => ['A', 'B'],
            'is_required' => false,
        ]);

        $clientA = Client::factory()->create(['organization_id' => $orgA->id, 'company_name' => 'Client In A']);
        CustomFieldValue::query()->create([
            'organization_id' => $orgA->id,
            'custom_field_id' => $fieldA->id,
            'entity_type' => 'client',
            'entity_id' => $clientA->id,
            'value_text' => 'A',
        ]);

        $userB = User::factory()->create();
        $userB->organizations()->attach($orgB->id, ['is_owner' => true]);
        $userB->forceFill(['active_organization_id' => $orgB->id])->save();
        $this->assignRoleInOrg($userB, $orgB, 'Owner');

        // Org B has no custom field "tier" - cf param is ignored; org B has no clients
        $response = $this->actingAs($userB)->get(
            route('clients.index', ['organization' => $orgB->slug]) . '?cf[tier]=A',
            ['X-Inertia' => 'true']
        );

        $response->assertOk();
        $data = $response->json('props.clients.data') ?? [];
        $this->assertCount(0, $data);
    }

    public function test_client_custom_field_values_relation_scoped_to_client_organization(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $field = CustomField::query()->create([
            'organization_id' => $orgA->id,
            'entity' => 'client',
            'label' => 'Note',
            'slug' => 'note_' . uniqid(),
            'type' => 'text',
            'is_required' => false,
        ]);

        $client = Client::factory()->create(['organization_id' => $orgA->id]);

        $value = CustomFieldValue::query()->create([
            'organization_id' => $orgA->id,
            'custom_field_id' => $field->id,
            'entity_type' => 'client',
            'entity_id' => $client->id,
            'value_text' => 'Legit',
        ]);

        $this->assertCount(1, $client->customFieldValues()->get());

        DB::table('custom_field_values')->where('id', $value->id)->update(['organization_id' => $orgB->id]);

        $client->unsetRelation('customFieldValues');

        $this->assertCount(0, $client->customFieldValues()->get());
        $this->assertSame(
            0,
            CustomFieldValue::query()
                ->where('organization_id', $orgA->id)
                ->where('entity_id', $client->id)
                ->count()
        );
    }
}
