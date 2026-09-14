<?php

namespace App\Models;

use App\Support\Auditable;
use App\Support\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use Auditable, BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'category_id', 'sku', 'barcode', 'name', 'description',
        'unit', 'is_active', 'price', 'cost', 'weight', 'volume',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'price' => 'decimal:2',
        'cost' => 'decimal:2',
        'weight' => 'decimal:3',
        'volume' => 'decimal:3',
    ];

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }
}
