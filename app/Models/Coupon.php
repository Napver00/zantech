<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;
    protected $fillable = [
        'code',
        'amount',
        'type',                 // flat or percent
        'is_global',            // true = applies to all products
        'max_usage',            // overall max usage
        'max_usage_per_user',   // per user limit
        'start_date',
        'end_date',
    ];

    // Relationship with Activity model
    public function activities()
    {
        return $this->hasMany(Activity::class, 'relatable_id');
    }

    // Many-to-Many with Item (products)
    public function items()
    {
        return $this->belongsToMany(Item::class, 'coupon_products');
    }
}
