<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DispatchItem extends Model
{
    protected $fillable = ['dispatch_id', 'preparation_item_id', 'quantity_dispatched'];

    protected $casts = ['quantity_dispatched' => 'decimal:2'];

    public function dispatch()
    {
        return $this->belongsTo(Dispatch::class);
    }

    public function preparationItem()
    {
        return $this->belongsTo(PreparationItem::class);
    }
}
