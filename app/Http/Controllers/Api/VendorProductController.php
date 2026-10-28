<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class VendorProductController extends Controller
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

        return ProductResource::collection(
            $store->products()
                ->with([
                    'category',
                    'images',
                    'variants.inventory',
                ])
                ->latest()
                ->get()
        );
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $store = $user->ownedStores()
            ->first();

        if (!$store) {
            return response()->json([
                'message' => 'No store found',
            ], 404);
        }

        $data = $request->validate([
            'category_id' => [
                'nullable',
                Rule::exists(
                    'categories',
                    'id'
                )->where(
                    'store_id',
                    $store->id
                ),
            ],

            'name' =>
                'required|string|max:255',

            'description' =>
                'nullable|string',

            'base_price' =>
                'required|numeric|min:0',

            'compare_price' =>
                'nullable|numeric|min:0',

            'currency' =>
                'required|string|size:3',

            'has_variants' =>
                'sometimes|boolean',
        ]);

        $hasVariants =
            (bool) (
                $data['has_variants']
                ?? false
            );

        $product = DB::transaction(
            function () use (
                $data,
                $store,
                $hasVariants
            ) {
                $productData = $data;

                unset(
                    $productData[
                        'has_variants'
                    ]
                );

                $product =
                    Product::create([
                        ...$productData,

                        'tenant_id' =>
                            $store->tenant_id,

                        'store_id' =>
                            $store->id,

                        'slug' =>
                            Str::slug(
                                $data['name']
                            )
                            . '-'
                            . Str::random(5),

                        'has_variants' =>
                            $hasVariants,

                        'status' =>
                            'pending_review',
                    ]);

                /*
                 * Simple product:
                 *
                 * Create an internal Default
                 * variant because Cart and
                 * Inventory work with
                 * product_variant_id.
                 */
                if (!$hasVariants) {
                    $defaultVariant =
                        $product->variants()
                            ->create([
                                'name' =>
                                    'Default',

                                'sku' =>
                                    'PRD-'
                                    . $product->id
                                    . '-'
                                    . strtoupper(
                                        Str::random(6)
                                    ),

                                'price' =>
                                    $product
                                        ->base_price,

                                'stock' =>
                                    0,

                                'is_active' =>
                                    true,
                            ]);

                    /*
                     * Every purchasable variant
                     * must have an Inventory row.
                     *
                     * Inventory is the primary
                     * stock source.
                     */
                    Inventory::create([
                        'store_id' =>
                            $store->id,

                        'product_variant_id' =>
                            $defaultVariant
                                ->id,

                        'quantity' =>
                            0,

                        'reserved_quantity' =>
                            0,
                    ]);
                }

                return $product;
            }
        );

        return new ProductResource(
            $product->load([
                'category',
                'images',
                'variants.inventory',
            ])
        );
    }

    public function update(
        Request $request,
        Product $product
    ) {
        $this->authorizeProduct(
            $request,
            $product
        );

        $data = $request->validate([
            'category_id' => [
                'nullable',

                Rule::exists(
                    'categories',
                    'id'
                )->where(
                    'store_id',
                    $product->store_id
                ),
            ],

            'name' =>
                'sometimes|string|max:255',

            'description' =>
                'nullable|string',

            'base_price' =>
                'sometimes|numeric|min:0',

            'compare_price' =>
                'nullable|numeric|min:0',

            'currency' =>
                'sometimes|string|size:3',
        ]);

        DB::transaction(
            function () use (
                $product,
                $data
            ) {
                $product->update(
                    $data
                );

                /*
                 * For simple products,
                 * keep Default variant price
                 * synchronized with base_price.
                 */
                if (
                    !$product->has_variants
                ) {
                    $defaultVariant =
                        $product->variants()
                            ->where(
                                'name',
                                'Default'
                            )
                            ->first();

                    /*
                     * Legacy simple product
                     * may not have Default.
                     */
                    if (!$defaultVariant) {
                        $defaultVariant =
                            $product->variants()
                                ->create([
                                    'name' =>
                                        'Default',

                                    'sku' =>
                                        'PRD-'
                                        . $product->id
                                        . '-'
                                        . strtoupper(
                                            Str::random(6)
                                        ),

                                    'price' =>
                                        $product
                                            ->base_price,

                                    'stock' =>
                                        0,

                                    'is_active' =>
                                        true,
                                ]);
                    } else {
                        $defaultVariant
                            ->update([
                                'price' =>
                                    $product
                                        ->base_price,

                                'is_active' =>
                                    true,
                            ]);
                    }

                    /*
                     * Repair old products that
                     * have Default variant but
                     * no Inventory row.
                     */
                    Inventory::firstOrCreate(
                        [
                            'store_id' =>
                                $product
                                    ->store_id,

                            'product_variant_id' =>
                                $defaultVariant
                                    ->id,
                        ],
                        [
                            'quantity' =>
                                $defaultVariant
                                    ->stock,

                            'reserved_quantity' =>
                                0,
                        ]
                    );
                }
            }
        );

        return new ProductResource(
            $product->fresh()
                ->load([
                    'category',
                    'images',
                    'variants.inventory',
                ])
        );
    }

    public function destroy(
        Request $request,
        Product $product
    ) {
        $this->authorizeProduct(
            $request,
            $product
        );

        $product->delete();

        return response()->json([
            'message' =>
                'Product deleted',
        ]);
    }

    private function authorizeProduct(
        Request $request,
        Product $product
    ): void {
        abort_unless(
            (int) $product
                ->store
                ->owner_id ===
                (int) $request
                    ->user()
                    ->id,
            403,
            'Unauthorized'
        );
    }
}