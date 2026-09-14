<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function show(Request $request)
    {
        return new CompanyResource($request->user()->company);
    }

    public function update(Request $request)
    {
        $this->authorize('manage', \App\Models\User::class);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'timezone' => ['sometimes', 'string', 'max:100'],
            'date_format' => ['sometimes', 'string', 'max:50'],
            'settings' => ['sometimes', 'array'],
        ]);

        $company = $request->user()->company;
        $company->update($data);

        return new CompanyResource($company);
    }
}
