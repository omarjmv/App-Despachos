<?php

namespace App\Models;

use App\Enums\PreparationStatus;
use App\Support\Auditable;
use App\Support\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class Preparation extends Model
{
    use Auditable, BelongsToCompany;

    protected $fillable = ['company_id', 'order_id', 'prepared_by', 'status', 'started_at', 'finished_at'];

    protected $casts = [
        'status' => PreparationStatus::class,
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function preparedBy()
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function items()
    {
        return $this->hasMany(PreparationItem::class);
    }

    public function review()
    {
        return $this->hasOne(Review::class)->latestOfMany();
    }

    public function totalPrepared(): float
    {
        return (float) $this->items()->sum('quantity_prepared');
    }
}
