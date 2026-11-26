<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Orders\AdminOrderService;
use App\Services\Marketplace\VendorEarningService;
use App\Services\Payments\PaymentService;
use Illuminate\Http\Request;

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
        AdminOrderService $orderService,
        PaymentService $paymentService,
        VendorEarningService $earningService
    ) {
        $admin = $request->user();

        if (
            ! $admin->isPlatformAdmin() &&
            $admin->role !== 'tenant_admin'
        ) {
            return response()->json([
                'message' => 'غير مصرح لك بتعديل الطلبات.',
            ], 403);
        }

        if (
            $admin->role === 'tenant_admin' &&
            (int) $order->tenant_id !== (int) $admin->tenant_id
        ) {
            return response()->json([
                'message' => 'هذا الطلب لا يتبع للـTenant الخاص بك.',
            ], 403);
        }

        $data = $request->validate([
            'status' => [
                'required',
                'in:pending,confirmed,processing,shipped,delivered,cancelled',
            ],
        ]);

        $updatedOrder = $orderService->updateStatus(
            $order,
            $data['status'],
            $paymentService,
            $earningService
        );

        return response()->json([
            'message' => 'تم تحديث حالة الطلب.',
            'order' => $updatedOrder,
        ]);
    }

}