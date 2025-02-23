<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier_item_list extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'item_id',
        'price',
        'quantity',
        'challan_id'
    ];
}
