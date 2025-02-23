<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Challan extends Model
{
    use HasFactory;

    protected $fillable = [
        'Date',
        'item_id',
        'user_id',
        'total',
        'delivery_price',
        'supplier_id'
    ];
}
