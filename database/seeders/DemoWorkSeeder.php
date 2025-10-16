<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoWorkSeeder extends Seeder
{
    public function run(): void
    {
        $org = DB::table('organizations')->where('slug', 'acme')->first();
        if (! $org) {
            return;
        }

        $client = DB::table('clients')->where('organization_id', $org->id)->first();
        if (! $client) {
            $clientId = DB::table('clients')->insertGetId([
                'organization_id' => $org->id,
                'company_name' => 'Umbrella Co.',
                'industry' => 'Tech', 'niche' => 'SaaS',
                'status' => 'Active',
                'fronter' => json_encode([]),
                'closer' => json_encode([]),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        } else {
            $clientId = $client->id;
        }

        $pm = DB::table('users')->join('departments', 'departments.id', '=', 'users.department_id')
            ->where('departments.code', 'CST')->value('users.id') ?? DB::table('users')->value('id');

        $projectId = DB::table('projects')->insertGetId([
            'organization_id' => $org->id,
            'client_id' => $clientId,
            'title' => 'Website Revamp',
            'project_manager_id' => $pm,
            'status' => 'Active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $assignee = DB::table('users')->where('id', '<>', $pm)->value('id') ?? $pm;

        $statuses = ['Backlog', 'In Progress', 'Review', 'Done'];
        foreach (range(1, 8) as $i) {
            DB::table('tasks')->insert([
                'organization_id' => $org->id,
                'project_id' => $projectId,
                'title' => "Task #$i",
                'status' => $statuses[$i % 4],
                'priority' => rand(0, 5),
                'due_date' => now()->addDays($i)->toDateString(),
                'assignees' => json_encode([$assignee]),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // REMOVE the old appended block that used $acme and project_activation_status

        $users = \App\Models\User::whereNotNull('department_id')->take(2)->get();
        $am1 = optional($users->first())->id;
        $am2 = optional($users->last())->id;

        DB::table('clients')->insert([
            [
                'organization_id' => $org->id,
                'company_name' => 'Stark Plumbing',
                'niche' => 'Plumbing',
                'assigned_account_manager_id' => $am1,
                'client_activation_status' => 'Active',
                'status' => 'active',
                'fronter' => json_encode([]),
                'closer' => json_encode([]),
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'organization_id' => $org->id,
                'company_name' => 'Wayne Painting',
                'niche' => 'Painting',
                'assigned_account_manager_id' => $am2,
                'client_activation_status' => 'On Hold',
                'status' => 'active',
                'fronter' => json_encode([]),
                'closer' => json_encode([]),
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

    }
}
