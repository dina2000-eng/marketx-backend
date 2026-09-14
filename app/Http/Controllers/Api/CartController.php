<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $cart = Cart::firstOrCreate([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        return response()->json([
            'cart' => $cart->load('items.product'),
        ]);
    }


    public function addItem(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'product_id' => [
                'required',
                'exists:products,id'
            ],
            'quantity' => [
                'required',
                'integer',
                'min:1'
            ],
        ]);


        $product = Product::where('id', $data['product_id'])
            ->where('status', 'approved')
            ->first();


        if (! $product) {
            return response()->json([
                'message' => 'المنتج غير متاح.',
            ], 404);
        }


        $cart = Cart::firstOrCreate([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);


        $item = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $product->id)
            ->first();


        if ($item) {

            $item->update([
                'quantity' => $item->quantity + $data['quantity'],
            ]);

        } else {

            $item = CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'quantity' => $data['quantity'],
                'price' => $product->base_price,
            ]);

        }


        return response()->json([
            'message' => 'تمت إضافة المنتج للسلة.',
            'item' => $item->load('product'),
        ]);
    }

    public function updateItem(Request $request, $itemId)
{
    $data = $request->validate([
        'quantity' => 'required|integer|min:1',
    ]);

    $item = \App\Models\CartItem::findOrFail($itemId);

    $item->update([
        'quantity' => $data['quantity'],
    ]);

    return response()->json([
        'message' => 'تم تحديث الكمية.',
        'item' => $item->load('product'),
    ]);
}
public function removeItem($itemId)
{
    $item = \App\Models\CartItem::findOrFail($itemId);

    $item->delete();

    return response()->json([
        'message' => 'تم حذف المنتج من السلة.',
    ]);
}
}