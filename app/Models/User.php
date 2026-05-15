<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'phone_number',
        'profile_picture',
        'role_id',
        'email',
        'password',
        'otp',
        'otp_expires_at',
        'email_verification_token',
        'google_id',
        'ip_address',
        'device',
        'last_activity',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for arrays.
     */
    protected $hidden = [
        'password',
        'remember_token',
        'otp',
        'otp_expires_at',
        'google_id',
        'email_verification_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];
 
    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'role_id' => 'integer',
        'two_factor_enabled' => 'boolean',
        'two_factor_confirmed_at' => 'datetime',
    ];

    /**
     * Get the identifier that will be stored in the JWT subject claim.
     */
    public function getJWTIdentifier()
    {
        return $this->getKey(); // Usually the user's ID
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    public function supplierImportHistories()
    {
        return $this->hasMany(SupplierImportHistory::class, 'uploaded_by');
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function getRoleKeyAttribute(): ?string
    {
        return $this->role?->key;
    }

    public function hasPermission(string $permissionKey): bool
    {
        $role = $this->role()->with('permissions:id,key')->first();
        if (!$role) {
            return false;
        }

        if ($role->key === 'ADMIN') {
            return true;
        }

        $owned = $role->permissions->pluck('key')->all();
        $candidates = $this->expandPermissionCandidates($permissionKey);
        return !empty(array_intersect($candidates, $owned));
    }

    public function hasAnyPermission(array $permissionKeys): bool
    {
        $keys = array_values(array_filter(array_map('strval', $permissionKeys)));
        if ($keys === []) {
            return false;
        }

        $role = $this->role()->with('permissions:id,key')->first();
        if (!$role) {
            return false;
        }

        if ($role->key === 'ADMIN') {
            return true;
        }

        $owned = $role->permissions->pluck('key')->all();
        foreach ($keys as $key) {
            $candidates = $this->expandPermissionCandidates($key);
            if (!empty(array_intersect($candidates, $owned))) {
                return true;
            }
        }

        return false;
    }

    public function hasAllPermissions(array $permissionKeys): bool
    {
        $keys = array_values(array_filter(array_map('strval', $permissionKeys)));
        if ($keys === []) {
            return false;
        }

        $role = $this->role()->with('permissions:id,key')->first();
        if (!$role) {
            return false;
        }

        if ($role->key === 'ADMIN') {
            return true;
        }

        $owned = $role->permissions->pluck('key')->all();
        foreach ($keys as $key) {
            $candidates = $this->expandPermissionCandidates($key);
            if (empty(array_intersect($candidates, $owned))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Expand legacy/current permission aliases for backward compatibility.
     *
     * @return array<int, string>
     */
    private function expandPermissionCandidates(string $permissionKey): array
    {
        $aliases = [
            'dashboard.read_all' => 'dashboard.read',
            'audit_logs.read_all' => 'audit_logs.read',
            'uoms.create' => 'uom.create',
            'uoms.read_all' => 'uom.read_all',
            'uoms.read_own' => 'uom.read_own',
            'uoms.update_all' => 'uom.update_all',
            'uoms.update_own' => 'uom.update_own',
            'uoms.delete_all' => 'uom.delete_all',
            'uoms.delete_own' => 'uom.delete_own',
            'roles.update_all' => 'roles.update',
            'roles.delete_all' => 'roles.delete',
            'suppliers.restore' => 'suppliers.recovery',
            'suppliers.statistics' => 'suppliers.read_history',
            'raw_materials.reorder' => 'raw_materials.create_reorder',
            'raw_materials.scrap' => 'raw_materials.create_scrap',
            'products.reorder' => 'products.create_reorder',
            'products.scrap' => 'products.create_scrap',
            'sale_orders.statistics' => 'sale_orders.read_sale_dashboard',
            'company.manage' => 'company.update',
        ];

        $reverse = array_flip($aliases);
        $candidates = [$permissionKey];
        if (isset($aliases[$permissionKey])) {
            $candidates[] = $aliases[$permissionKey];
        }
        if (isset($reverse[$permissionKey])) {
            $candidates[] = $reverse[$permissionKey];
        }

        return array_values(array_unique($candidates));
    }

    public function rm_stock_movements(){
        return $this -> hasMany(RMStockMovement::class, 'user_id');
    }


}
