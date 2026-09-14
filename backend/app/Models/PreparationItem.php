<?php

namespace App\Models;

use App\Enums\DifferenceReason;
use Illuminate\Database\Eloquent\Model;

class PreparationItem extends Model
{
    protected $fillable = [
        'preparation_id', 'order_item_id', 'quantity_prepared',
        'difference_reason', 'difference_notes',
    ];

    protected $casts = [
        'quantity_prepared' => 'decimal:2',
        'difference_reason' => DifferenceReason::class,
    ];

    public function preparation()
    {
        return $this->belongsTo(Preparation::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function dispatchItems()
    {
        return $this->hasMany(DispatchItem::class);
    }
}
