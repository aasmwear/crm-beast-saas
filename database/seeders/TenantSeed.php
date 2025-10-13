<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

use App\Models\User;
use App\Models\Organization;

use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class TenantSeed extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // ---------- Organizations ----------
        $acme = Organization::firstOrCreate(
            ['slug' => 'acme'],
            ['name' => 'Acme', 'plan' => 'trial', 'settings' => []]
        );
        $beta = Organization::firstOrCreate(
            ['slug' => 'beta'],
            ['name' => 'Beta', 'plan' => 'trial', 'settings' => []]
        );

        foreach ([$acme, $beta] as $org) {

            // ---------- Departments ----------
            $deptCstId = DB::table('departments')->updateOrInsert(
                ['organization_id' => $org->id, 'code' => 'CST'],
                ['name' => 'Customer Success & Tech', 'updated_at' => $now, 'created_at' => $now]
            ) ? DB::table('departments')->where(['organization_id'=>$org->id,'code'=>'CST'])->value('id') : null;

            $deptSalesId = DB::table('departments')->updateOrInsert(
                ['organization_id' => $org->id, 'code' => 'SAL'],
                ['name' => 'Sales', 'updated_at' => $now, 'created_at' => $now]
            ) ? DB::table('departments')->where(['organization_id'=>$org->id,'code'=>'SAL'])->value('id') : null;

            $deptCreativeId = DB::table('departments')->updateOrInsert(
                ['organization_id' => $org->id, 'code' => 'CR'],
                ['name' => 'Creative', 'updated_at' => $now, 'created_at' => $now]
            ) ? DB::table('departments')->where(['organization_id'=>$org->id,'code'=>'CR'])->value('id') : null;

            // ---------- Users ----------
            $owner  = User::firstOrCreate(
                ['email' => "owner@{$org->slug}.test"],
                ['name' => "Owner ({$org->name})", 'password' => Hash::make('password')]
            );  $owner->department_id = $deptSalesId; $owner->save();

            $pm     = User::firstOrCreate(
                ['email' => "pm@{$org->slug}.test"],
                ['name' => "PM ({$org->name})", 'password' => Hash::make('password')]
            );  $pm->department_id = $deptCstId; $pm->save();

            $salesA = User::firstOrCreate(
                ['email' => "salesa@{$org->slug}.test"],
                ['name' => "Sales A ({$org->name})", 'password' => Hash::make('password')]
            );  $salesA->department_id = $deptSalesId; $salesA->save();

            $salesB = User::firstOrCreate(
                ['email' => "salesb@{$org->slug}.test"],
                ['name' => "Sales B ({$org->name})", 'password' => Hash::make('password')]
            );  $salesB->department_id = $deptSalesId; $salesB->save();

            foreach ([$owner,$pm,$salesA,$salesB] as $u) {
                DB::table('organization_user')->updateOrInsert(
                    ['organization_id'=>$org->id,'user_id'=>$u->id],
                    ['created_at'=>$now,'updated_at'=>$now]
                );
            }

            // ---------- ROLES (Spatie teams mode) ----------
            // Tell Spatie which "team/org" we are assigning within.
            /** @var PermissionRegistrar $registrar */
            $registrar = app(PermissionRegistrar::class);
            $registrar->setPermissionsTeamId($org->id);

            // Roles are unique per (name, guard, team_id). Create/Find within current team.
            $adminRole = Role::findOrCreate('Admin', 'web');           // team scoped by registrar
            $pmRole    = Role::findOrCreate('ProjectManager', 'web');
            $viewer    = Role::findOrCreate('Viewer', 'web');

            // Assign within current team context (will set model_has_roles.team_id)
            $owner->syncRoles([$adminRole]);
            $pm->syncRoles([$pmRole]);
            $salesA->syncRoles([$viewer]);
            $salesB->syncRoles([$viewer]);

            // ---------- Clients ----------
            $client1Id = DB::table('clients')->updateOrInsert(
                ['organization_id'=>$org->id, 'company_name'=>"{$org->name} Global Media"],
                [
                    'industry'=>'Marketing','niche'=>'Lead Gen',
                    'primary_contact_name'=>'Alex Green',
                    'primary_contact_email'=>"alex@{$org->slug}-client.com",
                    'primary_contact_phone'=>'+1-555-0101',
                    'website'=>'https://example.com','address'=>'101 Market St',
                    'tags'=>json_encode(['vip','retainer']),
                    'fronter'=>json_encode([$salesA->id]),
                    'closer'=>json_encode([$salesB->id]),
                    'assigned_account_manager_id'=>$owner->id,
                    'google_business_profile_status'=>'Created',
                    'google_business_profile_access_status'=>'Access Granted',
                    'client_activation_status'=>'Active',
                    'notes_by_cst'=>'<p>Inspection complete.</p>',
                    'notes_by_sales'=>'<p>High intent.</p>',
                    'notes_by_tech'=>'<p>GMB clean-up pending.</p>',
                    'status'=>'Active',
                    'updated_at'=>$now,'created_at'=>$now
                ]
            ) ? DB::table('clients')->where(['organization_id'=>$org->id,'company_name'=>"{$org->name} Global Media"])->value('id') : null;

            $client2Id = DB::table('clients')->updateOrInsert(
                ['organization_id'=>$org->id, 'company_name'=>"{$org->name} Studio"],
                [
                    'industry'=>'Design','niche'=>'Branding',
                    'primary_contact_name'=>'Brooke Sun',
                    'primary_contact_email'=>"brooke@{$org->slug}-studio.com",
                    'primary_contact_phone'=>'+1-555-0202',
                    'website'=>'https://studio.example.com','address'=>'202 Pine Ave',
                    'tags'=>json_encode(['one-off']),
                    'fronter'=>json_encode([$salesA->id]),
                    'closer'=>json_encode([$salesB->id]),
                    'assigned_account_manager_id'=>$owner->id,
                    'google_business_profile_status'=>'Pending',
                    'google_business_profile_access_status'=>'Access Pending',
                    'client_activation_status'=>'Active',
                    'status'=>'Active',
                    'updated_at'=>$now,'created_at'=>$now
                ]
            ) ? DB::table('clients')->where(['organization_id'=>$org->id,'company_name'=>"{$org->name} Studio"])->value('id') : null;

            // ---------- Projects ----------
            $projAId = DB::table('projects')->updateOrInsert(
                ['organization_id'=>$org->id, 'project_code'=> strtoupper($org->slug).'-001'],
                [
                    'title'=>'Local SEO Rollout','client_id'=>$client1Id,
                    'description'=>'GBP optimization + reviews campaign',
                    'project_manager_id'=>$pm->id, 'department_id'=>$deptCstId,
                    'start_date'=>Carbon::now()->subDays(10),'end_date'=>Carbon::now()->addMonths(2),
                    'status'=>'Active','budget'=>5000,'price'=>6500,'billable'=>true,
                    'google_business_profile_status'=>'Created',
                    'google_business_profile_access_status'=>'Access Granted',
                    'client_activation_status'=>'Active',
                    'attachments'=>json_encode([]),
                    'custom_fields'=>json_encode(['tier'=>'gold']),
                    'updated_at'=>$now,'created_at'=>$now
                ]
            ) ? DB::table('projects')->where(['organization_id'=>$org->id,'project_code'=> strtoupper($org->slug).'-001'])->value('id') : null;

            $projBId = DB::table('projects')->updateOrInsert(
                ['organization_id'=>$org->id, 'project_code'=> strtoupper($org->slug).'-002'],
                [
                    'title'=>'Landing Page + Ads','client_id'=>$client2Id,
                    'description'=>'CRO-focused LP + Google Ads',
                    'project_manager_id'=>$pm->id, 'department_id'=>$deptCstId,
                    'start_date'=>Carbon::now()->subDays(3),'end_date'=>Carbon::now()->addMonth(),
                    'status'=>'Active','budget'=>8000,'price'=>9800,'billable'=>true,
                    'client_activation_status'=>'Active',
                    'attachments'=>json_encode([]),
                    'custom_fields'=>json_encode(['tier'=>'silver']),
                    'updated_at'=>$now,'created_at'=>$now
                ]
            ) ? DB::table('projects')->where(['organization_id'=>$org->id,'project_code'=> strtoupper($org->slug).'-002'])->value('id') : null;

            // ---------- Tasks ----------
            foreach ([[$projAId,'Audit GBP','medium'],[$projAId,'Review workflow','high'],[$projBId,'LP wireframe','high']] as $i => $row) {
                DB::table('tasks')->updateOrInsert(
                    ['organization_id'=>$org->id,'project_id'=>$row[0],'title'=>$row[1]],
                    [
                        'description'=>'Auto-seeded task',
                        'assignees'=>json_encode([$pm->id]),
                        'due_date'=>Carbon::now()->addDays(7+$i),
                        'priority'=>$row[2],
                        'status'=>'In Progress',
                        'estimated_hours'=>6,'logged_hours'=>0,
                        'subtasks'=>json_encode([]),'attachments'=>json_encode([]),
                        'review_status'=>'Pending',
                        'updated_at'=>$now,'created_at'=>$now
                    ]
                );
            }
        }
    }
}
