<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        // Lectura amplia: un supervisor necesita ver preparadores/motoristas
        // para asignar pedidos y despachos, aunque no pueda administrarlos.
        $this->authorize('viewAny', User::class);

        $query = User::query()->with('role')->orderBy('name');

        if ($request->filled('role')) {
            $query->whereHas('role', fn ($q) => $q->where('name', $request->string('role')));
        }

        return UserResource::collection($query->paginate(20));
    }

    public function store(StoreUserRequest $request)
    {
        $user = User::query()->create([
            ...$request->safe()->except('password'),
            'company_id' => $request->user()->company_id,
            'password' => $request->validated('password'),
        ]);

        return new UserResource($user->load('role'));
    }

    public function show(User $user)
    {
        $this->authorize('manage', User::class);

        return new UserResource($user->load('role'));
    }

    public function update(StoreUserRequest $request, User $user)
    {
        $data = $request->safe()->except('password');

        if ($request->filled('password')) {
            $data['password'] = $request->validated('password');
        }

        $user->update($data);

        return new UserResource($user->load('role'));
    }

    public function destroy(User $user)
    {
        $this->authorize('manage', User::class);

        $user->delete();

        return response()->json(status: 204);
    }
}
