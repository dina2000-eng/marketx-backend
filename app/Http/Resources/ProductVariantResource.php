<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $availableStock = $this->relationLoaded('inventory')
            ? ($this->inventory?->availableQuantity() ?? $this->stock)
            : $this->stock;

        return [
            'id' => (int) $this->id,

            'name' => $this->name,

            'sku' => $this->sku,

            'price' => (float) $this->price,

            // Actual available stock
            'stock' => (int) $availableStock,

            'is_active' => (bool) $this->is_active,
        ];
    }
}