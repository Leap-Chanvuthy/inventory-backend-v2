<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * @return array<int, array{module:string, action:string, key:string, label:string}>
     */
    public static function defaults(): array
    {
        return PermissionCatalog::flatPermissions();
    }

    public function run(): void
    {
        $defaults = self::defaults();
        $allowedKeys = [];

        foreach ($defaults as $permission) {
            $allowedKeys[] = $permission['key'];
            Permission::query()->updateOrCreate(
                ['key' => $permission['key']],
                [
                    'module' => $permission['module'],
                    'action' => $permission['action'],
                ]
            );
        }

        Permission::query()
            ->whereNotIn('key', $allowedKeys)
            ->delete();
    }
}
