<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = ['name', 'phone', 'email', 'address', 'points'];

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
