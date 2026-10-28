<?php

namespace App\Models;

use App\Models\Inventory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{

    protected $fillable = [
        'product_id',
        'name',
        'sku',
        'price',
        'stock',
        'is_active',
    ];


    protected $casts = [
        'is_active'=>'boolean',
        'price'=>'decimal:2',
         'stock' => 'integer', 
    ];


    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
    
    public function attributeValues(): BelongsToMany
{
    return $this->belongsToMany(
        AttributeValue::class,
        'attribute_value_variant'
    );
}
public function inventory()
{
    return $this->hasOne(Inventory::class);
}


}