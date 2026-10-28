<?php

namespace App\Services\Payments;

use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\Marketplace\VendorEarningService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PaymentService
{
    public function __construct(
        private readonly VendorEarningService $earningService
    ) {
    }

    /**
     * Mark online payment as paid.
     */
    public function markPaid(
        Payment $payment,
        ?string $transactionId = null
    ): Payment {
        return DB::transaction(function () use (
            $payment,
            $transactionId
        ) {
            $payment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * Idempotency.
             */
            if ($payment->status === 'paid') {
                return $payment;
            }

            if ($payment->status === 'refunded') {
                throw new RuntimeException(
                    'Refunded payment cannot be marked as paid.'
                );
            }

            $order = $payment->order()
                ->with([
                    'items.variant',
                    'vendorOrders',
                ])
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * Convert reserved stock
             * into actual sold stock.
             */
            foreach ($order->items as $item) {
                $inventory = Inventory::query()
                    ->where(
                        'product_variant_id',
                        $item->product_variant_id
                    )
                    ->lockForUpdate()
                    ->first();

                if (! $inventory) {
                    throw new RuntimeException(
                        'Inventory record missing for order item #' .
                        $item->id
                    );
                }

                if (
                    $inventory->reserved_quantity <
                    $item->quantity
                ) {
                    throw new RuntimeException(
                        'Reserved inventory is insufficient for order item #' .
                        $item->id
                    );
                }

                /*
                 * Remove reservation.
                 */
                $inventory->decrement(
                    'reserved_quantity',
                    $item->quantity
                );

                /*
                 * Deduct actual quantity.
                 */
                $inventory->decrement(
                    'quantity',
                    $item->quantity
                );

                InventoryTransaction::create([
                    'inventory_id' =>
                        $inventory->id,

                    'type' =>
                        'sale',

                    'quantity' =>
                        -$item->quantity,

                    'note' =>
                        'Online payment order #' .
                        $order->id,
                ]);

                $inventory->refresh();

                /*
                 * Keep legacy variant stock synced.
                 */
                if ($item->variant) {
                    $item->variant->update([
                        'stock' =>
                            $inventory->quantity,
                    ]);
                }
            }

            /*
             * Original payment transaction.
             */
            $payment->update([
                'status' =>
                    'paid',

                'transaction_id' =>
                    $transactionId
                    ?? $payment->transaction_id,

                'paid_at' =>
                    now(),

                'failed_at' =>
                    null,
            ]);

            $order->update([
                'payment_status' =>
                    'paid',
            ]);

            /*
             * Vendo received the money.
             *
             * Seller earning:
             * pending -> funded
             */
            $this->earningService
                ->fundOrder(
                    $order
                );

            return $payment->fresh();
        });
    }

    /**
     * Mark online payment as failed
     * and release reserved stock.
     */
    public function markFailed(
        Payment $payment
    ): Payment {
        return DB::transaction(function () use (
            $payment
        ) {
            $payment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * Never release inventory twice.
             */
            if (
                in_array(
                    $payment->status,
                    [
                        'failed',
                        'cancelled',
                    ],
                    true
                )
            ) {
                return $payment;
            }

            if ($payment->status === 'paid') {
                throw new RuntimeException(
                    'Paid payment cannot be marked as failed.'
                );
            }

            if ($payment->status === 'refunded') {
                throw new RuntimeException(
                    'Refunded payment cannot be marked as failed.'
                );
            }

            $order = $payment->order()
                ->with('items')
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * Release reserved stock.
             */
            foreach ($order->items as $item) {
                $inventory = Inventory::query()
                    ->where(
                        'product_variant_id',
                        $item->product_variant_id
                    )
                    ->lockForUpdate()
                    ->first();

                if (! $inventory) {
                    continue;
                }

                $releaseQuantity = min(
                    (int)
                    $inventory->reserved_quantity,

                    (int)
                    $item->quantity
                );

                if ($releaseQuantity > 0) {
                    $inventory->decrement(
                        'reserved_quantity',
                        $releaseQuantity
                    );
                }
            }

            $payment->update([
                'status' =>
                    'failed',

                'failed_at' =>
                    now(),
            ]);

            $order->update([
                'payment_status' =>
                    'failed',
            ]);

            return $payment->fresh();
        });
    }

    /**
     * Full online payment refund.
     *
     * The original payment transaction_id
     * is NEVER changed here.
     *
     * Refund information is stored
     * inside the refunds table.
     */
    public function refundPayment(
        Payment $payment,
        ?string $gatewayRefundReference = null,
        ?string $reason = null
    ): Payment {
        return DB::transaction(function () use (
            $payment,
            $gatewayRefundReference,
            $reason
        ) {
            $payment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * Never refund twice.
             */
            if ($payment->status === 'refunded') {
                return $payment;
            }

            if ($payment->status !== 'paid') {
                throw new RuntimeException(
                    'Only paid payments can be refunded.'
                );
            }

            $order = $payment->order()
                ->with([
                    'items.variant',
                    'vendorOrders',
                ])
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * Create refund record.
             *
             * During a real gateway integration,
             * the gateway refund API will provide
             * gateway_refund_reference.
             */
            $refund = Refund::create([
                'payment_id' =>
                    $payment->id,

                'order_id' =>
                    $order->id,

                'user_id' =>
                    $payment->user_id,

                'amount' =>
                    $payment->amount,

                'currency' =>
                    $payment->currency,

                'status' =>
                    'processing',

                'gateway' =>
                    $payment->gateway,

                'gateway_refund_reference' =>
                    $gatewayRefundReference,

                'reason' =>
                    $reason,

                'metadata' =>
                    null,
            ]);

            /*
             * Reverse seller earnings first.
             *
             * If seller money cannot be
             * returned, refund is blocked.
             */
            foreach (
                $order->vendorOrders
                as $vendorOrder
            ) {
                $this->earningService
                    ->refundVendorOrder(
                        $vendorOrder
                    );
            }

            /*
             * Restore inventory.
             */
            foreach ($order->items as $item) {
                $inventory = Inventory::query()
                    ->where(
                        'product_variant_id',
                        $item->product_variant_id
                    )
                    ->lockForUpdate()
                    ->first();

                if (! $inventory) {
                    continue;
                }

                $inventory->increment(
                    'quantity',
                    $item->quantity
                );

                InventoryTransaction::create([
                    'inventory_id' =>
                        $inventory->id,

                    'type' =>
                        'return',

                    'quantity' =>
                        $item->quantity,

                    'note' =>
                        'Refunded online order #' .
                        $order->id,
                ]);

                $inventory->refresh();

                if ($item->variant) {
                    $item->variant->update([
                        'stock' =>
                            $inventory->quantity,
                    ]);
                }
            }

            /*
             * Cancel vendor orders.
             */
            foreach (
                $order->vendorOrders
                as $vendorOrder
            ) {
                $vendorOrder->update([
                    'status' =>
                        'cancelled',
                ]);
            }

            /*
             * Mark refund processed.
             */
            $refund->update([
                'status' =>
                    'processed',

                'processed_at' =>
                    now(),
            ]);

            /*
             * IMPORTANT:
             *
             * transaction_id stays untouched.
             *
             * It remains the reference of the
             * ORIGINAL payment transaction.
             */
            $payment->update([
                'status' =>
                    'refunded',

                'refunded_at' =>
                    now(),
            ]);

            /*
             * Parent order.
             */
            $order->update([
                'payment_status' =>
                    'refunded',

                'status' =>
                    'cancelled',
            ]);

            return $payment->fresh();
        });
    }

    /**
     * Expire pending online payment
     * and release reserved inventory.
     */
    public function expire(
        Payment $payment
    ): Payment {
        return DB::transaction(function () use (
            $payment
        ) {
            $payment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->status !== 'pending') {
                return $payment;
            }

            $order = $payment->order()
                ->with([
                    'items',
                    'vendorOrders',
                ])
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * Release reserved stock.
             */
            foreach ($order->items as $item) {
                $inventory = Inventory::query()
                    ->where(
                        'product_variant_id',
                        $item->product_variant_id
                    )
                    ->lockForUpdate()
                    ->first();

                if (! $inventory) {
                    continue;
                }

                $releaseQuantity = min(
                    (int)
                    $inventory->reserved_quantity,

                    (int)
                    $item->quantity
                );

                if ($releaseQuantity > 0) {
                    $inventory->decrement(
                        'reserved_quantity',
                        $releaseQuantity
                    );
                }
            }

            $payment->update([
                'status' =>
                    'cancelled',
            ]);

            $order->update([
                'payment_status' =>
                    'cancelled',

                'status' =>
                    'cancelled',
            ]);

            /*
             * Pending payment never received money,
             * so this is NOT a financial refund.
             *
             * We only reverse the pending earning.
             */
            foreach (
                $order->vendorOrders
                as $vendorOrder
            ) {
                $this->earningService
                    ->reverse(
                        $vendorOrder
                    );

                $vendorOrder->update([
                    'status' =>
                        'cancelled',
                ]);
            }

            return $payment->fresh();
        });
    }
}