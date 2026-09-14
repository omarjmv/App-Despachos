<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PreparationStatus;
use App\Enums\ReviewResult;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\AuditLog;
use App\Models\Preparation;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReviewService
{
    public function approve(Preparation $preparation, User $reviewer): Review
    {
        $order = $this->assertReviewable($preparation);

        return DB::transaction(function () use ($preparation, $reviewer, $order) {
            $order->update(['status' => OrderStatus::EN_REVISION]);

            $review = Review::query()->create([
                'company_id' => $preparation->company_id,
                'preparation_id' => $preparation->id,
                'reviewed_by' => $reviewer->id,
                'result' => ReviewResult::APROBADO,
                'reviewed_at' => now(),
            ]);

            $order->update(['status' => OrderStatus::APROBADO]);

            AuditLog::record('APROBAR_DESPACHO', $order, ['review_id' => $review->id]);

            return $review->load('reviewer');
        });
    }

    public function reject(Preparation $preparation, User $reviewer, string $reason): Review
    {
        $order = $this->assertReviewable($preparation);

        return DB::transaction(function () use ($preparation, $reviewer, $reason, $order) {
            $order->update(['status' => OrderStatus::EN_REVISION]);

            $review = Review::query()->create([
                'company_id' => $preparation->company_id,
                'preparation_id' => $preparation->id,
                'reviewed_by' => $reviewer->id,
                'result' => ReviewResult::RECHAZADO,
                'rejection_reason' => $reason,
                'reviewed_at' => now(),
            ]);

            // Vuelve a PREPARANDO para que el preparador original corrija (decisión menor, Documento 3 §7).
            // Se reabre la MISMA preparación (no se crea una nueva) para conservar lo ya registrado.
            $order->update(['status' => OrderStatus::PREPARANDO]);
            $preparation->update(['status' => PreparationStatus::EN_PROCESO, 'finished_at' => null]);

            AuditLog::record('RECHAZAR_REVISION', $order, ['review_id' => $review->id, 'reason' => $reason]);

            return $review->load('reviewer');
        });
    }

    private function assertReviewable(Preparation $preparation): \App\Models\Order
    {
        $order = $preparation->order;

        if ($order->status !== OrderStatus::PREPARADO) {
            throw new InvalidStateTransitionException(
                "El pedido debe estar PREPARADO para revisarse (actual: {$order->status->value})."
            );
        }

        return $order;
    }
}
