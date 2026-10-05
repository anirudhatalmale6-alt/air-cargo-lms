<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'shipment_id', 'status', 'location', 'flight_number', 'pieces', 'weight_kg',
    'occurred_at', 'description', 'source', 'visible_to_customer', 'recorded_by',
])]
class ShipmentStatusEvent extends Model
{
    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'occurred_at' => 'datetime',
            'weight_kg' => 'decimal:2',
            'visible_to_customer' => 'boolean',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isFromApi(): bool
    {
        return str_starts_with((string) $this->source, 'api:');
    }
}
