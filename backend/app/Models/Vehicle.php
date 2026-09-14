<?php

namespace App\Models;

use App\Support\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $fillable = ['company_id', 'plate', 'brand', 'model', 'capacity', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'capacity' => 'decimal:2'];
}
