<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'product_id', 'quantity_requested', 'unit', 'notes'];

    protected $casts = ['quantity_requested' => 'decimal:2'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function preparationItems()
    {
        return $this->hasMany(PreparationItem::class);
    }
}
