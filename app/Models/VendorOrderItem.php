<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorOrderItem extends Model
{
    protected $fillable = [
        'vendor_order_id',
        'order_item_id',
        'product_variant_id',
        'quantity',
        'price',
        'total',
    ];

    protected $casts = [
        'vendor_order_id' => 'integer',
        'order_item_id' => 'integer',
        'product_variant_id' => 'integer',
        'quantity' => 'integer',
        'price' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function vendorOrder(): BelongsTo
    {
        return $this->belongsTo(
            VendorOrder::class
        );
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(
            OrderItem::class
        );
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(
            ProductVariant::class,
            'product_variant_id'
        );
    }
}