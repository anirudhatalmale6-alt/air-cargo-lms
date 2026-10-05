<?php

namespace App\Models;

use App\Support\AwbNumber;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['airline_id', 'prefix', 'serial', 'number', 'status', 'batch_id', 'received_on', 'notes'])]
class Awb extends Model
{
    protected $table = 'awbs';

    protected function casts(): array
    {
        return ['received_on' => 'date'];
    }

    public function airline(): BelongsTo
    {
        return $this->belongsTo(Airline::class);
    }

    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', 'available');
    }

    /** Create the row from a validated AWB value object. */
    public static function fromNumber(Airline $airline, AwbNumber $awb, array $attributes = []): static
    {
        return static::create(array_merge([
            'airline_id' => $airline->id,
            'prefix' => $awb->prefix,
            'serial' => $awb->serial,
            'number' => $awb->digits(),
            'status' => 'available',
        ], $attributes));
    }

    public function value(): AwbNumber
    {
        return AwbNumber::parse($this->number);
    }

    public function formatted(): string
    {
        return $this->value()->formatted();
    }

    public function isAssignable(): bool
    {
        return $this->status === 'available';
    }
}
