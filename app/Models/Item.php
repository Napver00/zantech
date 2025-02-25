<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'short_description',
        'status',
        'quantity',
        'price',
        'discount'
    ];

    // Relationship with Cetagory_Product_list
    public function categories()
    {
        return $this->hasMany(Cetagory_Product_list::class, 'item_id');
    }

    // Relationship with Tag
    public function tags()
    {
        return $this->hasMany(Tag::class, 'item_id');
    }

    // Relationship with File (images)
    public function images()
    {
        return $this->hasMany(File::class, 'relatable_id')->where('type', 'product');
    }
}
