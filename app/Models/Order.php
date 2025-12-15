<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'hold_id',
        'product_id',
        'qty',
        'total_price',
        'status',
    ];

    protected $casts = [
        'qty' => 'decimal',
        'total_price' => 'integer',
    ];

    // public function product()
    // {
    //     return $this->belongsTo(Product::class);
    // }

    public function hold()
    {
        return $this->belongsTo(Hold::class, 'hold_id');
    }
}
