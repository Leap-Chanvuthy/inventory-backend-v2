<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public const SYSTEM_ROLES = [
        ['name' => 'Administrator', 'key' => 'ADMIN'],
        ['name' => 'Stock Controller', 'key' => 'STOCK_CONTROLLER'],
        ['name' => 'Vender', 'key' => 'VENDER'],
    ];

    public function run(): void
    {
        foreach (self::SYSTEM_ROLES as $role) {
            Role::query()->updateOrCreate(
                ['key' => $role['key']],
                [
                    'name' => $role['name'],
                    'is_system' => true,
                ]
            );
        }
    }
}

