<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    public function index(Request $request)
    {
        $admin = $request->user();


        if (! $admin->isPlatformAdmin() && $admin->role !== 'tenant_admin') {

            return response()->json([
                'message' => 'غير مصرح لك بعرض الطلبات.',
            ], 403);

        }


        $query = Order::with([
            'user',
            'items.product',
            'items.store',
            'vendorOrders.store'
        ])
        ->latest();


        if ($admin->role === 'tenant_admin') {

            $query->where('tenant_id', $admin->tenant_id);

        }


        return response()->json([
            'orders' => $query->get(),
        ]);
    }

    public function updateStatus(Request $request, Order $order)
{
    $admin = $request->user();

    if (! $admin->isPlatformAdmin() && $admin->role !== 'tenant_admin') {
        return response()->json([
            'message' => 'غير مصرح لك بتعديل الطلبات.',
        ], 403);
    }

    $data = $request->validate([
        'status' => [
            'required',
            'in:pending,confirmed,processing,shipped,completed,cancelled'
        ],
    ]);

    $order->update([
        'status' => $data['status'],
    ]);

    return response()->json([
        'message' => 'تم تحديث حالة الطلب.',
        'order' => $order->fresh(),
    ]);
}
}