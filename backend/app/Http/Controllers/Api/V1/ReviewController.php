<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Review\RejectReviewRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ReviewResource;
use App\Models\Order;
use App\Services\ReviewService;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(private readonly ReviewService $reviews) {}

    public function pending(Request $request)
    {
        $this->authorize('execute', \App\Models\Review::class);

        $orders = Order::query()
            ->where('status', OrderStatus::PREPARADO)
            ->with(['customer', 'latestPreparation.items.orderItem.product'])
            ->latest()
            ->paginate(20);

        return OrderResource::collection($orders);
    }

    public function approve(Request $request, Order $order)
    {
        $this->authorize('execute', \App\Models\Review::class);

        $preparation = $order->latestPreparation()->firstOrFail();
        $review = $this->reviews->approve($preparation, $request->user());

        return new ReviewResource($review);
    }

    public function reject(RejectReviewRequest $request, Order $order)
    {
        $this->authorize('execute', \App\Models\Review::class);

        $preparation = $order->latestPreparation()->firstOrFail();
        $review = $this->reviews->reject($preparation, $request->user(), $request->validated('reason'));

        return new ReviewResource($review);
    }
}
