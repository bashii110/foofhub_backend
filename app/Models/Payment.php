<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'method',
        'screenshot_url',
        'reference',
        'amount',
        'verified',
        'verified_by',
        'verified_at',
        'rejection_reason',
    ];

    protected $casts = [
        'verified'    => 'boolean',
        'verified_at' => 'datetime',
        'amount'      => 'decimal:2',
    ];

    public function order()      { return $this->belongsTo(Order::class); }
    public function verifiedBy() { return $this->belongsTo(User::class, 'verified_by'); }

    public function getScreenshotUrlAttribute($value): ?string
    {
        return $value ? Storage::url($value) : null;
    }
}