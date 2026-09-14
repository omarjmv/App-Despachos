<?php

namespace App\Models;

use App\Support\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class ProductCategory extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function products()
    {
        return $this->hasMany(Product::class, 'category_id');
    }
}
