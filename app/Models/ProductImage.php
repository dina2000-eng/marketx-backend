<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{

    protected $fillable = [
        'product_id',
        'image_url',
        'is_main',
        'sort_order',
    ];


    protected $casts = [
        'is_main'=>'boolean',
    ];


    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function images(): HasMany
{
    return $this->hasMany(ProductImage::class)
        ->orderBy('sort_order');
}

}