<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorOrder extends Model
{
    protected $fillable = [
        'order_id',
        'store_id',
        'status',
        'total_amount',
    ];


    protected $casts = [
        'total_amount' => 'decimal:2',
    ];


    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }


    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}