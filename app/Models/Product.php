<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id','name','description','price','image_url',
        'preparation_time','calories','ingredients','is_popular','is_available',
    ];
    protected $casts = [
        'price'         => 'float',
        'is_popular'    => 'boolean',
        'is_available'  => 'boolean',
        'ingredients'   => 'array',
    ];

    public function category()   { return $this->belongsTo(Category::class); }
    public function orderItems() { return $this->hasMany(OrderItem::class); }
}