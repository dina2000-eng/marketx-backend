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
            'cart' => $cart->load([
                'items.product.store',
                'items.variant.inventory',
            ]),
        ]);
    }

    public function addItem(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'product_id' => [
                'required',
                'integer',
                'exists:products,id',
            ],

            'variant_id' => [
                'required',
                'integer',
                'exists:product_variants,id',
            ],

            'quantity' => [
                'required',
                'integer',
                'min:1',
            ],
        ]);

        /*
         * Publicly available product only.
         */
        $product = Product::query()
            ->whereKey($data['product_id'])
            ->where('status', 'approved')

            ->whereHas('store', function ($query) {
                $query->where('status', 'active');
            })

            ->with('store')
            ->first();

        if (!$product) {
            return response()->json([
                'message' => 'المنتج غير متاح.',
            ], 404);
        }

        /*
         * Product must belong to the same tenant.
         */
        if (
            $user->tenant_id !== null &&
            (int) $product->tenant_id !==
                (int) $user->tenant_id
        ) {
            return response()->json([
                'message' => 'المنتج غير متاح.',
            ], 404);
        }

        /*
         * Variant must actually belong
         * to this product and be active.
         *
         * This works for:
         * - normal variants
         * - hidden Default Variant
         */
        $variant = $product->variants()
            ->whereKey($data['variant_id'])
            ->where('is_active', true)
            ->with('inventory')
            ->first();

        if (!$variant) {
            return response()->json([
                'message' => 'الخيار غير متاح لهذا المنتج.',
            ], 422);
        }

        /*
         * Inventory is now the main
         * source of stock.
         *
         * Fallback to variant.stock only
         * for old records that do not yet
         * have an inventory record.
         */
        $availableQuantity = $variant->inventory
            ? $variant->inventory->availableQuantity()
            : $variant->stock;

        if ($availableQuantity < $data['quantity']) {
            return response()->json([
                'message' => 'الكمية المطلوبة غير متوفرة.',
            ], 422);
        }

        $cart = Cart::firstOrCreate([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        /*
         * Same product + same variant
         * should use one cart row.
         */
        $item = CartItem::query()
            ->where('cart_id', $cart->id)
            ->where('product_id', $product->id)
            ->where('variant_id', $variant->id)
            ->first();

        $newQuantity =
            ($item?->quantity ?? 0) +
            $data['quantity'];

        /*
         * Important:
         * validate total quantity already
         * existing in the cart.
         */
        if ($availableQuantity < $newQuantity) {
            return response()->json([
                'message' =>
                    'الكمية الإجمالية المطلوبة غير متوفرة.',
            ], 422);
        }

        if ($item) {
            $item->update([
                'quantity' => $newQuantity,

                // Refresh the current price.
                'price' => $variant->price,
            ]);
        } else {
            $item = CartItem::create([
                'cart_id' => $cart->id,

                'product_id' => $product->id,

                'variant_id' => $variant->id,

                'quantity' => $data['quantity'],

                'price' => $variant->price,
            ]);
        }

        return response()->json([
            'message' => 'تمت إضافة المنتج للسلة.',

            'item' => $item->load([
                'product.store',
                'variant.inventory',
            ]),
        ], 201);
    }

    public function updateItem(
        Request $request,
        $itemId
    ) {
        $user = $request->user();

        $data = $request->validate([
            'quantity' => [
                'required',
                'integer',
                'min:1',
            ],
        ]);

        $item = CartItem::query()
            ->whereKey($itemId)

            ->whereHas(
                'cart',
                function ($query) use ($user) {
                    $query
                        ->where(
                            'user_id',
                            $user->id
                        )
                        ->where(
                            'tenant_id',
                            $user->tenant_id
                        )
                        ->where(
                            'status',
                            'active'
                        );
                }
            )

            ->with([
                'product.store',
                'variant.inventory',
            ])

            ->firstOrFail();

        /*
         * Product must still be available.
         */
        if (
            !$item->product ||
            $item->product->status !== 'approved' ||
            !$item->product->store ||
            $item->product->store->status !== 'active'
        ) {
            return response()->json([
                'message' =>
                    'هذا المنتج لم يعد متاحًا.',
            ], 422);
        }

        /*
         * Variant must still be active.
         */
        if (
            !$item->variant ||
            !$item->variant->is_active
        ) {
            return response()->json([
                'message' =>
                    'هذا الخيار لم يعد متاحًا.',
            ], 422);
        }

        $availableQuantity =
            $item->variant->inventory
                ? $item->variant
                    ->inventory
                    ->availableQuantity()
                : $item->variant->stock;

        if (
            $availableQuantity <
            $data['quantity']
        ) {
            return response()->json([
                'message' =>
                    'الكمية المطلوبة غير متوفرة.',
            ], 422);
        }

        $item->update([
            'quantity' => $data['quantity'],

            /*
             * Refresh current price as well.
             */
            'price' => $item->variant->price,
        ]);

        return response()->json([
            'message' => 'تم تحديث الكمية.',

            'item' => $item->load([
                'product.store',
                'variant.inventory',
            ]),
        ]);
    }

    public function removeItem(
        Request $request,
        $itemId
    ) {
        $user = $request->user();

        $item = CartItem::query()
            ->whereKey($itemId)

            ->whereHas(
                'cart',
                function ($query) use ($user) {
                    $query
                        ->where(
                            'user_id',
                            $user->id
                        )
                        ->where(
                            'tenant_id',
                            $user->tenant_id
                        )
                        ->where(
                            'status',
                            'active'
                        );
                }
            )

            ->firstOrFail();

        $item->delete();

        return response()->json([
            'message' =>
                'تم حذف المنتج من السلة.',
        ]);
    }
}