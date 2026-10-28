<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\VendorOrder;
use App\Notifications\OrderStatusNotification;
use App\Services\Marketplace\VendorEarningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class VendorOrderController extends Controller
{
    /**
     * Vendor orders list.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $stores = $user
            ->ownedStores()
            ->pluck('id');

        if ($stores->isEmpty()) {
            return response()->json([
                'message' =>
                    'لا يوجد متجر لهذا البائع.',
            ], 404);
        }

        $orders = VendorOrder::with([
            'order.user',
            'store',
            'items.orderItem',
            'items.variant.product',
            'earning',
        ])
            ->whereIn(
                'store_id',
                $stores
            )
            ->latest()
            ->get();

        return response()->json([
            'store' => $stores,
            'vendor_orders' => $orders,
        ]);
    }

    /**
     * Update vendor order status.
     */
    public function updateStatus(
        Request $request,
        VendorOrder $vendorOrder,
        VendorEarningService $earningService
    ) {
        $user = $request->user();

        /*
         * Vendor must own the store.
         */
        $allowed = $user
            ->ownedStores()
            ->where(
                'id',
                $vendorOrder->store_id
            )
            ->exists();

        if (! $allowed) {
            return response()->json([
                'message' =>
                    'غير مصرح لك بتعديل هذا الطلب.',
            ], 403);
        }

        $data = $request->validate([
            'status' => [
                'required',
                'in:pending,confirmed,processing,shipped,delivered,cancelled',
            ],
        ]);

        DB::transaction(
            function () use (
                $vendorOrder,
                $data,
                $earningService
            ) {
                /*
                 * Lock vendor order.
                 */
                $lockedVendorOrder =
                    VendorOrder::query()
                        ->whereKey(
                            $vendorOrder->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $oldStatus =
                    $lockedVendorOrder->status;

                $newStatus =
                    $data['status'];

                /*
                 * Same status = no action.
                 */
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
                    $oldStatus === 'cancelled' ||
                    $oldStatus === 'delivered'
                ) {
                    throw ValidationException::withMessages([
                        'status' =>
                            'لا يمكن تغيير حالة طلب مكتمل أو ملغي.',
                    ]);
                }

                /*
                 * Parent order.
                 */
                $parentOrder =
                    $lockedVendorOrder
                        ->order()
                        ->with([
                            'user',
                            'payments',
                        ])
                        ->lockForUpdate()
                        ->firstOrFail();

                $paymentMethod =
                    $parentOrder->payment_method;

                $paymentStatus =
                    $parentOrder->payment_status;

                /*
                 * ======================================
                 * CANCELLATION
                 * ======================================
                 */
                if (
                    $newStatus ===
                    'cancelled'
                ) {
                    /*
                     * Paid online order requires
                     * refund flow first.
                     */
                    if (
                        $paymentMethod === 'online' &&
                        in_array(
                            $paymentStatus,
                            [
                                'paid',
                                'refunded',
                            ],
                            true
                        )
                    ) {
                        throw ValidationException::withMessages([
                            'status' =>
                                'لا يمكن إلغاء طلب مدفوع إلكترونيًا قبل تنفيذ عملية الاسترداد.',
                        ]);
                    }

                    $lockedVendorOrder
                        ->load('items');

                    foreach (
                        $lockedVendorOrder->items
                        as $item
                    ) {
                        if (
                            ! $item->product_variant_id
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
                                    $lockedVendorOrder->store_id
                                )
                                ->lockForUpdate()
                                ->first();

                        if (! $inventory) {
                            continue;
                        }

                        /*
                         * ONLINE + PENDING
                         *
                         * Stock was only reserved,
                         * not deducted.
                         */
                        if (
                            $paymentMethod === 'online' &&
                            in_array(
                                $paymentStatus,
                                [
                                    'pending',
                                    'unpaid',
                                ],
                                true
                            )
                        ) {
                            $releaseQuantity =
                                min(
                                    (int)
                                    $inventory->reserved_quantity,
                                    (int)
                                    $item->quantity
                                );

                            if (
                                $releaseQuantity > 0
                            ) {
                                $inventory->decrement(
                                    'reserved_quantity',
                                    $releaseQuantity
                                );
                            }

                            continue;
                        }

                        /*
                         * ONLINE + FAILED/CANCELLED
                         *
                         * PaymentService already
                         * released the reservation.
                         */
                        if (
                            $paymentMethod === 'online' &&
                            in_array(
                                $paymentStatus,
                                [
                                    'failed',
                                    'cancelled',
                                ],
                                true
                            )
                        ) {
                            continue;
                        }

                        /*
                         * COD:
                         *
                         * Stock was deducted during
                         * checkout, so restore it.
                         */
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
                                'Cancelled vendor order #' .
                                $lockedVendorOrder->id,
                        ]);

                        /*
                         * Keep legacy variant.stock
                         * synchronized.
                         */
                        $inventory->refresh();

                        if ($inventory->variant) {
                            $inventory
                                ->variant()
                                ->update([
                                    'stock' =>
                                        $inventory->quantity,
                                ]);
                        }
                    }

                    /*
                     * Make sure earning exists
                     * even for older orders.
                     */
                    $earningService
                        ->createForVendorOrder(
                            $lockedVendorOrder
                        );

                    /*
                     * Remove pending/funded earning.
                     */
                    $earningService
                        ->reverse(
                            $lockedVendorOrder
                        );
                }

                /*
                 * Update VendorOrder status.
                 */
                $lockedVendorOrder->update([
                    'status' =>
                        $newStatus,
                ]);

                /*
                 * ======================================
                 * DELIVERED
                 * ======================================
                 */
                if (
                    $newStatus ===
                    'delivered'
                ) {
                    /*
                     * Online orders must actually
                     * be paid before delivery can
                     * release seller earnings.
                     */
                    if (
                        $paymentMethod === 'online' &&
                        $paymentStatus !== 'paid'
                    ) {
                        throw ValidationException::withMessages([
                            'status' =>
                                'لا يمكن إكمال طلب إلكتروني قبل نجاح الدفع.',
                        ]);
                    }

                    /*
                     * Ensure earning exists.
                     */
                    $earningService
                        ->createForVendorOrder(
                            $lockedVendorOrder
                        );

                    /*
                     * Online:
                     * normally already funded
                     * by PaymentService.
                     *
                     * COD:
                     * fund it when delivered.
                     *
                     * Method is idempotent,
                     * so calling it again is safe.
                     */
                    $earningService
                        ->fundVendorOrder(
                            $lockedVendorOrder
                        );

                    /*
                     * Seller money becomes
                     * withdrawable.
                     *
                     * funded -> available
                     */
                    $earningService
                        ->makeAvailable(
                            $lockedVendorOrder
                        );
                }

                /*
                 * Sync parent Order status.
                 */
                $this
                    ->syncParentOrderStatus(
                        $lockedVendorOrder
                    );

                /*
                 * Refresh parent order because
                 * syncParentOrderStatus may
                 * have changed it.
                 */
                $parentOrder->refresh();

                /*
                 * If ALL vendor orders of a COD
                 * order are delivered, COD is
                 * considered collected.
                 */
                if (
                    $paymentMethod === 'cod' &&
                    $parentOrder->status === 'delivered' &&
                    $parentOrder->payment_status !== 'paid'
                ) {
                    $parentOrder->update([
                        'payment_status' =>
                            'paid',
                    ]);

                    $codPayment =
                        $parentOrder
                            ->payments()
                            ->where(
                                'method',
                                'cod'
                            )
                            ->lockForUpdate()
                            ->latest('id')
                            ->first();

                    if (
                        $codPayment &&
                        $codPayment->status !== 'paid'
                    ) {
                        $codPayment->update([
                            'status' =>
                                'paid',

                            'paid_at' =>
                                now(),

                            'failed_at' =>
                                null,
                        ]);
                    }
                }

                /*
                 * Reload customer after status
                 * synchronization.
                 */
                $parentOrder->load('user');

                /*
                 * Send notification after commit.
                 */
                if (
                    $parentOrder &&
                    $parentOrder->user
                ) {
                    DB::afterCommit(
                        function () use (
                            $parentOrder,
                            $newStatus
                        ) {
                            try {
                                $parentOrder
                                    ->user
                                    ->notify(
                                        new OrderStatusNotification(
                                            $parentOrder,
                                            $newStatus
                                        )
                                    );

                                Log::info(
                                    'ORDER STATUS NOTIFICATION SENT',
                                    [
                                        'order_id' =>
                                            $parentOrder->id,

                                        'user_id' =>
                                            $parentOrder
                                                ->user
                                                ->id,

                                        'status' =>
                                            $newStatus,
                                    ]
                                );
                            } catch (\Throwable $e) {
                                Log::error(
                                    'ORDER STATUS NOTIFICATION FAILED',
                                    [
                                        'order_id' =>
                                            $parentOrder->id,

                                        'status' =>
                                            $newStatus,

                                        'error' =>
                                            $e->getMessage(),
                                    ]
                                );
                            }
                        }
                    );
                }

                Log::info(
                    'VENDOR STATUS UPDATED',
                    [
                        'vendor_order_id' =>
                            $lockedVendorOrder->id,

                        'order_id' =>
                            $parentOrder->id,

                        'old_status' =>
                            $oldStatus,

                        'new_status' =>
                            $newStatus,
                    ]
                );
            }
        );

        return response()->json([
            'message' =>
                'تم تحديث حالة الطلب.',

            'vendor_order' =>
                $vendorOrder->fresh([
                    'order.user',
                    'store',
                    'items.orderItem',
                    'items.variant.product',
                    'earning',
                ]),
        ]);
    }

    /**
     * Synchronize parent order status
     * from all vendor orders.
     */
    private function syncParentOrderStatus(
        VendorOrder $vendorOrder
    ): void {
        $order =
            $vendorOrder
                ->order()
                ->lockForUpdate()
                ->first();

        if (! $order) {
            return;
        }

        $statuses =
            $order
                ->vendorOrders()
                ->pluck('status');

        if ($statuses->isEmpty()) {
            return;
        }

        /*
         * All vendor orders cancelled.
         */
        if (
            $statuses->every(
                fn ($status) =>
                    $status === 'cancelled'
            )
        ) {
            $order->update([
                'status' =>
                    'cancelled',
            ]);

            return;
        }

        /*
         * Ignore cancelled vendor orders
         * while calculating progress.
         */
        $activeStatuses =
            $statuses->reject(
                fn ($status) =>
                    $status === 'cancelled'
            );

        if (
            $activeStatuses->isEmpty()
        ) {
            $order->update([
                'status' =>
                    'cancelled',
            ]);

            return;
        }

        /*
         * All active vendor orders delivered.
         */
        if (
            $activeStatuses->every(
                fn ($status) =>
                    $status === 'delivered'
            )
        ) {
            $order->update([
                'status' =>
                    'delivered',
            ]);

            return;
        }

        /*
         * All active orders are shipped
         * or delivered.
         */
        if (
            $activeStatuses->every(
                fn ($status) =>
                    in_array(
                        $status,
                        [
                            'shipped',
                            'delivered',
                        ],
                        true
                    )
            )
        ) {
            $order->update([
                'status' =>
                    'shipped',
            ]);

            return;
        }

        /*
         * At least one order progressed
         * beyond confirmed.
         */
        if (
            $activeStatuses->contains(
                'processing'
            ) ||
            $activeStatuses->contains(
                'shipped'
            ) ||
            $activeStatuses->contains(
                'delivered'
            )
        ) {
            $order->update([
                'status' =>
                    'processing',
            ]);

            return;
        }

        /*
         * All active orders confirmed.
         */
        if (
            $activeStatuses->every(
                fn ($status) =>
                    $status === 'confirmed'
            )
        ) {
            $order->update([
                'status' =>
                    'confirmed',
            ]);

            return;
        }

        /*
         * Otherwise pending.
         */
        $order->update([
            'status' =>
                'pending',
        ]);
    }
}