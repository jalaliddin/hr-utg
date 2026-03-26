<?php

namespace Database\Seeders;

use App\Models\HikvisionDevice;
use App\Models\Organization;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $organizations = [
            ['name' => 'Boshqaruv apparati', 'code' => 'BA', 'type' => 'head', 'address' => 'Urganch sh., Markaziy ko\'chasi 1', 'phone' => '+998 62 222-00-01'],
            ['name' => 'Urganch GTS', 'code' => 'QT01', 'type' => 'branch', 'address' => 'Urganch sh.', 'phone' => '+998 62 222-01-01'],
            ['name' => 'Xiva GTS', 'code' => 'QT02', 'type' => 'branch', 'address' => 'Xiva sh.', 'phone' => '+998 62 375-00-01'],
            ['name' => 'Yangibozor GTS', 'code' => 'QT03', 'type' => 'branch', 'address' => 'Yangibozor tum.', 'phone' => '+998 62 233-00-01'],
            ['name' => 'Bog\'ot GTS', 'code' => 'QT04', 'type' => 'branch', 'address' => 'Bog\'ot tum.', 'phone' => '+998 62 246-00-01'],
            ['name' => 'Gurlan GTS', 'code' => 'QT05', 'type' => 'branch', 'address' => 'Gurlan tum.', 'phone' => '+998 62 253-00-01'],
            ['name' => 'Hazorasp GTS', 'code' => 'QT06', 'type' => 'branch', 'address' => 'Hazorasp tum.', 'phone' => '+998 62 255-00-01'],
            ['name' => 'Qo\'shko\'pir GTS', 'code' => 'QT07', 'type' => 'branch', 'address' => 'Qo\'shko\'pir tum.', 'phone' => '+998 62 256-00-01'],
            ['name' => 'Shovot GTS', 'code' => 'QT08', 'type' => 'branch', 'address' => 'Shovot tum.', 'phone' => '+998 62 257-00-01'],
            ['name' => 'Tuproqqal\'a GTS', 'code' => 'QT09', 'type' => 'branch', 'address' => 'Tuproqqal\'a tum.', 'phone' => '+998 62 258-00-01'],
            ['name' => 'Xonqa GTS', 'code' => 'QT10', 'type' => 'branch', 'address' => 'Xonqa tum.', 'phone' => '+998 62 259-00-01'],
            ['name' => 'Pitnak GTS', 'code' => 'QT11', 'type' => 'branch', 'address' => 'Yangiariq tum.', 'phone' => '+998 62 261-00-01'],
            ['name' => 'Mang\'it GTS', 'code' => 'QT12', 'type' => 'branch', 'address' => 'Mang\'it tum.', 'phone' => '+998 62 262-00-01'],
            ['name' => 'Beruniy GTS', 'code' => 'QT13', 'type' => 'branch', 'address' => 'Beruniy tum.', 'phone' => '+998 62 263-00-01'],
            ['name' => 'Nukus GTS', 'code' => 'QT14', 'type' => 'branch', 'address' => 'Nukus sh.', 'phone' => '+998 61 222-00-01'],
        ];

        foreach ($organizations as $i => $orgData) {
            $org = Organization::create($orgData);

            HikvisionDevice::create([
                'organization_id' => $org->id,
                'name' => "DS-K1A340FWX #{$org->code}",
                'ip_address' => '10.10.'.($i + 1).'.10',
                'port' => 80,
                'username' => 'admin',
                'password' => encrypt('admin123'),
                'status' => 'unknown',
            ]);
        }
    }
}
