<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RoleAPIController extends Controller
{
    private function ensureCatalogPermissions(): void
    {
        foreach (PermissionCatalog::flatPermissions() as $permission) {
            Permission::query()->updateOrCreate(
                ['key' => $permission['key']],
                [
                    'module' => $permission['module'],
                    'action' => $permission['action'],
                ]
            );
        }
    }

    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->query('per_page', 15), 100));
        $search = trim((string) $request->query('search', ''));

        $query = Role::query()
            ->withCount('users')
            ->withCount('permissions')
            ->orderByDesc('is_system')
            ->orderBy('name');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('key', 'like', "%{$search}%");
            });
        }

        return ResponseHelper::success($query->paginate($perPage), 'Roles retrieved successfully.');
    }

    public function show(int $id)
    {
        $role = Role::query()
            ->withCount('users')
            ->with('permissions:id,module,action,key')
            ->findOrFail($id);

        return ResponseHelper::success($role, 'Role retrieved successfully.');
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'key' => 'required|string|max:255|unique:roles,key',
                'description' => 'nullable|string|max:2000',
                'permission_ids' => 'nullable|array',
                'permission_ids.*' => 'integer|exists:permissions,id',
                'permissions' => 'nullable|array',
                'permissions.*' => 'string|exists:permissions,key',
            ]);

            $role = Role::query()->create([
                'name' => $validated['name'],
                'key' => strtoupper($validated['key']),
                'description' => $validated['description'] ?? null,
                'is_system' => false,
            ]);

            $permissionIds = [];
            if (!empty($validated['permission_ids']) && is_array($validated['permission_ids'])) {
                $permissionIds = array_map('intval', $validated['permission_ids']);
            } elseif (!empty($validated['permissions']) && is_array($validated['permissions'])) {
                $permissionIds = Permission::query()
                    ->whereIn('key', $validated['permissions'])
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
            }

            if (!empty($permissionIds)) {
                $role->permissions()->sync($permissionIds);
            }

            $role->load('permissions:id,module,action,key');

            return ResponseHelper::success($role, 'Role created successfully.', 201);
        } catch (ValidationException $e) {
            return ResponseHelper::validation($e->errors(), 'Validation Error');
        }
    }

    public function update(Request $request, int $id)
    {
        try {
            $role = Role::query()->findOrFail($id);
            if ($role->is_system) {
                return ResponseHelper::error('Forbidden', 403, 'System roles cannot be edited.');
            }

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'key' => 'sometimes|required|string|max:255|unique:roles,key,' . $role->id,
                'description' => 'nullable|string|max:2000',
            ]);

            if (array_key_exists('name', $validated)) {
                $role->name = $validated['name'];
            }
            if (array_key_exists('key', $validated)) {
                $role->key = strtoupper($validated['key']);
            }
            if (array_key_exists('description', $validated)) {
                $role->description = $validated['description'];
            }
            $role->save();

            return ResponseHelper::success($role, 'Role updated successfully.');
        } catch (ValidationException $e) {
            return ResponseHelper::validation($e->errors(), 'Validation Error');
        }
    }

    public function destroy(int $id)
    {
        $role = Role::query()->findOrFail($id);
        if ($role->is_system) {
            return ResponseHelper::error('Forbidden', 403, 'System roles cannot be deleted.');
        }

        if ($role->users()->exists()) {
            return ResponseHelper::error('Conflict', 409, 'Role is assigned to users and cannot be deleted.');
        }

        $role->permissions()->detach();
        $role->delete();

        return ResponseHelper::success(null, 'Role deleted successfully.');
    }

    public function permissions()
    {
        // Self-heal permission catalog so newly introduced canonical keys
        // are always available in role matrix UI without requiring manual seeding.
        $this->ensureCatalogPermissions();

        $allPermissions = Permission::query()
            ->get(['id', 'module', 'action', 'key'])
            ->keyBy('key');

        $grouped = [];
        foreach (PermissionCatalog::modules() as $moduleKey => $moduleConfig) {
            $rows = [];
            foreach ($moduleConfig['actions'] as $actionConfig) {
                $permissionKey = $moduleKey . '.' . $actionConfig['key'];
                $permission = $allPermissions->get($permissionKey);
                if (!$permission) {
                    continue;
                }

                $rows[] = [
                    'id' => (int) $permission->id,
                    'key' => (string) $permission->key,
                    'action' => (string) $permission->action,
                    'label' => (string) $actionConfig['label'],
                ];
            }

            if ($rows !== []) {
                $grouped[] = [
                    'module' => (string) $moduleConfig['label'],
                    'key' => (string) $moduleKey,
                    'module_key' => (string) $moduleKey,
                    'permissions' => $rows,
                ];
            }
        }

        return ResponseHelper::success($grouped, 'Permissions retrieved successfully.');
    }

    public function updatePermissions(Request $request, int $id)
    {
        try {
            $role = Role::query()->findOrFail($id);
            if ($role->is_system) {
                return ResponseHelper::error('Forbidden', 403, 'System role permissions cannot be modified.');
            }

            $validated = $request->validate([
                'permission_ids' => 'nullable|array',
                'permission_ids.*' => 'integer|exists:permissions,id',
                'permissions' => 'nullable|array',
                'permissions.*' => 'string|exists:permissions,key',
            ]);

            $permissionIds = [];
            if (!empty($validated['permission_ids']) && is_array($validated['permission_ids'])) {
                $permissionIds = array_map('intval', $validated['permission_ids']);
            } elseif (!empty($validated['permissions']) && is_array($validated['permissions'])) {
                $permissionIds = Permission::query()
                    ->whereIn('key', $validated['permissions'])
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
            }

            $role->permissions()->sync($permissionIds);
            $role->touch();
            $role->load('permissions:id,module,action,key');

            return ResponseHelper::success($role, 'Role permissions updated successfully.');
        } catch (ValidationException $e) {
            return ResponseHelper::validation($e->errors(), 'Validation Error');
        }
    }

    public function selectOptions()
    {
        $roles = Role::query()
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get(['id', 'name', 'key', 'is_system']);

        return ResponseHelper::success($roles, 'Role options retrieved successfully.');
    }
}
