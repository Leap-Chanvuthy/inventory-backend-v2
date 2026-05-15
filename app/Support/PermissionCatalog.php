<?php

namespace App\Support;

class PermissionCatalog
{
    /**
     * Canonical permission matrix per feature/module.
     *
     * @return array<string, array{label:string, actions:array<int, array{key:string,label:string}>}>
     */
    public static function modules(): array
    {
        return [
            'users' => [
                'label' => 'Users',
                'actions' => [
                    ['key' => 'create', 'label' => 'Create'],
                    ['key' => 'read_all', 'label' => 'Read All'],
                    ['key' => 'read_own', 'label' => 'Read Own'],
                    ['key' => 'update_all', 'label' => 'Update All'],
                    ['key' => 'update_own', 'label' => 'Update Own'],
                    ['key' => 'delete_all', 'label' => 'Delete All'],
                    ['key' => 'delete_own', 'label' => 'Delete Own'],
                ],
            ],
            'dashboard' => [
                'label' => 'Dashboard',
                'actions' => [
                    ['key' => 'read', 'label' => 'Read Only'],
                ],
            ],
            'audit_logs' => [
                'label' => 'Audit Logs',
                'actions' => [
                    ['key' => 'read', 'label' => 'Read Only'],
                ],
            ],
            'suppliers' => [
                'label' => 'Suppliers',
                'actions' => [
                    ['key' => 'create', 'label' => 'Create'],
                    ['key' => 'import', 'label' => 'Import'],
                    ['key' => 'update_all', 'label' => 'Update All'],
                    ['key' => 'update_own', 'label' => 'Update Own'],
                    ['key' => 'read_all', 'label' => 'Read All'],
                    ['key' => 'read_own', 'label' => 'Read Own'],
                    ['key' => 'recovery', 'label' => 'Recovery'],
                    ['key' => 'read_history', 'label' => 'Read History'],
                ],
            ],
            'raw_materials' => [
                'label' => 'Raw Materials',
                'actions' => [
                    ['key' => 'create', 'label' => 'Create'],
                    ['key' => 'read_all', 'label' => 'Read All'],
                    ['key' => 'read_own', 'label' => 'Read Own'],
                    ['key' => 'update_all', 'label' => 'Update All'],
                    ['key' => 'update_own', 'label' => 'Update Own'],
                    ['key' => 'delete_all', 'label' => 'Delete All'],
                    ['key' => 'delete_own', 'label' => 'Delete Own'],
                    ['key' => 'create_reorder', 'label' => 'Create Reorder'],
                    ['key' => 'update_reorder_all', 'label' => 'Update Reorder All'],
                    ['key' => 'update_reorder_own', 'label' => 'Update Reorder Own'],
                    ['key' => 'create_scrap', 'label' => 'Create Scrap'],
                    ['key' => 'update_scrap_all', 'label' => 'Update Scrap All'],
                    ['key' => 'update_scrap_own', 'label' => 'Update Scrap Own'],
                ],
            ],
            'products' => [
                'label' => 'Products',
                'actions' => [
                    ['key' => 'create', 'label' => 'Create'],
                    ['key' => 'read_all', 'label' => 'Read All'],
                    ['key' => 'read_own', 'label' => 'Read Own'],
                    ['key' => 'update_all', 'label' => 'Update All'],
                    ['key' => 'update_own', 'label' => 'Update Own'],
                    ['key' => 'delete_all', 'label' => 'Delete All'],
                    ['key' => 'delete_own', 'label' => 'Delete Own'],
                    ['key' => 'create_reorder', 'label' => 'Create Reorder'],
                    ['key' => 'update_reorder_all', 'label' => 'Update Reorder All'],
                    ['key' => 'update_reorder_own', 'label' => 'Update Reorder Own'],
                    ['key' => 'create_scrap', 'label' => 'Create Scrap'],
                    ['key' => 'update_scrap_all', 'label' => 'Update Scrap All'],
                    ['key' => 'update_scrap_own', 'label' => 'Update Scrap Own'],
                ],
            ],
            'warehouses' => [
                'label' => 'Multi-Warehouse',
                'actions' => [
                    ['key' => 'create', 'label' => 'Create'],
                    ['key' => 'read_all', 'label' => 'Read All'],
                    ['key' => 'read_own', 'label' => 'Read Own'],
                    ['key' => 'update_all', 'label' => 'Update All'],
                    ['key' => 'update_own', 'label' => 'Update Own'],
                    ['key' => 'delete_all', 'label' => 'Delete All'],
                    ['key' => 'delete_own', 'label' => 'Delete Own'],
                ],
            ],
            'uom' => [
                'label' => 'Unit of Measurement',
                'actions' => [
                    ['key' => 'create', 'label' => 'Create'],
                    ['key' => 'read_all', 'label' => 'Read All'],
                    ['key' => 'read_own', 'label' => 'Read Own'],
                    ['key' => 'update_all', 'label' => 'Update All'],
                    ['key' => 'update_own', 'label' => 'Update Own'],
                    ['key' => 'delete_all', 'label' => 'Delete All'],
                    ['key' => 'delete_own', 'label' => 'Delete Own'],
                ],
            ],
            'customers' => [
                'label' => 'Customers',
                'actions' => [
                    ['key' => 'create', 'label' => 'Create'],
                    ['key' => 'read_all', 'label' => 'Read All'],
                    ['key' => 'read_own', 'label' => 'Read Own'],
                    ['key' => 'update_all', 'label' => 'Update All'],
                    ['key' => 'update_own', 'label' => 'Update Own'],
                    ['key' => 'delete_all', 'label' => 'Delete All'],
                    ['key' => 'delete_own', 'label' => 'Delete Own'],
                ],
            ],
            'sale_orders' => [
                'label' => 'Sale Orders',
                'actions' => [
                    ['key' => 'create', 'label' => 'Create'],
                    ['key' => 'read_all', 'label' => 'Read All'],
                    ['key' => 'read_own', 'label' => 'Read Own'],
                    ['key' => 'update_all', 'label' => 'Update All'],
                    ['key' => 'update_own', 'label' => 'Update Own'],
                    ['key' => 'read_sale_dashboard', 'label' => 'Read Sale Dashboard'],
                ],
            ],
            'raw_material_categories' => [
                'label' => 'Raw Material Categories',
                'actions' => [
                    ['key' => 'create', 'label' => 'Create'],
                    ['key' => 'read_all', 'label' => 'Read All'],
                    ['key' => 'read_own', 'label' => 'Read Own'],
                    ['key' => 'update_all', 'label' => 'Update All'],
                    ['key' => 'update_own', 'label' => 'Update Own'],
                    ['key' => 'delete_all', 'label' => 'Delete All'],
                ],
            ],
            'product_categories' => [
                'label' => 'Product Categories',
                'actions' => [
                    ['key' => 'create', 'label' => 'Create'],
                    ['key' => 'read_all', 'label' => 'Read All'],
                    ['key' => 'read_own', 'label' => 'Read Own'],
                    ['key' => 'update_all', 'label' => 'Update All'],
                    ['key' => 'update_own', 'label' => 'Update Own'],
                    ['key' => 'delete_all', 'label' => 'Delete All'],
                ],
            ],
            'customer_categories' => [
                'label' => 'Customer Categories',
                'actions' => [
                    ['key' => 'create', 'label' => 'Create'],
                    ['key' => 'read_all', 'label' => 'Read All'],
                    ['key' => 'read_own', 'label' => 'Read Own'],
                    ['key' => 'update_all', 'label' => 'Update All'],
                    ['key' => 'update_own', 'label' => 'Update Own'],
                    ['key' => 'delete_all', 'label' => 'Delete All'],
                ],
            ],
            'company' => [
                'label' => 'Company Profile',
                'actions' => [
                    ['key' => 'create', 'label' => 'Create'],
                    ['key' => 'read', 'label' => 'Read'],
                    ['key' => 'update', 'label' => 'Update'],
                ],
            ],
            'roles' => [
                'label' => 'Roles & Permissions',
                'actions' => [
                    ['key' => 'read_all', 'label' => 'Read All'],
                    ['key' => 'create', 'label' => 'Create'],
                    ['key' => 'update', 'label' => 'Update'],
                    ['key' => 'delete', 'label' => 'Delete'],
                    ['key' => 'assign_permissions', 'label' => 'Assign Permissions'],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array{module:string, action:string, key:string, label:string}>
     */
    public static function flatPermissions(): array
    {
        $rows = [];
        foreach (self::modules() as $moduleKey => $moduleConfig) {
            foreach ($moduleConfig['actions'] as $action) {
                $rows[] = [
                    'module' => (string) $moduleKey,
                    'action' => (string) $action['key'],
                    'key' => "{$moduleKey}.{$action['key']}",
                    'label' => (string) $action['label'],
                ];
            }
        }

        return $rows;
    }
}

