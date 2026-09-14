<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function orders(Request $request)
    {
        $query = Order::query()->with('customer');

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->integer('customer_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('order_date', '>=', $request->date('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('order_date', '<=', $request->date('date_to'));
        }

        return \App\Http\Resources\OrderResource::collection($query->latest()->paginate(50));
    }

    /**
     * Solicitado vs preparado vs despachado por producto (sección 17).
     */
    public function products(Request $request)
    {
        $rows = OrderItem::query()
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('preparation_items', 'preparation_items.order_item_id', '=', 'order_items.id')
            ->leftJoin('dispatch_items', 'dispatch_items.preparation_item_id', '=', 'preparation_items.id')
            ->select('products.id', 'products.name', 'products.sku')
            ->selectRaw('SUM(order_items.quantity_requested) as solicitado')
            ->selectRaw('COALESCE(SUM(preparation_items.quantity_prepared), 0) as preparado')
            ->selectRaw('COALESCE(SUM(dispatch_items.quantity_dispatched), 0) as despachado')
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->get();

        return response()->json(['data' => $rows]);
    }
}
