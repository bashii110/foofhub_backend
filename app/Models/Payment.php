<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'order_id','method','screenshot_url','reference',
        'verified','verified_by','verified_at','rejection_reason',
    ];
    protected $casts = [
        'verified'    => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function order()      { return $this->belongsTo(Order::class); }
    public function verifiedBy() { return $this->belongsTo(User::class, 'verified_by'); }
}