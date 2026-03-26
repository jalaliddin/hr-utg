<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Super Administrator',
            'email' => 'admin@utg.uz',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
        ]);

        $headOrg = Organization::where('code', 'BA')->first();
        if ($headOrg) {
            User::create([
                'name' => 'HR Menejer',
                'email' => 'hr@utg.uz',
                'password' => Hash::make('password'),
                'role' => 'hr_manager',
                'organization_id' => $headOrg->id,
            ]);
        }

        $branches = Organization::where('type', 'branch')->take(3)->get();
        foreach ($branches as $org) {
            User::create([
                'name' => "{$org->name} Admin",
                'email' => strtolower($org->code).'@utg.uz',
                'password' => Hash::make('password'),
                'role' => 'org_admin',
                'organization_id' => $org->id,
            ]);
        }
    }
}
