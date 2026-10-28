<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttributeValue extends Model
{

    protected $fillable = [
        'product_attribute_id',
        'value',
    ];


    public function attribute(): BelongsTo
    {
        return $this->belongsTo(
            ProductAttribute::class,
            'product_attribute_id'
        );
    }

    public function variants(): BelongsToMany
{
    return $this->belongsToMany(
        ProductVariant::class,
        'attribute_value_variant'
    );
}

}