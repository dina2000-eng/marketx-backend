<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'owner_id', 'name', 'slug', 'logo_url', 'cover_url',
        'description', 'phone', 'email','country_code', 'default_commission_pct',
        'status', 'rejection_reason',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function vendorOrders(): HasMany
{
    return $this->hasMany(VendorOrder::class);
}

public function attributes(): HasMany
{
    return $this->hasMany(ProductAttribute::class);
}

public function earnings(): HasMany
{
    return $this->hasMany(
        VendorEarning::class
    );
}

public function wallets(): HasMany
{
    return $this->hasMany(
        SellerWallet::class
    );
}

public function payouts(): HasMany
{
    return $this->hasMany(
        Payout::class
    );
}
}
