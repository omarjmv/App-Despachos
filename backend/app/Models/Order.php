<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Support\Auditable;
use App\Support\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use Auditable, BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'number', 'customer_id', 'created_by', 'assigned_to',
        'order_date', 'required_date', 'status', 'notes', 'cancellation_reason',
    ];

    protected $casts = [
        'order_date' => 'date',
        'required_date' => 'date',
        'status' => OrderStatus::class,
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function preparations()
    {
        return $this->hasMany(Preparation::class);
    }

    public function latestPreparation()
    {
        return $this->hasOne(Preparation::class)->latestOfMany();
    }

    public function dispatches()
    {
        return $this->hasMany(Dispatch::class);
    }

    public function totalRequested(): float
    {
        return (float) $this->items()->sum('quantity_requested');
    }
}
