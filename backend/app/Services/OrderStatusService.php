<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Encodes the order status state machine (docs/04-FEATURES-BY-PHASE.md,
 * docs/06-UX-FLOWS.md) plus two decisions made for Milestone 4:
 * cancellation is allowed from any non-terminal state, and a COD order is
 * auto-marked paid the moment it's delivered (cash changes hands then).
 */
class OrderStatusService
{
    private const TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['preparing', 'cancelled'],
        'preparing' => ['out_for_delivery', 'cancelled'],
        'out_for_delivery' => ['delivered', 'cancelled'],
        'delivered' => [],
        'cancelled' => [],
    ];

    public function transition(Order $order, string $toStatus, User $changedBy): Order
    {
        $fromStatus = $order->status;

        if (! in_array($toStatus, self::TRANSITIONS[$fromStatus] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => "Cannot move an order from \"{$fromStatus}\" to \"{$toStatus}\".",
            ]);
        }

        return DB::transaction(function () use ($order, $fromStatus, $toStatus, $changedBy) {
            $order->status = $toStatus;

            if ($toStatus === 'delivered' && $order->payment_method === 'cod') {
                $order->payment_status = 'paid';
            }

            $order->save();

            $order->statusHistories()->create([
                'store_id' => $order->store_id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'changed_by' => $changedBy->id,
            ]);

            return $order;
        });
    }
}
