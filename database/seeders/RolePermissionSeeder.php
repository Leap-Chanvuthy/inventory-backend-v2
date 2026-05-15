<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $roles = Role::query()->get()->keyBy('key');
        $allPermissionIds = Permission::query()->pluck('id')->all();

        $admin = $roles->get('ADMIN');
        if ($admin) {
            $admin->permissions()->sync($allPermissionIds);
        }

        $stockControllerKeys = [
            'dashboard.read',
            'suppliers.create',
            'suppliers.import',
            'suppliers.update_all',
            'suppliers.update_own',
            'suppliers.read_all',
            'suppliers.read_own',
            'suppliers.recovery',
            'suppliers.read_history',

            'raw_materials.create',
            'raw_materials.read_all',
            'raw_materials.read_own',
            'raw_materials.update_all',
            'raw_materials.update_own',
            'raw_materials.delete_all',
            'raw_materials.delete_own',
            'raw_materials.create_reorder',
            'raw_materials.update_reorder_all',
            'raw_materials.update_reorder_own',
            'raw_materials.create_scrap',
            'raw_materials.update_scrap_all',
            'raw_materials.update_scrap_own',

            'products.create',
            'products.read_all',
            'products.read_own',
            'products.update_all',
            'products.update_own',
            'products.delete_all',
            'products.delete_own',
            'products.create_reorder',
            'products.update_reorder_all',
            'products.update_reorder_own',
            'products.create_scrap',
            'products.update_scrap_all',
            'products.update_scrap_own',

            'warehouses.create',
            'warehouses.read_all',
            'warehouses.read_own',
            'warehouses.update_all',
            'warehouses.update_own',
            'warehouses.delete_all',
            'warehouses.delete_own',

            'uom.create',
            'uom.read_all',
            'uom.read_own',
            'uom.update_all',
            'uom.update_own',
            'uom.delete_all',
            'uom.delete_own',

            'raw_material_categories.create',
            'raw_material_categories.read_all',
            'raw_material_categories.read_own',
            'raw_material_categories.update_all',
            'raw_material_categories.update_own',
            'raw_material_categories.delete_all',

            'product_categories.create',
            'product_categories.read_all',
            'product_categories.read_own',
            'product_categories.update_all',
            'product_categories.update_own',
            'product_categories.delete_all',
        ];

        $venderKeys = [
            'dashboard.read',
            'products.read_all',
            'customers.create',
            'customers.read_all',
            'customers.read_own',
            'customers.update_all',
            'customers.update_own',
            'customers.delete_all',
            'customers.delete_own',
            'sale_orders.create',
            'sale_orders.read_all',
            'sale_orders.read_own',
            'sale_orders.update_all',
            'sale_orders.update_own',
            'sale_orders.read_sale_dashboard',
            'customer_categories.create',
            'customer_categories.read_all',
            'customer_categories.read_own',
            'customer_categories.update_all',
            'customer_categories.update_own',
            'customer_categories.delete_all',
        ];

        $this->syncRolePermissions($roles->get('STOCK_CONTROLLER'), $stockControllerKeys);
        $this->syncRolePermissions($roles->get('VENDER'), $venderKeys);
    }

    private function syncRolePermissions(?Role $role, array $permissionKeys): void
    {
        if (!$role) {
            return;
        }

        $ids = Permission::query()
            ->whereIn('key', $permissionKeys)
            ->pluck('id')
            ->all();

        $role->permissions()->sync($ids);
    }
}
