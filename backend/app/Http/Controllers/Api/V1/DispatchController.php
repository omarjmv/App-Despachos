<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dispatch\StoreDispatchRequest;
use App\Http\Resources\DispatchResource;
use App\Models\Dispatch;
use App\Models\Order;
use App\Services\DispatchService;
use Illuminate\Http\Request;

class DispatchController extends Controller
{
    public function __construct(private readonly DispatchService $dispatches) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Dispatch::class);

        $query = Dispatch::query()->with(['order.customer', 'vehicle', 'driver', 'items.preparationItem.orderItem.product'])->latest();

        // Despachos que aún no se agregaron a ninguna ruta (para armar una nueva).
        if ($request->boolean('unrouted')) {
            $query->whereDoesntHave('routeStop');
        }

        if ($request->filled('customer_id')) {
            $query->whereHas('order', fn ($q) => $q->where('customer_id', $request->integer('customer_id')));
        }

        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->integer('vehicle_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('dispatched_at', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('dispatched_at', '<=', $request->date('date_to'));
        }

        return DispatchResource::collection($query->paginate(20));
    }

    public function store(StoreDispatchRequest $request, Order $order)
    {
        $review = $order->latestPreparation()->firstOrFail()->review()->firstOrFail();

        $dispatch = $this->dispatches->create(
            $review,
            $request->user(),
            $request->validated('vehicle_id'),
            $request->validated('driver_id'),
        );

        return new DispatchResource($dispatch->load(['order', 'vehicle', 'driver']));
    }

    public function show(Dispatch $dispatch)
    {
        $this->authorize('viewAny', Dispatch::class);

        return new DispatchResource($dispatch->load(['order.customer', 'vehicle', 'driver', 'items.preparationItem.orderItem.product']));
    }
}
