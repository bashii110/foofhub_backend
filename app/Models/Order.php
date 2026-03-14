<?php

namespace App\Models;

use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'rider_id',
        'customer_name',
        'phone',
        'delivery_address',
        'delivery_lat',
        'delivery_lng',
        'subtotal',
        'delivery_fee',
        'discount',
        'total_amount',
        'payment_method',
        'payment_status',
        'status',
        'notes',
        'admin_notes',
        'estimated_delivery_at',
        'delivered_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'subtotal'               => 'decimal:2',
        'delivery_fee'           => 'decimal:2',
        'discount'               => 'decimal:2',
        'total_amount'           => 'decimal:2',
        'delivery_lat'           => 'float',
        'delivery_lng'           => 'float',
        'estimated_delivery_at'  => 'datetime',
        'delivered_at'           => 'datetime',
        'cancelled_at'           => 'datetime',
    ];

    // ── Status Constants ───────────────────────────────────────────────
    const STATUS_PENDING_PAYMENT      = 'pending_payment';
    const STATUS_PENDING_VERIFICATION = 'pending_verification';
    const STATUS_VERIFIED             = 'verified';
    const STATUS_PREPARING            = 'preparing';
    const STATUS_OUT_FOR_DELIVERY     = 'out_for_delivery';
    const STATUS_DELIVERED            = 'delivered';
    const STATUS_CANCELLED            = 'cancelled';

    const PAYMENT_STATUS_PENDING  = 'pending';
    const PAYMENT_STATUS_PAID     = 'paid';
    const PAYMENT_STATUS_FAILED   = 'failed';
    const PAYMENT_STATUS_REFUNDED = 'refunded';

    const REVENUE_STATUSES = [
        self::STATUS_VERIFIED,
        self::STATUS_PREPARING,
        self::STATUS_OUT_FOR_DELIVERY,
        self::STATUS_DELIVERED,
    ];

    const CANCELLABLE_STATUSES = [
        self::STATUS_PENDING_PAYMENT,
        self::STATUS_PENDING_VERIFICATION,
    ];

    // ── Relationships ──────────────────────────────────────────────────
    public function user()      { return $this->belongsTo(User::class); }
    public function rider()     { return $this->belongsTo(User::class, 'rider_id'); }
    public function items()     { return $this->hasMany(OrderItem::class); }
    public function payment()   { return $this->hasOne(Payment::class); }
    public function statusLogs(){ return $this->hasMany(OrderStatusLog::class); }

    // ── Scopes ─────────────────────────────────────────────────────────
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeRevenue($query)
    {
        return $query->whereIn('status', self::REVENUE_STATUSES);
    }

    // ── Helpers ────────────────────────────────────────────────────────
    public function isCancellable(): bool
    {
        return in_array($this->status, self::CANCELLABLE_STATUSES);
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }
}