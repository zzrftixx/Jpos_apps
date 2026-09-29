<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = ['name', 'customer_type', 'phone', 'email', 'address', 'points'];

    protected $casts = [
        'points' => 'integer',
    ];

    public function isReseller(): bool
    {
        return strtoupper($this->customer_type ?? '') === 'RESELLER';
    }

    public function isGrosir(): bool
    {
        return strtoupper($this->customer_type ?? '') === 'GROSIR';
    }

    public function isUmum(): bool
    {
        return !$this->isReseller() && !$this->isGrosir();
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
