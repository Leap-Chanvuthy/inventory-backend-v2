<?php

namespace App\Models;

use App\Enums\UserRoleEnum;
use Illuminate\Contracts\Auth\MustVerifyEmail;
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
        'role',
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
    ];
 
    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'role' => UserRoleEnum::class,
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


}