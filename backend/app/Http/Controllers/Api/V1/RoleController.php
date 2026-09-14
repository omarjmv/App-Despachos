<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Models\Role;

class RoleController extends Controller
{
    public function index()
    {
        return RoleResource::collection(Role::query()->orderBy('name')->get());
    }
}
