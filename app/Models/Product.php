<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'description',
        'price',
        'image_path',
        'preparation_time',
        'calories',
        'ingredients',
        'is_popular',
        'is_available',
        'stock',
    ];

    protected $casts = [
        'price'        => 'decimal:2',
        'ingredients'  => 'array',
        'is_popular'   => 'boolean',
        'is_available' => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────────
    public function category()   { return $this->belongsTo(Category::class); }
    public function orderItems() { return $this->hasMany(OrderItem::class); }

    // ── Scopes ─────────────────────────────────────────────────────────
    public function scopeAvailable($query)  { return $query->where('is_available', true); }
    public function scopePopular($query)    { return $query->where('is_popular', true); }

    // ── Accessors ──────────────────────────────────────────────────────
    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path
            ? Storage::url($this->image_path)
            : null;
    }

    // ── Append custom attributes ───────────────────────────────────────
    protected $appends = ['image_url'];
    protected $hidden  = ['image_path'];
}