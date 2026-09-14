<?php

namespace App\Models;

use App\Support\Auditable;
use App\Support\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use Auditable, BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'code', 'name', 'description', 'phone', 'email',
        'address', 'latitude', 'longitude', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
