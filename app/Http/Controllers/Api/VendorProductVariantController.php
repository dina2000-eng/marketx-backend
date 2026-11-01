<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\AttributeValue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VendorProductVariantController extends Controller
{
    public function index(
        Request $request,
        Product $product
    ) {
        $this->authorizeProduct(
            $request,
            $product
        );

        $variants = $product->variants()
            ->where('name', '!=', 'Default')
            ->with([
                'attributeValues',
                'inventory',
            ])
            ->latest()
            ->get();

        return response()->json(
            $variants
        );
    }

    public function store(
        Request $request,
        Product $product
    ) {
        $this->authorizeProduct(
            $request,
            $product
        );

        $data = $request->validate([
            'name' =>
                'required|string|max:255',

            'sku' =>
                'nullable|string|max:255',

            'price' =>
                'required|numeric|min:0',

            'stock' =>
                'required|integer|min:0',

            'attribute_values' =>
                'nullable|array',

            'attribute_values.*' =>
                'exists:attribute_values,id',
        ]);

        $attributeValueIds = $data['attribute_values'] ?? [];

if (!empty($attributeValueIds)) {
    $validCount = AttributeValue::query()
        ->whereIn('id', $attributeValueIds)
        ->whereHas('attribute', function ($query) use ($product) {
            $query->where('store_id', $product->store_id);
        })
        ->count();

    if ($validCount !== count(array_unique($attributeValueIds))) {
        throw ValidationException::withMessages([
            'attribute_values' =>
                'بعض قيم الخصائص لا تنتمي إلى متجر هذا المنتج.',
        ]);
    }
}

        $variant = DB::transaction(
            function () use (
                $product,
                $data
            ) {
                /*
                 * If this product was previously
                 * simple, disable its hidden
                 * Default variant.
                 */
                $defaultVariant =
                    $product->variants()
                        ->where(
                            'name',
                            'Default'
                        )
                        ->first();

                if ($defaultVariant) {
                    $defaultVariant->update([
                        'is_active' => false,
                    ]);
                }

                /*
                 * Create the real variant.
                 */
                $variant =
                    ProductVariant::create([
                        'product_id' =>
                            $product->id,

                        'name' =>
                            $data['name'],

                        'sku' =>
                            $data['sku'] ?? null,

                        'price' =>
                            $data['price'],

                        /*
                         * Legacy field.
                         *
                         * Inventory remains the
                         * primary stock source.
                         */
                        'stock' =>
                            $data['stock'],

                        'is_active' =>
                            true,
                    ]);

                /*
                 * Every real variant should
                 * have an Inventory row.
                 */
                Inventory::create([
                    'store_id' =>
                        $product->store_id,

                    'product_variant_id' =>
                        $variant->id,

                    'quantity' =>
                        $data['stock'],
                ]);

                if (
                    !empty(
                        $data['attribute_values']
                    )
                ) {
                    $variant
                        ->attributeValues()
                        ->sync(
                            $data[
                                'attribute_values'
                            ]
                        );
                }

                /*
                 * Product is now a variant product.
                 */
                $product->update([
                    'has_variants' => true,
                ]);

                return $variant;
            }
        );

        return response()->json(
            $variant->load([
                'attributeValues',
                'inventory',
            ]),
            201
        );
    }

    public function update(
        Request $request,
        ProductVariant $variant
    ) {
        $product =
            $variant->product;

        $this->authorizeProduct(
            $request,
            $product
        );

        /*
         * Prevent direct editing of the
         * internal Default variant.
         */
        if ($variant->name === 'Default') {
            return response()->json([
                'message' =>
                    'لا يمكن تعديل الـDefault Variant من هنا.',
            ], 422);
        }

        $data = $request->validate([
            'name' =>
                'sometimes|string|max:255',

            'sku' =>
                'nullable|string|max:255',

            'price' =>
                'sometimes|numeric|min:0',

            'stock' =>
                'sometimes|integer|min:0',

            'is_active' =>
                'sometimes|boolean',

            'attribute_values' =>
                'sometimes|array',

            'attribute_values.*' =>
                'exists:attribute_values,id',
        ]);

        $attributeValueIds = $data['attribute_values'] ?? [];

if (!empty($attributeValueIds)) {
    $validCount = AttributeValue::query()
        ->whereIn('id', $attributeValueIds)
        ->whereHas('attribute', function ($query) use ($product) {
            $query->where('store_id', $product->store_id);
        })
        ->count();

    if ($validCount !== count(array_unique($attributeValueIds))) {
        throw ValidationException::withMessages([
            'attribute_values' =>
                'بعض قيم الخصائص لا تنتمي إلى متجر هذا المنتج.',
        ]);
    }
}

        DB::transaction(
            function () use (
                $variant,
                $product,
                $data
            ) {
                /*
                 * Inventory stock is handled
                 * separately.
                 */
                $variantData = $data;

                unset(
                    $variantData['attribute_values']
                );

                /*
                 * Before updating stock, make sure
                 * it is not below reserved stock.
                 */
                if (
                    array_key_exists(
                        'stock',
                        $data
                    )
                ) {
                    $inventory =
                        $variant->inventory()
                            ->lockForUpdate()
                            ->first();

                    if (
                        $inventory &&
                        $data['stock'] <
                            $inventory
                                ->reserved_quantity
                    ) {
                        throw ValidationException::withMessages([
                            'stock' =>
                                'لا يمكن جعل المخزون أقل من الكمية المحجوزة.',
                        ]);
                    }
                }

                $variant->update(
                    $variantData
                );

                /*
                 * Keep Inventory and the legacy
                 * variant.stock synchronized.
                 */
                if (
                    array_key_exists(
                        'stock',
                        $data
                    )
                ) {
                    $inventory =
                        Inventory::firstOrCreate(
                            [
                                'store_id' =>
                                    $product
                                        ->store_id,

                                'product_variant_id' =>
                                    $variant
                                        ->id,
                            ],
                            [
                                'quantity' =>
                                    $data[
                                        'stock'
                                    ],
                            ]
                        );

                    $inventory->update([
                        'quantity' =>
                            $data['stock'],
                    ]);

                    $variant->update([
                        'stock' =>
                            $data['stock'],
                    ]);
                }

                /*
                 * Allow editing attributes too.
                 */
                if (
                    array_key_exists(
                        'attribute_values',
                        $data
                    )
                ) {
                    $variant
                        ->attributeValues()
                        ->sync(
                            $data[
                                'attribute_values'
                            ] ?? []
                        );
                }
            }
        );

        return response()->json(
            $variant->fresh([
                'attributeValues',
                'inventory',
            ])
        );
    }

    public function destroy(
        Request $request,
        ProductVariant $variant
    ) {
        $product =
            $variant->product;

        $this->authorizeProduct(
            $request,
            $product
        );

        /*
         * Default is an internal system variant.
         */
        if ($variant->name === 'Default') {
            return response()->json([
                'message' =>
                    'لا يمكن حذف الـDefault Variant.',
            ], 422);
        }

        DB::transaction(
            function () use (
                $variant,
                $product
            ) {
                $variant->delete();

                /*
                 * Check if there are any real
                 * variants left.
                 */
                $remainingVariants =
                    $product->variants()
                        ->where(
                            'name',
                            '!=',
                            'Default'
                        )
                        ->count();

                /*
                 * If the last real variant was
                 * removed, return the product
                 * to simple-product mode.
                 */
                if (
                    $remainingVariants === 0
                ) {
                    $defaultVariant =
                        $product->variants()
                            ->where(
                                'name',
                                'Default'
                            )
                            ->first();

                    /*
                     * Normally it already exists.
                     */
                    if ($defaultVariant) {
                        $defaultVariant->update([
                            'price' =>
                                $product
                                    ->base_price,

                            'is_active' =>
                                true,
                        ]);

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
                            ]
                        );
                    } else {
                        /*
                         * Legacy product that has
                         * no hidden default variant.
                         */
                        $defaultVariant =
                            $product
                                ->variants()
                                ->create([
                                    'name' =>
                                        'Default',

                                    'sku' =>
                                        'PRD-' .
                                        $product->id .
                                        '-DEFAULT',

                                    'price' =>
                                        $product
                                            ->base_price,

                                    'stock' =>
                                        0,

                                    'is_active' =>
                                        true,
                                ]);

                        Inventory::create([
                            'store_id' =>
                                $product
                                    ->store_id,

                            'product_variant_id' =>
                                $defaultVariant
                                    ->id,

                            'quantity' =>
                                0,
                        ]);
                    }

                    $product->update([
                        'has_variants' =>
                            false,
                    ]);
                }
            }
        );

        return response()->json([
            'message' =>
                'Variant deleted',
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