<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Customer::class);

        $query = Customer::query()->orderBy('name');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('code', 'like', "%{$request->search}%");
            });
        }

        return CustomerResource::collection($query->paginate(20));
    }

    public function store(StoreCustomerRequest $request)
    {
        $customer = Customer::query()->create([
            ...$request->validated(),
            'company_id' => $request->user()->company_id,
        ]);

        return new CustomerResource($customer);
    }

    public function show(Customer $customer)
    {
        $this->authorize('viewAny', Customer::class);

        return new CustomerResource($customer);
    }

    public function update(StoreCustomerRequest $request, Customer $customer)
    {
        $customer->update($request->validated());

        return new CustomerResource($customer);
    }

    public function destroy(Customer $customer)
    {
        $this->authorize('manage', Customer::class);

        $customer->delete();

        return response()->json(status: 204);
    }
}
