<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Preparation\RegisterPreparationItemRequest;
use App\Http\Requests\Preparation\ScanPreparationRequest;
use App\Http\Resources\PreparationResource;
use App\Models\Order;
use App\Services\PreparationService;
use Illuminate\Http\Request;

class PreparationController extends Controller
{
    public function __construct(private readonly PreparationService $preparations) {}

    public function show(Request $request, Order $order)
    {
        $this->authorize('view', $order);

        $preparation = $this->preparations->startOrResume($order, $request->user());
        $this->authorize('execute', $preparation);

        // Aunque internamente cree el registro la primera vez, de cara al
        // cliente esto es una lectura: siempre 200, nunca el 201 implícito
        // que Laravel aplicaría por wasRecentlyCreated.
        return (new PreparationResource($preparation->load('items.orderItem.product', 'preparedBy')))
            ->response()
            ->setStatusCode(200);
    }

    public function storeItem(RegisterPreparationItemRequest $request, Order $order)
    {
        $preparation = $order->preparations()->where('status', 'EN_PROCESO')->firstOrFail();
        $this->authorize('execute', $preparation);

        $item = $this->preparations->registerItem(
            $preparation,
            $request->validated('order_item_id'),
            (float) $request->validated('quantity_prepared'),
            $request->validated('difference_reason'),
            $request->validated('difference_notes'),
        );

        return new \App\Http\Resources\PreparationItemResource($item->load('orderItem.product'));
    }

    public function scan(ScanPreparationRequest $request, Order $order)
    {
        $preparation = $order->preparations()->where('status', 'EN_PROCESO')->firstOrFail();
        $this->authorize('execute', $preparation);

        $item = $this->preparations->registerScan($preparation, $request->validated('barcode'));

        return new \App\Http\Resources\PreparationItemResource($item->load('orderItem.product'));
    }

    public function finish(Request $request, Order $order)
    {
        $preparation = $order->preparations()->where('status', 'EN_PROCESO')->firstOrFail();
        $this->authorize('execute', $preparation);

        $preparation = $this->preparations->finish($preparation);

        return new PreparationResource($preparation->load('items.orderItem.product'));
    }
}
