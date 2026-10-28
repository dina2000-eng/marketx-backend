<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\Payments\PaymentService;
use App\Services\Marketplace\VendorEarningService;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $orders = Order::with([
            'items.variant.product.store',
            'vendorOrders.store',
        ])
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        return response()->json([
            'orders' => $orders,
        ]);
    }

    public function show(
        Request $request,
        Order $order
    ) {
        $user = $request->user();

        if ((int) $order->user_id !== (int) $user->id) {
            return response()->json([
                'message' => 'غير مصرح لك بعرض هذا الطلب.',
            ], 403);
        }

        return response()->json([
            'order' => $order->load([
                'items.variant.product.store',
                'vendorOrders.store',
            ]),
        ]);
    }

    public function cancel(
    Request $request,
    Order $order,
    PaymentService $paymentService,
    VendorEarningService $earningService
) {
    $user = $request->user();

    if (
        (int) $order->user_id !==
        (int) $user->id
    ) {
        return response()->json([
            'message' =>
                'غير مصرح لك بإلغاء هذا الطلب.',
        ], 403);
    }

    try {
        DB::transaction(function () use (
            $order,
            $user,
            $paymentService,
            $earningService
        ) {
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->where(
                    'user_id',
                    $user->id
                )
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $lockedOrder->status !==
                'pending'
            ) {
                throw new \RuntimeException(
                    'ORDER_CANNOT_BE_CANCELLED'
                );
            }

            $lockedOrder->load([
                'vendorOrders.items',
                'items',
            ]);

            foreach (
                $lockedOrder->vendorOrders
                as $vendorOrder
            ) {
                if (
                    $vendorOrder->status !==
                    'pending' &&
                    $vendorOrder->status !==
                    'cancelled'
                ) {
                    throw new \RuntimeException(
                        'ORDER_CANNOT_BE_CANCELLED'
                    );
                }
            }

            $payment =
                $lockedOrder
                    ->payments()
                    ->latest('id')
                    ->lockForUpdate()
                    ->first();

            /*
             * ONLINE PAYMENT
             */
            if (
                $lockedOrder->payment_method ===
                'online'
            ) {
                /*
                 * Payment still pending:
                 * release reserved stock +
                 * reverse pending seller earning.
                 */
                if (
                    $payment &&
                    $payment->status ===
                    'pending'
                ) {
                    $paymentService->expire(
                        $payment
                    );

                    return;
                }

                /*
                 * Payment already succeeded:
                 * refund handles:
                 * - inventory restore
                 * - seller earning reversal
                 * - vendor order cancellation
                 * - parent order cancellation
                 */
                if (
                    $payment &&
                    $payment->status ===
                    'paid'
                ) {
                    $paymentService
                        ->refundPayment(
                            $payment
                        );

                    return;
                }

                /*
                 * Failed/cancelled online payment:
                 * stock was never sold.
                 * Only reverse seller earnings
                 * and cancel order records.
                 */
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

                    $earningService
                        ->refundVendorOrder(
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

                    'payment_status' =>
                        $payment?->status
                        ?? $lockedOrder
                            ->payment_status,
                ]);

                return;
            }

            /*
             * COD
             *
             * Checkout already deducted
             * physical stock, so restore it.
             */
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
                                $item
                                    ->product_variant_id
                            )
                            ->where(
                                'store_id',
                                $vendorOrder
                                    ->store_id
                            )
                            ->lockForUpdate()
                            ->first();

                    if ($inventory) {
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
                                'Customer cancelled order #' .
                                $lockedOrder->id,
                        ]);

                        $inventory->refresh();

                        ProductVariant::query()
                            ->whereKey(
                                $item
                                    ->product_variant_id
                            )
                            ->update([
                                'stock' =>
                                    $inventory
                                        ->quantity,
                            ]);
                    } else {
                        /*
                         * Legacy fallback.
                         */
                        $variant =
                            ProductVariant::query()
                                ->whereKey(
                                    $item
                                        ->product_variant_id
                                )
                                ->lockForUpdate()
                                ->first();

                        if ($variant) {
                            $variant->increment(
                                'stock',
                                $item->quantity
                            );
                        }
                    }
                }

                /*
                 * COD earning was never funded.
                 */
                $earningService
                    ->refundVendorOrder(
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
        });

        return response()->json([
            'message' =>
                'تم إلغاء الطلب بنجاح.',

            'order' =>
                $order->fresh([
                    'items.variant.product.store',
                    'vendorOrders.store',
                    'payments',
                ]),
        ]);

    } catch (\RuntimeException $e) {
        if (
            $e->getMessage() ===
            'ORDER_CANNOT_BE_CANCELLED'
        ) {
            return response()->json([
                'message' =>
                    'لا يمكن إلغاء الطلب بعد بدء البائع بمعالجته.',
            ], 400);
        }

        throw $e;
    }
}
}