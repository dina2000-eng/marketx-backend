<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VendorOrder extends Model
{
    protected $fillable = [
        'order_id',
        'store_id',
        'status',
        'total_amount',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'store_id' => 'integer',
        'total_amount' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(
            Order::class
        );
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(
            Store::class
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(
            VendorOrderItem::class
        );
    }

    public function earning(): HasOne
{
    return $this->hasOne(
        VendorEarning::class
    );
}
}