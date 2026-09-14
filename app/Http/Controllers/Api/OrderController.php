<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $orders = Order::with([
                'items.product',
                'items.store'
            ])
            ->where('user_id', $user->id)
            ->latest()
            ->get();


        return response()->json([
            'orders' => $orders,
        ]);
    }

    public function show(Request $request, Order $order)
{
    $user = $request->user();

    if ($order->user_id !== $user->id) {
        return response()->json([
            'message' => 'غير مصرح لك بعرض هذا الطلب.',
        ], 403);
    }


    return response()->json([
        'order' => $order->load([
            'items.product',
            'items.store'
        ]),
    ]);
}
}