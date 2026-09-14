<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;

class ProductCategoryController extends Controller
{
    public function index()
    {
        return ProductCategory::query()->orderBy('name')->get(['id', 'name', 'is_active']);
    }
}
