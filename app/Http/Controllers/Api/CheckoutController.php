<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\VendorOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public function checkout(Request $request)
    {
        $user = $request->user();

        $cart = Cart::with('items.product')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();


        if (! $cart || $cart->items->isEmpty()) {

            return response()->json([
                'message' => 'السلة فارغة.',
            ], 422);

        }


        return DB::transaction(function () use ($cart, $user) {

            $total = $cart->items->sum(function ($item) {
                return $item->price * $item->quantity;
            });


            // إنشاء الطلب الرئيسي
            $order = Order::create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'status' => 'pending',
                'total_amount' => $total,
                'currency' => $cart->items->first()->product->currency,
            ]);


            // تجميع المنتجات حسب المتجر
            $itemsByStore = $cart->items->groupBy(function ($item) {
                return $item->product->store_id;
            });


            foreach ($itemsByStore as $storeId => $items) {

                $vendorTotal = $items->sum(function ($item) {
                    return $item->price * $item->quantity;
                });


                // إنشاء طلب خاص بالبائع
                $vendorOrder = VendorOrder::create([
                    'order_id' => $order->id,
                    'store_id' => $storeId,
                    'status' => 'pending',
                    'total_amount' => $vendorTotal,
                ]);


                foreach ($items as $item) {

                    $vendorOrder
                        ->order()
                        ->first()
                        ->items()
                        ->create([
                            'product_id' => $item->product_id,
                            'store_id' => $storeId,
                            'quantity' => $item->quantity,
                            'price' => $item->price,
                        ]);

                }
            }


            // تفريغ السلة
            $cart->items()->delete();


            return response()->json([
                'message' => 'تم إنشاء الطلب بنجاح.',
                'order' => $order->load([
                    'items.product',
                    'vendorOrders.store'
                ]),
            ], 201);

        });
    }
}