<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryItem extends Model
{
    protected $fillable = [
        'delivery_id', 'dispatch_item_id', 'quantity_delivered', 'quantity_rejected', 'rejection_reason',
    ];

    protected $casts = [
        'quantity_delivered' => 'decimal:2',
        'quantity_rejected' => 'decimal:2',
    ];

    public function delivery()
    {
        return $this->belongsTo(Delivery::class);
    }

    public function dispatchItem()
    {
        return $this->belongsTo(DispatchItem::class);
    }
}
