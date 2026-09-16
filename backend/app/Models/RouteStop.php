<?php

namespace App\Models;

use App\Enums\RouteStopStatus;
use Illuminate\Database\Eloquent\Model;

class RouteStop extends Model
{
    protected $fillable = ['route_id', 'dispatch_id', 'sequence', 'status'];

    protected $casts = ['status' => RouteStopStatus::class];

    public function route()
    {
        return $this->belongsTo(RouteModel::class, 'route_id');
    }

    public function dispatch()
    {
        return $this->belongsTo(Dispatch::class);
    }

    public function delivery()
    {
        return $this->hasOne(Delivery::class);
    }
}
