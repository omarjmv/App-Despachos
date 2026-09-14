<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function summary(Request $request)
    {
        $today = now()->toDateString();

        $counts = Order::query()
            ->whereDate('order_date', $today)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $byStatus = fn (OrderStatus $status) => (int) ($counts[$status->value] ?? 0);

        return response()->json([
            'pedidos_hoy' => Order::query()->whereDate('order_date', $today)->count(),
            'preparados' => $byStatus(OrderStatus::PREPARADO),
            'en_revision' => $byStatus(OrderStatus::EN_REVISION),
            'despachados' => $byStatus(OrderStatus::DESPACHADO),
            'en_ruta' => $byStatus(OrderStatus::EN_RUTA),
            'entregados' => $byStatus(OrderStatus::ENTREGADO),
            'parciales' => $byStatus(OrderStatus::PARCIAL),
            'cancelados' => $byStatus(OrderStatus::CANCELADO),
        ]);
    }

    public function charts(Request $request)
    {
        $ordersByDay = Order::query()
            ->selectRaw('order_date, count(*) as total')
            ->where('order_date', '>=', now()->subDays(30))
            ->groupBy('order_date')
            ->orderBy('order_date')
            ->get();

        $ordersByCustomer = Order::query()
            ->selectRaw('customer_id, count(*) as total')
            ->groupBy('customer_id')
            ->with('customer:id,name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return response()->json([
            'pedidos_por_dia' => $ordersByDay,
            'pedidos_por_cliente' => $ordersByCustomer,
        ]);
    }
}
