<?php

namespace App\Models;

use App\Support\Auditable;
use App\Support\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class Dispatch extends Model
{
    use Auditable, BelongsToCompany;

    protected $table = 'dispatches';

    protected $fillable = [
        'company_id', 'number', 'order_id', 'review_id', 'vehicle_id',
        'driver_id', 'dispatched_by', 'dispatched_at',
    ];

    protected $casts = ['dispatched_at' => 'datetime'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function review()
    {
        return $this->belongsTo(Review::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function dispatchedBy()
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function items()
    {
        return $this->hasMany(DispatchItem::class);
    }
}
