<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\VehicleResource;
use App\Models\Vehicle;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    public function index()
    {
        return VehicleResource::collection(Vehicle::query()->orderBy('plate')->get());
    }

    public function store(Request $request)
    {
        $this->authorize('manage', Vehicle::class);

        $data = $request->validate([
            'plate' => ['required', 'string', 'max:20'],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'capacity' => ['nullable', 'numeric'],
        ]);

        $vehicle = Vehicle::query()->create([...$data, 'company_id' => $request->user()->company_id]);

        return new VehicleResource($vehicle);
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        $this->authorize('manage', Vehicle::class);

        $data = $request->validate([
            'plate' => ['sometimes', 'string', 'max:20'],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'capacity' => ['nullable', 'numeric'],
            'is_active' => ['boolean'],
        ]);

        $vehicle->update($data);

        return new VehicleResource($vehicle);
    }

    public function destroy(Vehicle $vehicle)
    {
        $this->authorize('manage', Vehicle::class);

        $vehicle->delete();

        return response()->json(status: 204);
    }
}
