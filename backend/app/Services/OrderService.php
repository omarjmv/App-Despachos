<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Support\SequenceGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    /**
     * @param  array{customer_id:int, order_date:string, required_date?:string, notes?:string, items:array}  $data
     */
    public function create(array $data, User $creator): Order
    {
        $customer = Customer::query()->findOrFail($data['customer_id']);

        if (empty($data['items'])) {
            throw ValidationException::withMessages(['items' => 'El pedido debe tener al menos un producto.']);
        }

        return DB::transaction(function () use ($data, $creator, $customer) {
            $order = Order::query()->create([
                'company_id' => $creator->company_id,
                'number' => SequenceGenerator::next('orders', 'number', $creator->company_id, 'PED'),
                'customer_id' => $customer->id,
                'created_by' => $creator->id,
                'order_date' => $data['order_date'],
                'required_date' => $data['required_date'] ?? null,
                'status' => OrderStatus::PENDIENTE,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $order->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity_requested' => $item['quantity_requested'],
                    'unit' => $item['unit'],
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            return $order->load('items');
        });
    }

    public function assign(Order $order, User $preparer): Order
    {
        $this->assertTransition($order, OrderStatus::PREPARANDO);

        $order->update([
            'status' => OrderStatus::PREPARANDO,
            'assigned_to' => $preparer->id,
        ]);

        AuditLog::record('ASIGNAR_PEDIDO', $order, ['assigned_to' => $preparer->id]);

        return $order;
    }

    public function cancel(Order $order, string $reason): Order
    {
        $this->assertTransition($order, OrderStatus::CANCELADO);

        $order->update([
            'status' => OrderStatus::CANCELADO,
            'cancellation_reason' => $reason,
        ]);

        AuditLog::record('CANCELAR_PEDIDO', $order, ['reason' => $reason]);

        return $order;
    }

    private function assertTransition(Order $order, OrderStatus $target): void
    {
        if (! $order->status->canTransitionTo($target)) {
            throw new InvalidStateTransitionException(
                "No se puede pasar el pedido de {$order->status->value} a {$target->value}."
            );
        }
    }
}
