<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use Illuminate\Http\Request;

class VendorInventoryController extends Controller
{
    public function index(Request $request)
    {
        $store = $request->user()
            ->ownedStores()
            ->first();

        if (!$store) {
            return response()->json([
                'message' => 'No store found',
            ], 404);
        }

        $inventories = Inventory::query()
            ->with([
                'variant.product',
            ])
            ->where(
                'store_id',
                $store->id
            )
            ->get()
            ->map(function ($inventory) {
                $variant =
                    $inventory->variant;

                $product =
                    $variant?->product;

                return [
                    'id' =>
                        (int) $inventory->id,

                    'store_id' =>
                        (int) $inventory->store_id,

                    'product_variant_id' =>
                        (int) $inventory
                            ->product_variant_id,

                    'quantity' =>
                        (int) $inventory->quantity,

                    'reserved_quantity' =>
                        (int) $inventory
                            ->reserved_quantity,

                    'available_quantity' =>
                        (int) $inventory
                            ->availableQuantity(),

                    'low_stock_threshold' =>
                        (int) $inventory
                            ->low_stock_threshold,

                    'variant' => $variant
                        ? [
                            'id' =>
                                (int) $variant->id,

                            /*
                             * Hide internal Default
                             * name from presentation.
                             */
                            'name' =>
                                $variant->name === 'Default'
                                    ? null
                                    : $variant->name,

                            'raw_name' =>
                                $variant->name,

                            'sku' =>
                                $variant->sku,

                            'price' =>
                                (float) $variant->price,

                            'is_active' =>
                                (bool) $variant->is_active,

                            'is_default' =>
                                $variant->name === 'Default',

                            'product' => $product
                                ? [
                                    'id' =>
                                        (int) $product->id,

                                    'name' =>
                                        $product->name,

                                    'has_variants' =>
                                        (bool) $product
                                            ->has_variants,

                                    'status' =>
                                        $product->status,
                                ]
                                : null,
                        ]
                        : null,
                ];
            })
            ->values();

        return response()->json(
            $inventories
        );
    }
}