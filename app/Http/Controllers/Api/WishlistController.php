<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request)
    {
        $items = Wishlist::query()
            ->where(
                'user_id',
                $request->user()->id
            )
            ->whereHas('product', function ($query) {
                $query
                    ->where(
                        'status',
                        'approved'
                    )
                    ->whereHas(
                        'store',
                        function ($storeQuery) {
                            $storeQuery->where(
                                'status',
                                'active'
                            );
                        }
                    );
            })
            ->with([
                'product.store',
                'product.category',
                'product.images',

                'product.variants' => function ($query) {
                    $query
                        ->where(
                            'is_active',
                            true
                        )
                        ->with('inventory');
                },
            ])
            ->latest()
            ->get();

        return ProductResource::collection(
            $items->pluck('product')
        );
    }

    public function store(
        Request $request,
        Product $product
    ): JsonResponse {
        if (
            $product->status !==
            'approved'
        ) {
            return response()->json([
                'message' =>
                    'المنتج غير متاح.',
            ], 422);
        }

        $product->loadMissing(
            'store'
        );

        if (
            !$product->store ||
            $product->store->status !==
                'active'
        ) {
            return response()->json([
                'message' =>
                    'المنتج غير متاح.',
            ], 422);
        }

        Wishlist::firstOrCreate([
            'user_id' =>
                $request->user()->id,

            'product_id' =>
                $product->id,
        ]);

        return response()->json([
            'message' =>
                'تمت إضافة المنتج إلى المفضلة.',

            'product_id' =>
                (int) $product->id,
        ]);
    }

    public function destroy(
        Request $request,
        Product $product
    ): JsonResponse {
        Wishlist::query()
            ->where(
                'user_id',
                $request->user()->id
            )
            ->where(
                'product_id',
                $product->id
            )
            ->delete();

        return response()->json([
            'message' =>
                'تمت إزالة المنتج من المفضلة.',

            'product_id' =>
                (int) $product->id,
        ]);
    }
}