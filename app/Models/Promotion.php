<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promotion extends Model
{
    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'is_active',
        'description',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PromotionItem::class);
    }

    /**
     * Memeriksa apakah promosi ini sedang aktif pada tanggal tertentu (default hari ini).
     */
    public function isCurrentlyActive(?string $date = null): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $checkDate = $date ?: now()->toDateString();

        if ($this->start_date && $this->start_date->toDateString() > $checkDate) {
            return false;
        }

        if ($this->end_date && $this->end_date->toDateString() < $checkDate) {
            return false;
        }

        return true;
    }

    /**
     * Scope promosi yang aktif saat ini.
     */
    public function scopeActive(Builder $query, ?string $date = null): Builder
    {
        $checkDate = $date ?: now()->toDateString();

        return $query->where('is_active', true)
            ->where(function ($q) use ($checkDate) {
                $q->whereNull('start_date')->orWhereDate('start_date', '<=', $checkDate);
            })
            ->where(function ($q) use ($checkDate) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $checkDate);
            });
    }
}
