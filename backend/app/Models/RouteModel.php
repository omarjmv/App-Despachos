<?php

namespace App\Models;

use App\Enums\RouteStatus;
use App\Support\Auditable;
use App\Support\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * Se nombra RouteModel (no Route) para no chocar con
 * Illuminate\Routing\Route, muy usado en todo el framework.
 */
class RouteModel extends Model
{
    use Auditable, BelongsToCompany;

    protected $table = 'routes';

    protected $fillable = [
        'company_id', 'code', 'vehicle_id', 'driver_id', 'status', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'status' => RouteStatus::class,
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function stops()
    {
        return $this->hasMany(RouteStop::class, 'route_id')->orderBy('sequence');
    }
}
