<?php
namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Model
    implements AuthenticatableContract, CanResetPasswordContract, JWTSubject
{
    use Authenticatable, CanResetPassword;

    protected $fillable = [
        'name','email','password','role','phone','address','is_active',
    ];
    protected $hidden = ['password'];
    protected $casts  = ['is_active' => 'boolean'];

    // JWT
    public function getJWTIdentifier()   { return $this->getKey(); }
    public function getJWTCustomClaims() { return []; }

    // Relations
    public function orders() { return $this->hasMany(Order::class); }

    // Helpers
    public function isAdmin(): bool { return $this->role === 'admin'; }
    public function isStaff(): bool { return in_array($this->role, ['admin','staff']); }
}