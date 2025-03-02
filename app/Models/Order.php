<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_code',
        'user_id',
        'shipping_id',
        'status',
        'status_chnange_desc',
        'item_subtotal',
        'shipping_chaege',
        'total_amount',
        'coupons_id',
        'discount',
        'user_name',
        'phone',
        'address'
    ];

    public function orderItems()
    {
        return $this->hasMany(Order_list::class, 'order_id');
    }

    // Relationship with User
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Relationship with ShippingAddress
    public function shippingAddress()
    {
        return $this->belongsTo(ShippingAddress::class, 'shipping_id');
    }

    // Relationship with Coupon
    public function coupon()
    {
        return $this->belongsTo(Coupon::class, 'coupons_id');
    }

    // Relationship with Payment
    public function payments()
    {
        return $this->hasMany(Payment::class, 'order_id');
    }

    // Relationship with Activity model
    public function activities()
    {
        return $this->hasMany(Activity::class, 'relatable_id');
    }
}
