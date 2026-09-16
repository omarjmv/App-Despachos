<?php

namespace App\Models;

use App\Enums\DeliveryResult;
use App\Support\Auditable;
use App\Support\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    use Auditable, BelongsToCompany;

    protected $fillable = [
        'company_id', 'route_stop_id', 'dispatch_id', 'client_uuid', 'delivered_by',
        'result', 'rejection_reason', 'notes', 'latitude', 'longitude',
        'delivered_at', 'synced_at',
    ];

    protected $casts = [
        'result' => DeliveryResult::class,
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'delivered_at' => 'datetime',
        'synced_at' => 'datetime',
    ];

    public function routeStop()
    {
        return $this->belongsTo(RouteStop::class);
    }

    public function dispatch()
    {
        return $this->belongsTo(Dispatch::class);
    }

    public function deliveredBy()
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    public function items()
    {
        return $this->hasMany(DeliveryItem::class);
    }

    public function evidence()
    {
        return $this->hasMany(DeliveryEvidence::class);
    }
}
