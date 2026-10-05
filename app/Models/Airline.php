<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'iata_code', 'icao_code', 'awb_prefix', 'name',
    'tracking_driver', 'tracking_config', 'is_active',
])]
class Airline extends Model
{
    protected function casts(): array
    {
        return [
            'tracking_config' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function awbs(): HasMany
    {
        return $this->hasMany(Awb::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function label(): string
    {
        return "{$this->iata_code} {$this->name} ({$this->awb_prefix})";
    }
}
