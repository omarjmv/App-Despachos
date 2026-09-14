<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PreparationStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Preparation;
use App\Models\PreparationItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PreparationService
{
    public function startOrResume(Order $order, User $preparer): Preparation
    {
        if ($order->status !== OrderStatus::PREPARANDO) {
            throw new InvalidStateTransitionException(
                "El pedido debe estar en PREPARANDO para iniciar preparación (actual: {$order->status->value})."
            );
        }

        $existing = $order->preparations()
            ->where('status', PreparationStatus::EN_PROCESO)
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($order, $preparer) {
            $preparation = $order->preparations()->create([
                'company_id' => $order->company_id,
                'prepared_by' => $preparer->id,
                'status' => PreparationStatus::EN_PROCESO,
                'started_at' => now(),
            ]);

            foreach ($order->items as $item) {
                PreparationItem::query()->create([
                    'preparation_id' => $preparation->id,
                    'order_item_id' => $item->id,
                    'quantity_prepared' => 0,
                ]);
            }

            return $preparation->load('items.orderItem.product');
        });
    }

    /**
     * Registro manual de cantidad preparada por producto (sección 7).
     */
    public function registerItem(Preparation $preparation, int $orderItemId, float $quantity, ?string $differenceReason, ?string $differenceNotes): PreparationItem
    {
        $this->assertInProcess($preparation);

        if ($quantity < 0) {
            throw ValidationException::withMessages(['quantity_prepared' => 'La cantidad preparada no puede ser negativa.']);
        }

        $preparationItem = $preparation->items()->where('order_item_id', $orderItemId)->firstOrFail();
        $requested = (float) $preparationItem->orderItem->quantity_requested;

        if ($quantity < $requested && empty($differenceReason)) {
            throw ValidationException::withMessages([
                'difference_reason' => 'Debe indicar el motivo de la diferencia cuando lo preparado es menor a lo solicitado.',
            ]);
        }

        $preparationItem->update([
            'quantity_prepared' => $quantity,
            'difference_reason' => $quantity < $requested ? $differenceReason : null,
            'difference_notes' => $differenceNotes,
        ]);

        return $preparationItem->fresh();
    }

    /**
     * Flujo de escáner (sección 8): identifica producto por código y
     * suma 1 a lo preparado, evitando incrementos accidentales por
     * sobre-empaque salvo que la empresa lo permita explícitamente.
     */
    public function registerScan(Preparation $preparation, string $barcode): PreparationItem
    {
        $this->assertInProcess($preparation);

        $product = Product::query()->where('barcode', $barcode)->first();

        if (! $product) {
            throw ValidationException::withMessages(['barcode' => 'No se encontró ningún producto con ese código.']);
        }

        $preparationItem = $preparation->items()
            ->whereHas('orderItem', fn ($q) => $q->where('product_id', $product->id))
            ->first();

        if (! $preparationItem) {
            throw ValidationException::withMessages(['barcode' => 'Ese producto no pertenece a este pedido.']);
        }

        $requested = (float) $preparationItem->orderItem->quantity_requested;
        $newQuantity = (float) $preparationItem->quantity_prepared + 1;
        $allowOverpack = (bool) ($preparation->company->settings['allow_overpack'] ?? false);

        if ($newQuantity > $requested && ! $allowOverpack) {
            throw ValidationException::withMessages([
                'quantity_prepared' => 'La cantidad preparada superaría lo solicitado. Se requiere autorización.',
            ]);
        }

        $preparationItem->update([
            'quantity_prepared' => $newQuantity,
            'difference_reason' => $newQuantity < $requested ? $preparationItem->difference_reason : null,
        ]);

        return $preparationItem->fresh();
    }

    public function finish(Preparation $preparation): Preparation
    {
        $this->assertInProcess($preparation);
        $this->assertAllDifferencesJustified($preparation);

        DB::transaction(function () use ($preparation) {
            $preparation->update([
                'status' => PreparationStatus::FINALIZADA,
                'finished_at' => now(),
            ]);

            $preparation->order->update(['status' => OrderStatus::PREPARADO]);
        });

        AuditLog::record('FINALIZAR_PREPARACION', $preparation, [
            'total_preparado' => $preparation->totalPrepared(),
        ]);

        return $preparation->fresh(['items', 'order']);
    }

    private function assertInProcess(Preparation $preparation): void
    {
        if ($preparation->status !== PreparationStatus::EN_PROCESO) {
            throw new InvalidStateTransitionException('La preparación ya fue finalizada.');
        }
    }

    /**
     * No permite finalizar dejando productos por debajo de lo solicitado
     * sin motivo, aunque el preparador nunca los haya tocado explícitamente
     * (evita que un producto olvidado quede en 0 sin explicación).
     */
    private function assertAllDifferencesJustified(Preparation $preparation): void
    {
        $unjustified = $preparation->items()
            ->with('orderItem')
            ->get()
            ->filter(fn (PreparationItem $item) => (float) $item->quantity_prepared < (float) $item->orderItem->quantity_requested
                && empty($item->difference_reason)
            );

        if ($unjustified->isNotEmpty()) {
            throw ValidationException::withMessages([
                'preparation' => 'Hay productos con cantidad preparada menor a la solicitada sin motivo de diferencia: '
                    .$unjustified->pluck('orderItem.product_id')->implode(', '),
            ]);
        }
    }
}
