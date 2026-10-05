<?php

namespace App\Models;

use App\Enums\DocumentType;
use App\Enums\ShipmentStatus;
use App\Support\AwbNumber;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'reference', 'direction', 'customer_id', 'airline_id', 'awb_id', 'awb_number', 'hawb_number',
    'origin', 'destination', 'flight_number', 'flight_date', 'eta',
    'pieces', 'gross_weight_kg', 'volume_cbm', 'chargeable_weight_kg',
    'commodity', 'nature_of_goods', 'incoterm', 'payment_term', 'is_dangerous_goods',
    'shipper_name', 'consignee_name', 'current_status', 'status_changed_at',
    'currency', 'charges_total', 'created_by', 'remarks',
])]
class Shipment extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'current_status' => ShipmentStatus::class,
            'flight_date' => 'date',
            'eta' => 'date',
            'status_changed_at' => 'datetime',
            'gross_weight_kg' => 'decimal:2',
            'chargeable_weight_kg' => 'decimal:2',
            'volume_cbm' => 'decimal:3',
            'charges_total' => 'decimal:2',
            'is_dangerous_goods' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function airline(): BelongsTo
    {
        return $this->belongsTo(Airline::class);
    }

    public function awb(): BelongsTo
    {
        return $this->belongsTo(Awb::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusEvents(): HasMany
    {
        return $this->hasMany(ShipmentStatusEvent::class)->orderByDesc('occurred_at')->orderByDesc('id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ShipmentDocument::class)->latest('id');
    }

    public function charges(): HasMany
    {
        return $this->hasMany(ShipmentCharge::class);
    }

    public function isExport(): bool
    {
        return $this->direction === 'export';
    }

    public function awbFormatted(): ?string
    {
        return $this->awb_number ? AwbNumber::tryParse($this->awb_number)?->formatted() : null;
    }

    /**
     * IATA volumetric rule: 1 chargeable kg per 6000 cm3, i.e. cbm * 166.67.
     * Chargeable weight is the greater of gross and volumetric.
     */
    public function volumetricWeightKg(): ?float
    {
        return $this->volume_cbm !== null ? round((float) $this->volume_cbm * 166.6667, 2) : null;
    }

    public function suggestedChargeableWeightKg(): float
    {
        return max((float) $this->gross_weight_kg, $this->volumetricWeightKg() ?? 0.0);
    }

    /** Document types this direction expects but has not got. Drives the dashboard gap list. */
    public function missingDocumentTypes(): array
    {
        $present = $this->documents->pluck('type')->all();

        return array_values(array_filter(
            DocumentType::requiredFor($this->direction),
            fn (DocumentType $t) => ! in_array($t->value, $present, true)
        ));
    }

    public function recalculateChargesTotal(): void
    {
        $total = $this->charges()->where('side', 'receivable')->sum('amount');

        // Guard against a no-op write on every page view.
        if ((float) $this->charges_total !== (float) $total) {
            $this->forceFill(['charges_total' => $total])->saveQuietly();
        }
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user && $user->isCustomer()) {
            // A customer with no linked customer record must see nothing, not everything.
            return $query->where('customer_id', $user->customer_id ?? 0);
        }

        return $query;
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || trim($term) === '') {
            return $query;
        }

        $term = trim($term);
        $like = '%'.$term.'%';
        $digits = preg_replace('/\D+/', '', $term) ?? '';

        return $query->where(function (Builder $q) use ($like, $digits) {
            $q->where('reference', 'like', $like)
                ->orWhere('hawb_number', 'like', $like)
                ->orWhere('flight_number', 'like', $like)
                ->orWhere('shipper_name', 'like', $like)
                ->orWhere('consignee_name', 'like', $like)
                ->orWhereHas('customer', fn (Builder $c) => $c->where('name', 'like', $like));

            if ($digits !== '') {
                $q->orWhere('awb_number', 'like', '%'.$digits.'%');
            }
        });
    }

    public static function nextReference(string $direction): string
    {
        $prefix = ($direction === 'import' ? 'IMP-' : 'EXP-').date('Y').'-';

        $last = static::withTrashed()
            ->where('reference', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('reference');

        $n = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }
}
