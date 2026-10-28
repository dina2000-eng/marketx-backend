<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /*
         * Main image
         */
        $mainImage = null;

        if ($this->relationLoaded('images')) {
            $mainImage = $this->images
                ->firstWhere('is_main', true)
                ?? $this->images->first();
        }

        /*
         * Loaded active variants.
         */
        $loadedVariants = collect();

        if ($this->relationLoaded('variants')) {
            $loadedVariants = $this->variants;
        }

        /*
         * Internal Default variant for
         * simple products.
         */
        $defaultVariant = null;

        if (!$this->has_variants) {
            $defaultVariant = $loadedVariants
                ->firstWhere(
                    'name',
                    'Default'
                );
        }

        /*
         * Real public variants only.
         *
         * Defensive filter:
         * Default must never be exposed
         * to the buyer.
         */
        $publicVariants = $loadedVariants
            ->reject(
                fn ($variant) =>
                    $variant->name === 'Default'
            )
            ->values();

        /*
         * Simple products still need a
         * variant_id internally for Cart.
         */
        $purchaseVariantId =
            $defaultVariant
                ? (int) $defaultVariant->id
                : null;

        /*
         * Available stock.
         *
         * Inventory is the primary source.
         * variant.stock is legacy fallback.
         */
        $availableStock = 0;

        if ($this->has_variants) {
            $availableStock =
                $publicVariants->sum(
                    function ($variant) {
                        if (
                            $variant->relationLoaded(
                                'inventory'
                            ) &&
                            $variant->inventory
                        ) {
                            return max(
                                0,
                                (int) $variant
                                    ->inventory
                                    ->availableQuantity()
                            );
                        }

                        return max(
                            0,
                            (int) $variant->stock
                        );
                    }
                );
        } elseif ($defaultVariant) {
            if (
                $defaultVariant->relationLoaded(
                    'inventory'
                ) &&
                $defaultVariant->inventory
            ) {
                $availableStock = max(
                    0,
                    (int) $defaultVariant
                        ->inventory
                        ->availableQuantity()
                );
            } else {
                $availableStock = max(
                    0,
                    (int) $defaultVariant->stock
                );
            }
        }

        return [
            'id' =>
                (int) $this->id,

            'name' =>
                $this->name,

            'slug' =>
                $this->slug,

            'description' =>
                $this->description,

            'base_price' =>
                (float) $this->base_price,

            'compare_price' =>
                $this->compare_price !== null
                    ? (float) $this->compare_price
                    : null,

            'currency' =>
                $this->currency,

            'status' =>
                $this->status,

            'has_variants' =>
                (bool) $this->has_variants,

            /*
             * Total available stock.
             */
            'stock' =>
                (int) $availableStock,

            /*
             * Hidden variant used by Cart
             * for simple products.
             */
            'purchase_variant_id' =>
                $purchaseVariantId,

            'main_image_url' =>
                $mainImage
                    ? url(
                        'storage/' .
                        $mainImage->image_url
                    )
                    : null,

            'category' =>
                new CategoryResource(
                    $this->whenLoaded(
                        'category'
                    )
                ),

            'store' =>
                $this->whenLoaded(
                    'store',
                    function () {
                        return [
                            'id' =>
                                (int) $this
                                    ->store
                                    ->id,

                            'name' =>
                                $this
                                    ->store
                                    ->name,

                            'logo_url' =>
                                $this
                                    ->store
                                    ->logo_url,
                        ];
                    }
                ),

            'images' =>
                ProductImageResource::collection(
                    $this->whenLoaded(
                        'images'
                    )
                ),

            /*
             * Simple product:
             * []
             *
             * Variant product:
             * real variants only.
             */
            'variants' =>
                $this->has_variants
                    ? ProductVariantResource::collection(
                        $publicVariants
                    )
                    : [],
        ];
    }
}