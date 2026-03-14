<?php

namespace App\Models;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'address',
        'role',
        'is_active',
        'profile_image',
        'email_verified_at',
        'last_login_at',
        'fcm_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at'     => 'datetime',
        'is_active'         => 'boolean',
    ];

    // ── Roles ──────────────────────────────────────────────────────────
    const ROLE_USER      = 'user';
    const ROLE_ADMIN     = 'admin';
    const ROLE_STAFF     = 'staff';
    const ROLE_RIDER     = 'rider';

    public function isAdmin(): bool  { return $this->role === self::ROLE_ADMIN; }
    public function isStaff(): bool  { return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_STAFF]); }
    public function isRider(): bool  { return $this->role === self::ROLE_RIDER; }
    public function isUser(): bool   { return $this->role === self::ROLE_USER; }
    public function isActive(): bool { return (bool) $this->is_active; }

    // ── JWT ────────────────────────────────────────────────────────────
    public function getJWTIdentifier(): mixed        { return $this->getKey(); }
    public function getJWTCustomClaims(): array      { return ['role' => $this->role]; }

    // ── Relationships ──────────────────────────────────────────────────
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function riderDeliveries()
    {
        return $this->hasMany(Order::class, 'rider_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    // ── Accessors ──────────────────────────────────────────────────────
    public function getProfileImageUrlAttribute(): ?string
    {
        return $this->profile_image
            ? Storage::url($this->profile_image)
            : null;
    }
}