<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    protected $fillable = ['name', 'is_weighable'];

    protected $casts = [
        'is_weighable' => 'boolean',
    ];

    public function productUnits(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }
}
