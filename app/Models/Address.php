<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'label',
        'recipient_name',
        'phone',
        'country_code',
        'city',
        'area',
        'street',
        'building',
        'notes',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function formattedAddress(): string
    {
        $parts = array_filter([
            $this->country_code,
            $this->city,
            $this->area,
            $this->street,
            $this->building
                ? 'Building: ' . $this->building
                : null,
        ]);

        $address = implode(', ', $parts);

        if ($this->notes) {
            $address .= ' - ' . $this->notes;
        }

        return sprintf(
            '%s | %s | %s',
            $this->recipient_name,
            $this->phone,
            $address
        );
    }
}