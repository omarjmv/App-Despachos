<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Routes\ReorderRouteRequest;
use App\Http\Requests\Routes\StoreRouteRequest;
use App\Http\Resources\RouteResource;
use App\Models\RouteModel;
use App\Services\RouteService;
use Illuminate\Http\Request;

class RouteController extends Controller
{
    public function __construct(private readonly RouteService $routes) {}

    public function index(Request $request)
    {
        $this->authorize('manage', RouteModel::class);

        return RouteResource::collection(
            RouteModel::query()->with(['vehicle', 'driver', 'stops.dispatch.order.customer'])->latest()->paginate(20)
        );
    }

    public function store(StoreRouteRequest $request)
    {
        $route = $this->routes->create(
            $request->validated('vehicle_id'),
            $request->validated('driver_id'),
            $request->validated('dispatch_ids'),
            $request->user(),
        );

        return new RouteResource($route);
    }

    public function show(RouteModel $route)
    {
        $this->authorize('view', $route);

        return new RouteResource($route->load(['vehicle', 'driver', 'stops.dispatch.order.customer', 'stops.delivery']));
    }

    public function reorder(ReorderRouteRequest $request, RouteModel $route)
    {
        $this->authorize('manage', RouteModel::class);

        $route = $this->routes->reorder($route, $request->validated('stop_ids'));

        return new RouteResource($route);
    }

    public function start(RouteModel $route)
    {
        $this->authorize('manage', RouteModel::class);

        return new RouteResource($this->routes->start($route)->load('stops.dispatch.order'));
    }

    public function finish(RouteModel $route)
    {
        $this->authorize('manage', RouteModel::class);

        return new RouteResource($this->routes->finish($route));
    }

    /**
     * La ruta activa del motorista autenticado (Documento 3 §5).
     */
    public function mine(Request $request)
    {
        $route = RouteModel::query()
            ->where('driver_id', $request->user()->id)
            ->whereIn('status', ['PLANIFICADA', 'EN_CURSO'])
            ->with(['vehicle', 'stops.dispatch.order.customer', 'stops.dispatch.items', 'stops.delivery'])
            ->latest()
            ->first();

        if (! $route) {
            return response()->json(['message' => 'No tiene una ruta asignada.'], 404);
        }

        return new RouteResource($route);
    }
}
