<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Orders\AssignOrderRequest;
use App\Http\Requests\Orders\CancelOrderRequest;
use App\Http\Requests\Orders\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $query = Order::query()->with(['customer', 'creator', 'assignee'])->latest();

        // El preparador solo ve lo asignado a él (Documento 3 §3).
        if ($user->hasRole(Role::PREPARADOR)) {
            $query->where('assigned_to', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->integer('customer_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('order_date', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('order_date', '<=', $request->date('date_to'));
        }

        return OrderResource::collection($query->paginate(20));
    }

    public function store(StoreOrderRequest $request)
    {
        $order = $this->orders->create($request->validated(), $request->user());

        return new OrderResource($order->load(['customer', 'creator', 'items.product']));
    }

    public function show(Request $request, Order $order)
    {
        $this->authorize('view', $order);

        return new OrderResource($order->load(['customer', 'creator', 'assignee', 'items.product']));
    }

    public function update(StoreOrderRequest $request, Order $order)
    {
        $this->authorize('update', $order);

        $order->update($request->safe()->except('items'));

        $order->items()->delete();
        foreach ($request->validated('items') as $item) {
            $order->items()->create($item);
        }

        return new OrderResource($order->load(['customer', 'items.product']));
    }

    public function assign(AssignOrderRequest $request, Order $order)
    {
        $preparer = User::query()->findOrFail($request->validated('preparer_id'));

        $order = $this->orders->assign($order, $preparer);

        return new OrderResource($order->load(['customer', 'assignee']));
    }

    public function cancel(CancelOrderRequest $request, Order $order)
    {
        $order = $this->orders->cancel($order, $request->validated('reason'));

        return new OrderResource($order);
    }

    /**
     * Línea de tiempo completa del pedido (Documento 3 §6).
     */
    public function trace(Request $request, Order $order)
    {
        $this->authorize('view', $order);

        $order->load([
            'customer', 'creator', 'assignee', 'items.product',
            'preparations.preparedBy', 'preparations.items.orderItem',
            'preparations.review.reviewer',
            'preparations.review.dispatch.vehicle',
            'preparations.review.dispatch.driver',
        ]);

        $auditLogs = AuditLog::query()
            ->where('auditable_type', $order->getMorphClass())
            ->where('auditable_id', $order->id)
            ->with('user')
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'order' => new OrderResource($order),
            'audit_trail' => \App\Http\Resources\AuditLogResource::collection($auditLogs),
        ]);
    }
}
