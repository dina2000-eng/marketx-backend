<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{

    protected $fillable = [
        'store_id',
        'product_variant_id',
        'quantity',
        'reserved_quantity',
        'low_stock_threshold'
    ];


    public function store()
    {
        return $this->belongsTo(Store::class);
    }


    public function variant()
    {
        return $this->belongsTo(ProductVariant::class,'product_variant_id');
    }


    public function availableQuantity()
    {
        return $this->quantity - $this->reserved_quantity;
    }

    public function transactions()
{
    return $this->hasMany(
        InventoryTransaction::class
    );
}
public function scopeLowStock($query)
{
    return $query->whereColumn(
        'quantity',
        '<=',
        'low_stock_threshold'
    );
}

}