<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Client;
use App\Models\Organization;

class ClientDemoSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::firstOrCreate(['slug' => 'acme'], ['name' => 'Acme']);

        // create some lightweight demo rows
        foreach (['Acme Global Media','Northwind Labs','Blue Horizon'] as $i => $name) {
            Client::firstOrCreate(
                ['organization_id' => $org->id, 'company_name' => $name],
                [
                    'primary_contact_name' => 'Contact '.$i,
                    'primary_contact_email'=> "contact{$i}@example.com",
                    'niche' => 'Marketing',
                    'status' => 'Active',
                ]
            );
        }
    }
}
