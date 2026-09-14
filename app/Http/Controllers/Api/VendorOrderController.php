<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VendorOrder;
use Illuminate\Http\Request;

class VendorOrderController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $store = $user->ownedStore()->first();

        if (! $store) {
            return response()->json([
                'message' => 'لا يوجد متجر لهذا البائع.',
            ], 404);
        }


        $orders = VendorOrder::with([
                'order.user',
                'order.items.product'
            ])
            ->where('store_id', $store->id)
            ->latest()
            ->get();


        return response()->json([
            'store' => $store,
            'vendor_orders' => $orders,
        ]);
    }


    public function updateStatus(Request $request, VendorOrder $vendorOrder)
    {
        $user = $request->user();

        $store = $user->ownedStore()->first();

        if (! $store || $vendorOrder->store_id !== $store->id) {
            return response()->json([
                'message' => 'غير مصرح لك بتعديل هذا الطلب.',
            ], 403);
        }


        $data = $request->validate([
            'status' => [
                'required',
                'in:pending,confirmed,processing,shipped,completed,cancelled'
            ],
        ]);


        $vendorOrder->update([
            'status' => $data['status'],
        ]);


        return response()->json([
            'message' => 'تم تحديث حالة الطلب.',
            'vendor_order' => $vendorOrder->fresh([
                'order.user',
                'store'
            ]),
        ]);
    }
}