<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;


class ProductAttribute extends Model
{

    protected $fillable = [
        'store_id',
        'name'
    ];


    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }


    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class);
    }

}