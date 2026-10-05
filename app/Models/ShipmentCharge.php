<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'shipment_id', 'charge_type_id', 'description', 'basis',
    'quantity', 'rate', 'amount', 'currency', 'side', 'is_taxable',
])]
class ShipmentCharge extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'rate' => 'decimal:4',
            'amount' => 'decimal:2',
            'is_taxable' => 'boolean',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function chargeType(): BelongsTo
    {
        return $this->belongsTo(ChargeType::class);
    }

    /**
     * Quantity comes from the shipment for weight and piece based charges, so a
     * corrected weight reprices the charge instead of leaving a stale amount.
     */
    public function resolveQuantity(Shipment $shipment): float
    {
        return match ($this->basis) {
            'per_kg' => (float) ($shipment->chargeable_weight_kg ?: $shipment->gross_weight_kg),
            'per_piece' => (float) $shipment->pieces,
            default => (float) ($this->quantity ?: 1),
        };
    }
}
