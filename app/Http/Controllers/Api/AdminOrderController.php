<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Marketplace\VendorEarningService;
use App\Services\Payments\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminOrderController extends Controller
{
    public function index(Request $request)
    {
        $admin = $request->user();

        if (
            ! $admin->isPlatformAdmin() &&
            $admin->role !== 'tenant_admin'
        ) {
            return response()->json([
                'message' =>
                    'غير مصرح لك بعرض الطلبات.',
            ], 403);
        }

        $query = Order::with([
            'user',
            'items.product',
            'items.store',
            'items.variant',
            'vendorOrders.store',
        ])->latest();

        if (
            $admin->role === 'tenant_admin'
        ) {
            $query->where(
                'tenant_id',
                $admin->tenant_id
            );
        }

        $orders = $query->get();

        /*
         * Latest payment for each order.
         */
        $payments = Payment::query()
            ->whereIn(
                'order_id',
                $orders->pluck('id')
            )
            ->orderByDesc('id')
            ->get()
            ->unique('order_id')
            ->keyBy('order_id');

        foreach ($orders as $order) {
            $payment =
                $payments->get(
                    $order->id
                );

            $order->setAttribute(
                'payment',
                $payment
                    ? [
                        'id' =>
                            $payment->id,

                        'method' =>
                            $payment->method,

                        'status' =>
                            $payment->status,

                        'gateway' =>
                            $payment->gateway,

                        'transaction_id' =>
                            $payment->transaction_id,

                        'amount' =>
                            $payment->amount,

                        'currency' =>
                            $payment->currency,

                        'paid_at' =>
                            $payment->paid_at,

                        'refunded_at' =>
                            $payment->refunded_at,
                    ]
                    : null
            );
        }

        return response()->json([
            'orders' =>
                $orders,
        ]);
    }

    public function updateStatus(
        Request $request,
        Order $order,
        PaymentService $paymentService,
        VendorEarningService $earningService
    ) {
        $admin = $request->user();

        if (
            ! $admin->isPlatformAdmin() &&
            $admin->role !== 'tenant_admin'
        ) {
            return response()->json([
                'message' =>
                    'غير مصرح لك بتعديل الطلبات.',
            ], 403);
        }

        if (
            $admin->role === 'tenant_admin' &&
            (int) $order->tenant_id !==
            (int) $admin->tenant_id
        ) {
            return response()->json([
                'message' =>
                    'هذا الطلب لا يتبع للـTenant الخاص بك.',
            ], 403);
        }

        $data = $request->validate([
            'status' => [
                'required',
                'in:pending,confirmed,processing,shipped,delivered,cancelled',
            ],
        ]);

        $newStatus =
            $data['status'];

        /*
         * Get latest payment.
         */
        $payment = Payment::query()
            ->where(
                'order_id',
                $order->id
            )
            ->latest('id')
            ->first();

        /*
         * Paid online order:
         * refund must happen first.
         */
        if (
            $newStatus === 'cancelled' &&
            $payment &&
            $payment->method === 'online' &&
            $payment->status === 'paid'
        ) {
            throw ValidationException::withMessages([
                'status' =>
                    'يجب استرداد الدفعة الإلكترونية أولاً قبل إلغاء الطلب.',
            ]);
        }

        /*
         * Online pending payment:
         * use PaymentService so reserved
         * inventory is released correctly.
         */
        if (
            $newStatus === 'cancelled' &&
            $payment &&
            $payment->method === 'online' &&
            $payment->status === 'pending'
        ) {
            $paymentService->expire(
                $payment
            );

            return response()->json([
                'message' =>
                    'تم إلغاء الطلب وفك حجز المخزون.',

                'order' =>
                    $order->fresh([
                        'user',
                        'items.product',
                        'items.store',
                        'items.variant',
                        'vendorOrders.store',
                    ]),
            ]);
        }

        /*
         * Online order cannot be delivered
         * before payment succeeds.
         */
        if (
            $newStatus === 'delivered' &&
            $payment &&
            $payment->method === 'online' &&
            $payment->status !== 'paid'
        ) {
            throw ValidationException::withMessages([
                'status' =>
                    'لا يمكن تسليم طلب إلكتروني قبل تأكيد الدفع.',
            ]);
        }

        DB::transaction(function () use (
            $order,
            $data,
            $earningService
        ) {
            $lockedOrder =
                Order::query()
                    ->whereKey(
                        $order->id
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

            $oldStatus =
                $lockedOrder->status;

            $newStatus =
                $data['status'];

            if (
                $oldStatus ===
                $newStatus
            ) {
                return;
            }

            /*
             * Terminal statuses.
             */
            if (
                $oldStatus ===
                'cancelled'
            ) {
                throw ValidationException::withMessages([
                    'status' =>
                        'الطلب ملغي بالفعل.',
                ]);
            }

            if (
                $oldStatus ===
                    'delivered' &&
                $newStatus !==
                    'delivered'
            ) {
                throw ValidationException::withMessages([
                    'status' =>
                        'لا يمكن تغيير حالة طلب تم تسليمه.',
                ]);
            }

            /*
             * Whole-order cancellation.
             *
             * This path is mainly for COD
             * or already-failed/cancelled
             * online payments.
             */
            if (
                $newStatus ===
                'cancelled'
            ) {
                $lockedOrder->load([
                    'vendorOrders.items',
                ]);

                foreach (
                    $lockedOrder->vendorOrders
                    as $vendorOrder
                ) {
                    if (
                        $vendorOrder->status ===
                        'cancelled'
                    ) {
                        continue;
                    }

                    /*
                     * COD inventory was already
                     * deducted at checkout.
                     */
                    if (
                        $lockedOrder
                            ->payment_method ===
                        'cod'
                    ) {
                        foreach (
                            $vendorOrder->items
                            as $item
                        ) {
                            if (
                                ! $item
                                    ->product_variant_id
                            ) {
                                continue;
                            }

                            $inventory =
                                Inventory::query()
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
                                'inventory_id' =>
                                    $inventory->id,

                                'type' =>
                                    'return',

                                'quantity' =>
                                    $item->quantity,

                                'note' =>
                                    'Admin cancelled order #' .
                                    $lockedOrder->id,
                            ]);

                            $inventory->refresh();

                            $inventory
                                ->variant()
                                ->update([
                                    'stock' =>
                                        $inventory
                                            ->quantity,
                                ]);
                        }
                    }

                    /*
                     * Reverse seller earning.
                     */
                    $earningService->reverse(
                        $vendorOrder
                    );

                    $vendorOrder->update([
                        'status' =>
                            'cancelled',
                    ]);
                }

                $lockedOrder->update([
                    'status' =>
                        'cancelled',
                ]);

                return;
            }

            /*
             * Synchronize active vendor orders.
             */
            $lockedOrder
                ->vendorOrders()
                ->where(
                    'status',
                    '!=',
                    'cancelled'
                )
                ->update([
                    'status' =>
                        $newStatus,
                ]);

            $lockedOrder->update([
                'status' =>
                    $newStatus,
            ]);
        });

        return response()->json([
            'message' =>
                'تم تحديث حالة الطلب.',

            'order' =>
                $order->fresh([
                    'user',
                    'items.product',
                    'items.store',
                    'items.variant',
                    'vendorOrders.store',
                ]),
        ]);
    }
}