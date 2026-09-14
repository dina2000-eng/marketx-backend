<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'status',
        'total_amount',
        'currency',
        'shipping_address',
    ];


    protected $casts = [
        'total_amount' => 'decimal:2',
    ];


    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }


    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function vendorOrders(): HasMany
{
    return $this->hasMany(VendorOrder::class);
}
}