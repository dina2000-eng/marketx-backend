<?php

namespace App\Services\Orders;

use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Marketplace\VendorEarningService;
use App\Services\Payments\PaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminOrderService
{
    public function updateStatus(
        Order $order,
        string $newStatus,
        PaymentService $paymentService,
        VendorEarningService $earningService
    ): Order {
        $payment = Payment::query()
            ->where('order_id', $order->id)
            ->latest('id')
            ->first();

        if (
            $newStatus === 'cancelled' &&
            $payment &&
            $payment->method === 'online' &&
            $payment->status === 'paid'
        ) {
            throw ValidationException::withMessages([
                'status' => 'يجب استرداد الدفعة الإلكترونية أولاً قبل إلغاء الطلب.',
            ]);
        }

        if (
            $newStatus === 'cancelled' &&
            $payment &&
            $payment->method === 'online' &&
            $payment->status === 'pending'
        ) {
            $paymentService->expire($payment);

            return $order->fresh([
                'user',
                'items.product',
                'items.store',
                'items.variant',
                'vendorOrders.store',
            ]);
        }

        if (
            $newStatus === 'delivered' &&
            $payment &&
            $payment->method === 'online' &&
            $payment->status !== 'paid'
        ) {
            throw ValidationException::withMessages([
                'status' => 'لا يمكن تسليم طلب إلكتروني قبل تأكيد الدفع.',
            ]);
        }

        DB::transaction(function () use (
            $order,
            $newStatus,
            $earningService
        ) {
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $oldStatus = $lockedOrder->status;

            if ($oldStatus === $newStatus) {
                return;
            }

            if ($oldStatus === 'cancelled') {
                throw ValidationException::withMessages([
                    'status' => 'الطلب ملغي بالفعل.',
                ]);
            }

            if (
                $oldStatus === 'delivered' &&
                $newStatus !== 'delivered'
            ) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن تغيير حالة طلب تم تسليمه.',
                ]);
            }

            if ($newStatus === 'cancelled') {
                $lockedOrder->load([
                    'vendorOrders.items',
                ]);

                foreach ($lockedOrder->vendorOrders as $vendorOrder) {
                    if ($vendorOrder->status === 'cancelled') {
                        continue;
                    }

                    if ($lockedOrder->payment_method === 'cod') {
                        foreach ($vendorOrder->items as $item) {
                            if (! $item->product_variant_id) {
                                continue;
                            }

                            $inventory = Inventory::query()
                                ->where(
                                    'product_variant_id',
                                    $item->product_variant_id
                                )
                                ->where(
                                    'store_id',
                                    $vendorOrder->store_id
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
                                'inventory_id' => $inventory->id,
                                'type' => 'return',
                                'quantity' => $item->quantity,
                                'note' => 'Admin cancelled order #'.$lockedOrder->id,
                            ]);

                            $inventory->refresh();

                            $inventory->variant()->update([
                                'stock' => $inventory->quantity,
                            ]);
                        }
                    }

                    $earningService->reverse($vendorOrder);

                    $vendorOrder->update([
                        'status' => 'cancelled',
                    ]);
                }

                $lockedOrder->update([
                    'status' => 'cancelled',
                ]);

                return;
            }

            $lockedOrder->vendorOrders()
                ->where('status', '!=', 'cancelled')
                ->update([
                    'status' => $newStatus,
                ]);

            $lockedOrder->update([
                'status' => $newStatus,
            ]);
        });

        return $order->fresh([
            'user',
            'items.product',
            'items.store',
            'items.variant',
            'vendorOrders.store',
        ]);
    }
}
